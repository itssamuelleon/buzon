<?php
// Start output buffering to catch any unexpected output
ob_start();

// Suppress error display (errors will still be logged)
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once 'config.php';
require_once 'services/gemini_service.php';

// Clean any output that might have been generated
ob_end_clean();

header('Content-Type: application/json');

// Verificar autenticación y permisos
if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$complaint_id = isset($input['complaint_id']) ? intval($input['complaint_id']) : 0;

if ($complaint_id === 0) {
    echo json_encode(['success' => false, 'error' => 'ID de reporte inválido']);
    exit;
}

if (!isGeminiConfigured()) {
    echo json_encode(['success' => false, 'error' => 'Configura GEMINI_API_KEY para habilitar el análisis automático.']);
    exit;
}

// Obtener datos del reporte
$stmt_comp = $conn->prepare("
    SELECT c.*, u.name as user_name, u.email as user_email, cat.name as category_name 
    FROM complaints c 
    LEFT JOIN users u ON c.user_id = u.id 
    LEFT JOIN categories cat ON c.category_id = cat.id 
    WHERE c.id = ?
");
$stmt_comp->bind_param("i", $complaint_id);
$stmt_comp->execute();
$complaint_for_ai = $stmt_comp->get_result()->fetch_assoc();
$stmt_comp->close();

if (!$complaint_for_ai) {
    echo json_encode(['success' => false, 'error' => 'No se encontró el reporte solicitado.']);
    exit;
}

// Obtener adjuntos
$stmt_att_ai = $conn->prepare("SELECT file_name, file_type FROM attachments WHERE complaint_id = ?");
$stmt_att_ai->bind_param("i", $complaint_id);
$stmt_att_ai->execute();
$attachments_for_ai = $stmt_att_ai->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_att_ai->close();

// Obtener comentarios y sus adjuntos
$stmt_comments_ai = $conn->prepare("
    SELECT cc.id, cc.comment, cc.created_at, u.name as user_name, u.role as user_role
    FROM complaint_comments cc
    LEFT JOIN users u ON cc.user_id = u.id
    WHERE cc.complaint_id = ?
    ORDER BY cc.created_at ASC
");
$stmt_comments_ai->bind_param("i", $complaint_id);
$stmt_comments_ai->execute();
$comments_for_ai = $stmt_comments_ai->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_comments_ai->close();

// Obtener adjuntos de comentarios
$comment_attachments_for_ai = [];
if (!empty($comments_for_ai)) {
    $stmt_comment_att = $conn->prepare("
        SELECT ca.file_name, ca.file_type, ca.comment_id
        FROM comment_attachments ca
        INNER JOIN complaint_comments cc ON ca.comment_id = cc.id
        WHERE cc.complaint_id = ?
    ");
    $stmt_comment_att->bind_param("i", $complaint_id);
    $stmt_comment_att->execute();
    $comment_attachments_for_ai = $stmt_comment_att->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_comment_att->close();
}

// Preparar contexto
$folio = $complaint_for_ai['folio'] ?? str_pad($complaint_for_ai['id'], 6, '0', STR_PAD_LEFT);

$context_lines = [
    'Folio del reporte: ' . $folio,
    'Fecha de envío: ' . date('d/m/Y H:i', strtotime($complaint_for_ai['created_at'])),
    'Estado actual: ' . ($complaint_for_ai['status'] ?? 'desconocido'),
    'Categoría seleccionada por el usuario: ' . ($complaint_for_ai['category_name'] ?? 'Sin categoría'),
    'Es anónimo: ' . ($complaint_for_ai['is_anonymous'] ? 'sí' : 'no'),
];

if (!$complaint_for_ai['is_anonymous']) {
    $context_lines[] = 'Nombre de quien reporta: ' . ($complaint_for_ai['user_name'] ?? '');
    $context_lines[] = 'Correo de quien reporta: ' . ($complaint_for_ai['user_email'] ?? '');
}

$context_lines[] = '';
$context_lines[] = 'Descripción original del reporte:';
$context_lines[] = trim($complaint_for_ai['description'] ?? '(sin descripción)');

if (!empty($attachments_for_ai)) {
    $context_lines[] = '';
    $context_lines[] = 'Archivos adjuntos al reporte inicial:';
    foreach ($attachments_for_ai as $attachment) {
        $context_lines[] = '- ' . ($attachment['file_name'] ?? 'sin nombre') . ' [' . ($attachment['file_type'] ?? 'tipo desconocido') . ']';
    }
}

if (!empty($comments_for_ai)) {
    $context_lines[] = '';
    $context_lines[] = 'Comentarios y seguimiento del reporte:';
    foreach ($comments_for_ai as $idx => $comment) {
        $comment_num = $idx + 1;
        $role_label = '';
        if (isset($comment['user_role'])) {
            $role_label = ($comment['user_role'] === 'admin' ? ' (Administrador)' : ($comment['user_role'] === 'manager' ? ' (Encargado)' : ''));
        }
        $context_lines[] = '';
        $context_lines[] = "Comentario #{$comment_num} - " . ($comment['user_name'] ?? 'Usuario') . $role_label . ' - ' . date('d/m/Y H:i', strtotime($comment['created_at']));
        $context_lines[] = trim($comment['comment']);
        
        // Buscar adjuntos de este comentario
        $comment_id = $comment['id'] ?? null;
        if ($comment_id) {
            $attachments_in_comment = array_filter($comment_attachments_for_ai, function($att) use ($comment_id) {
                return isset($att['comment_id']) && $att['comment_id'] == $comment_id;
            });
            if (!empty($attachments_in_comment)) {
                $context_lines[] = 'Archivos adjuntos en este comentario:';
                foreach ($attachments_in_comment as $att) {
                    $context_lines[] = '  - ' . ($att['file_name'] ?? 'sin nombre') . ' [' . ($att['file_type'] ?? 'tipo desconocido') . ']';
                }
            }
        }
    }
}

$context = implode("\n", $context_lines);

// Obtener categorías disponibles
$stmt_cat = $conn->query("SELECT id, name, description FROM categories ORDER BY name");
$categories_list = $stmt_cat->fetch_all(MYSQLI_ASSOC);

$categories_text = "Categorías disponibles:\n";
foreach ($categories_list as $cat) {
    $categories_text .= "- ID: " . $cat['id'] . ", Nombre: " . $cat['name'] . " (" . ($cat['description'] ?? '') . ")\n";
}

// Obtener departamentos disponibles
$stmt_dept = $conn->query("SELECT id, name FROM departments ORDER BY name");
$departments_list = $stmt_dept->fetch_all(MYSQLI_ASSOC);

$departments_text = "Departamentos disponibles (con sus IDs):\n";
foreach ($departments_list as $dept) {
    $departments_text .= "- ID: " . $dept['id'] . ", Nombre: " . $dept['name'] . "\n";
}

$system_instruction = <<<TXT
Eres un asistente de clasificación y análisis para el Buzón de Quejas del Instituto Tecnológico Superior de Ciudad Constitución (ITSCC).

Objetivos:
1. Clasifica el reporte estrictamente como "queja", "sugerencia" o "felicitacion" según el texto proporcionado (este es el "Tipo").
2. Sugiere la categoría más apropiada de las disponibles en la base de datos (devuelve su ID).
3. Propón entre uno y tres departamentos más adecuados para atenderlo usando sus IDs. SIEMPRE debes sugerir al menos un departamento, nunca un arreglo vacío.
4. Genera un resumen COMPLETO Y DETALLADO del reporte en español, usando un tono formal y profesional.

IMPORTANTE SOBRE EL RESUMEN:
- El resumen debe incluir TODA la información relevante del reporte inicial Y de todos los comentarios de seguimiento.
- Si hay comentarios de administradores o encargados, incluye sus opiniones, acciones tomadas, y cualquier información adicional que hayan proporcionado.
- Si hay múltiples comentarios, sintetiza la conversación completa y el progreso del caso.
- El resumen debe ser comprehensivo (entre 100-200 palabras), no solo del reporte inicial.
- Incluye cualquier resolución, acción tomada, o estado actual mencionado en los comentarios.
- Si no hay comentarios, enfócate solo en el reporte inicial.

$categories_text

$departments_text

Formato de salida:
Responde EXCLUSIVAMENTE en JSON válido (sin texto adicional ni bloques Markdown) con la siguiente estructura:
{
  "tipo": "queja|sugerencia|felicitacion",
  "categoria_id": ID_NUMERICO_DE_LA_CATEGORIA,
  "lista_departamentos": [
    {
      "id": ID_NUMERICO_DEL_DEPARTAMENTO,
      "nombre": "Nombre exacto del departamento",
      "motivo": "Explicación breve en español"
    }
  ],
  "resumen": "Texto del resumen COMPLETO en español, incluyendo información del reporte inicial Y todos los comentarios de seguimiento"
}

Asegúrate de que:
- El campo "tipo" sea exactamente "queja", "sugerencia" o "felicitacion".
- El campo "categoria_id" contenga el ID numérico exacto de una categoría disponible (no el nombre, el ID).
- El campo "lista_departamentos" SIEMPRE contenga al menos un departamento (mínimo 1, máximo 3 elementos) con sus IDs numéricos exactos.
- Los campos "id" en lista_departamentos deben ser números enteros, no strings.
- NUNCA devuelvas un arreglo vacío en "lista_departamentos". Siempre sugiere al menos un departamento.
- NUNCA devuelvas nombres en lugar de IDs. Usa SOLO los IDs proporcionados.
- El "resumen" debe ser COMPREHENSIVO e incluir información de TODOS los comentarios disponibles, no solo del reporte inicial.
TXT;

// Debug: Log the context being sent to Gemini (temporary - remove after verification)
error_log("=== GEMINI CONTEXT DEBUG ===");
error_log("Complaint ID: " . $complaint_id);
error_log("Number of comments found: " . count($comments_for_ai));
error_log("Context length: " . strlen($context) . " characters");
error_log("Context preview (first 500 chars): " . substr($context, 0, 500));
error_log("============================");

$gemini_result = generateGeminiResponse($system_instruction, $context, [
    'responseMimeType' => 'application/json',
]);

if ($gemini_result['success']) {
    $content = trim($gemini_result['content'] ?? '');
    if (preg_match('/```(?:json)?\s*(.+?)```/is', $content, $matches)) {
        $content = trim($matches[1]);
    }

    $decoded = json_decode($content, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // Buscar nombre de la categoría
        $category_name = 'Sin categoría';
        if (isset($decoded['categoria_id'])) {
            foreach ($categories_list as $cat) {
                if ($cat['id'] == $decoded['categoria_id']) {
                    $category_name = $cat['name'];
                    break;
                }
            }
        }
        $decoded['categoria_nombre'] = $category_name;
        
        echo json_encode(['success' => true, 'data' => $decoded]);
    } else {
        echo json_encode(['success' => true, 'raw' => $content]);
    }
} else {
    echo json_encode(['success' => false, 'error' => $gemini_result['error'] ?? 'Error desconocido de Gemini']);
}
