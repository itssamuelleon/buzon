<?php
require_once 'config.php';
require_once 'services/gemini_service.php';

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

// Obtener evidencia de respuesta
$stmt_resp_ai = $conn->prepare("SELECT file_name, file_type FROM response_evidence WHERE complaint_id = ?");
$stmt_resp_ai->bind_param("i", $complaint_id);
$stmt_resp_ai->execute();
$response_evidence_for_ai = $stmt_resp_ai->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_resp_ai->close();

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
    $context_lines[] = 'Archivos adjuntos:';
    foreach ($attachments_for_ai as $attachment) {
        $context_lines[] = '- ' . ($attachment['file_name'] ?? 'sin nombre') . ' [' . ($attachment['file_type'] ?? 'tipo desconocido') . ']';
    }
}

if (!empty($response_evidence_for_ai)) {
    $context_lines[] = '';
    $context_lines[] = 'Evidencia de respuesta cargada por administradores:';
    foreach ($response_evidence_for_ai as $evidence) {
        $context_lines[] = '- ' . ($evidence['file_name'] ?? 'sin nombre') . ' [' . ($evidence['file_type'] ?? 'tipo desconocido') . ']';
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
Eres un asistente de clasificación para el Buzón de Quejas del Instituto Tecnológico Superior de Ciudad Constitución (ITSCC).

Objetivos:
1. Clasifica el reporte estrictamente como "queja", "sugerencia" o "felicitacion" según el texto proporcionado (este es el "Tipo").
2. Sugiere la categoría más apropiada de las disponibles en la base de datos (devuelve su ID).
3. Propón entre uno y tres departamentos más adecuados para atenderlo usando sus IDs. SIEMPRE debes sugerir al menos un departamento, nunca un arreglo vacío.
4. Genera un resumen breve (máximo 80 palabras) y claro en español, usando un tono formal.

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
  "resumen": "Texto del resumen en español"
}

Asegúrate de que:
- El campo "tipo" sea exactamente "queja", "sugerencia" o "felicitacion".
- El campo "categoria_id" contenga el ID numérico exacto de una categoría disponible (no el nombre, el ID).
- El campo "lista_departamentos" SIEMPRE contenga al menos un departamento (mínimo 1, máximo 3 elementos) con sus IDs numéricos exactos.
- Los campos "id" en lista_departamentos deben ser números enteros, no strings.
- NUNCA devuelvas un arreglo vacío en "lista_departamentos". Siempre sugiere al menos un departamento.
- NUNCA devuelvas nombres en lugar de IDs. Usa SOLO los IDs proporcionados.
TXT;

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
