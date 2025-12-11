<?php
/**
 * Endpoint AJAX para analizar múltiples reportes con Gemini EN UNA SOLA LLAMADA
 * Detecta: tipo, categoría, departamentos, duplicados e inválidos
 * Solo para admins
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once 'config.php';
require_once 'services/gemini_service.php';

ob_end_clean();

header('Content-Type: application/json');

// Verificar autenticación y permisos (solo admins)
if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$date_range = isset($input['date_range']) ? $input['date_range'] : 'this_year';

if (!isGeminiConfigured()) {
    echo json_encode(['success' => false, 'error' => 'Configura GEMINI_API_KEY para habilitar el análisis automático.']);
    exit;
}

// Construir query para obtener reportes sin asignar según el periodo
$query = "SELECT c.id, c.folio, c.description, c.status, c.created_at, c.is_anonymous,
                 u.name as user_name, u.email as user_email, 
                 cat.name as category_name
          FROM complaints c 
          LEFT JOIN users u ON c.user_id = u.id 
          LEFT JOIN categories cat ON c.category_id = cat.id 
          WHERE NOT EXISTS (SELECT 1 FROM complaint_departments cd WHERE cd.complaint_id = c.id)";

// Aplicar filtro de fecha
if ($date_range && $date_range !== 'all') {
    if ($date_range === 'this_year') {
        $startOfYear = (new DateTime('first day of january ' . date('Y')))->format('Y-m-d 00:00:00');
        $query .= " AND c.created_at >= '" . $conn->real_escape_string($startOfYear) . "'";
    } else {
        $query .= " AND c.created_at >= DATE_SUB(NOW(), INTERVAL " . intval($date_range) . " DAY)";
    }
}

$query .= " ORDER BY c.created_at DESC LIMIT 30";

$result = $conn->query($query);

if (!$result) {
    echo json_encode(['success' => false, 'error' => 'Error en consulta: ' . $conn->error]);
    exit;
}

$complaints = [];
while ($row = $result->fetch_assoc()) {
    $complaints[] = $row;
}

if (empty($complaints)) {
    echo json_encode(['success' => true, 'data' => [], 'message' => 'No hay reportes sin asignar en el periodo seleccionado.']);
    exit;
}

// Obtener TODOS los reportes del año para detectar duplicados
$startOfYear = (new DateTime('first day of january ' . date('Y')))->format('Y-m-d 00:00:00');
$existing_query = "SELECT c.id, c.folio, c.description, c.status, c.created_at,
                          cat.name as category_name
                   FROM complaints c 
                   LEFT JOIN categories cat ON c.category_id = cat.id 
                   WHERE c.created_at >= '{$startOfYear}'
                   AND c.status NOT IN ('invalid', 'duplicate')
                   ORDER BY c.created_at DESC
                   LIMIT 100";
$existing_result = $conn->query($existing_query);
$existing_reports = [];
if ($existing_result) {
    while ($row = $existing_result->fetch_assoc()) {
        $existing_reports[] = $row;
    }
}

// Construir contexto de reportes existentes para detectar duplicados
$existing_context = "";
if (!empty($existing_reports)) {
    $existing_context = "\n\n=== REPORTES EXISTENTES ESTE AÑO (para detectar duplicados) ===\n";
    $existing_context .= "IMPORTANTE: Un reporte es DUPLICADO solo si describe el MISMO PROBLEMA ESPECÍFICO.\n";
    $existing_context .= "- Dos quejas sobre internet lento/sin funcionar = DUPLICADO (mismo problema de infraestructura)\n";
    $existing_context .= "- Dos quejas sobre inscripción de diferentes estudiantes = NO DUPLICADO (cada estudiante tiene su propio caso)\n";
    $existing_context .= "- Dos quejas sobre el mismo profesor = DUPLICADO\n";
    $existing_context .= "- Dos quejas sobre baños sucios = DUPLICADO\n";
    $existing_context .= "- Dos quejas sobre pagos de diferentes estudiantes = NO DUPLICADO\n\n";
    
    foreach ($existing_reports as $idx => $rep) {
        $folio = $rep['folio'] ?? str_pad($rep['id'], 6, '0', STR_PAD_LEFT);
        $desc = mb_substr(trim($rep['description']), 0, 150);
        $existing_context .= "Existente #{$folio} (ID:{$rep['id']}): {$desc}\n";
    }
}

// Obtener categorías disponibles
$stmt_cat = $conn->query("SELECT id, name, description FROM categories ORDER BY name");
$categories_list = $stmt_cat->fetch_all(MYSQLI_ASSOC);

$categories_text = "";
foreach ($categories_list as $cat) {
    $categories_text .= "ID:" . $cat['id'] . " - " . $cat['name'] . "\n";
}

// Obtener departamentos disponibles
$stmt_dept = $conn->query("SELECT id, name FROM departments ORDER BY name");
$departments_list = $stmt_dept->fetch_all(MYSQLI_ASSOC);

$departments_text = "";
foreach ($departments_list as $dept) {
    $departments_text .= "ID:" . $dept['id'] . " - " . $dept['name'] . "\n";
}

// Preparar el contexto con los reportes a analizar
$reports_context = "=== REPORTES A ANALIZAR ===\n\n";
foreach ($complaints as $index => $complaint) {
    $created_date = new DateTime($complaint['created_at']);
    $now = new DateTime();
    $interval = $created_date->diff($now);
    $days_ago = $interval->days;
    
    $antiguedad = $days_ago == 0 ? 'Hoy' : ($days_ago == 1 ? 'Ayer' : "Hace {$days_ago} días");
    $folio = $complaint['folio'] ?? str_pad($complaint['id'], 6, '0', STR_PAD_LEFT);
    $remitente = $complaint['is_anonymous'] ? 'Anónimo' : ($complaint['user_name'] ?? 'No especificado');
    
    $descripcion = trim($complaint['description']);
    if (mb_strlen($descripcion) > 300) {
        $descripcion = mb_substr($descripcion, 0, 300) . '...';
    }
    
    $reports_context .= "---REPORTE {$index}---\n";
    $reports_context .= "ID: {$complaint['id']}\n";
    $reports_context .= "Folio: #{$folio}\n";
    $reports_context .= "Antigüedad: {$antiguedad}\n";
    $reports_context .= "Remitente: {$remitente}\n";
    $reports_context .= "Descripción: {$descripcion}\n\n";
}

// Agregar contexto de reportes existentes
$reports_context .= $existing_context;

// Instrucción del sistema
$system_instruction = <<<TXT
Eres un asistente de clasificación para el Buzón de Quejas del ITSCC. 
Analiza TODOS los reportes y detecta si son válidos, inválidos o duplicados.

CATEGORÍAS DISPONIBLES:
{$categories_text}

DEPARTAMENTOS DISPONIBLES:
{$departments_text}

INSTRUCCIONES:
1. Para CADA reporte, determina:
   - accion: "procesar" (válido), "invalido", o "duplicado"
   - Si es "duplicado", indica el folio/ID del reporte original
   - Si es "procesar", sugiere tipo, categoría y departamentos

2. Un reporte es INVÁLIDO si:
   - Contiene solo texto sin sentido, spam o caracteres aleatorios
   - No tiene contenido relevante o está vacío
   - Es ofensivo sin ninguna queja real

3. Un reporte es DUPLICADO si:
   - Describe el MISMO problema de INFRAESTRUCTURA que otro (internet, baños, aire, etc.)
   - Se queja del MISMO profesor/persona
   - Reporta el MISMO evento ya reportado
   
4. Un reporte NO es duplicado si:
   - Son problemas INDIVIDUALES (inscripción, pago, calificación de cada estudiante)
   - Son quejas de DIFERENTES materias o profesores
   - Son trámites personales de cada usuario

FORMATO JSON:
{
  "reportes": [
    {
      "id": ID_REPORTE,
      "accion": "procesar|invalido|duplicado",
      "duplicado_de": ID_REPORTE_ORIGINAL_O_NULL,
      "motivo_cierre": "Razón si es inválido/duplicado, null si procesar",
      "tipo": "queja|sugerencia|felicitacion",
      "categoria_id": ID_CATEGORIA,
      "departamentos": [{"id": ID, "nombre": "Nombre", "motivo": "razón"}],
      "resumen": "Resumen breve (40-60 palabras)"
    }
  ]
}

IMPORTANTE:
- Para reportes con accion="invalido" o "duplicado", los campos tipo/categoria/departamentos pueden estar vacíos
- El campo "id" debe coincidir EXACTAMENTE con el ID del reporte
- Usa SOLO IDs numéricos de las listas proporcionadas
TXT;

// Una sola llamada a Gemini
$gemini_result = generateGeminiResponse($system_instruction, $reports_context, [
    'responseMimeType' => 'application/json',
]);

if (!$gemini_result['success']) {
    echo json_encode(['success' => false, 'error' => $gemini_result['error'] ?? 'Error de Gemini']);
    exit;
}

// Parsear respuesta
$content = trim($gemini_result['content'] ?? '');
if (preg_match('/```(?:json)?\s*(.+?)```/is', $content, $matches)) {
    $content = trim($matches[1]);
}

$decoded = json_decode($content, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['reportes'])) {
    echo json_encode([
        'success' => false, 
        'error' => 'Error al parsear respuesta de IA',
        'raw' => $content
    ]);
    exit;
}

// Mapear resultados
$results = [];
$analyzed_ids = [];

foreach ($decoded['reportes'] as $analysis) {
    $complaint_id = intval($analysis['id'] ?? 0);
    $analyzed_ids[$complaint_id] = true;
    
    // Buscar datos originales
    $original = null;
    foreach ($complaints as $c) {
        if (intval($c['id']) === $complaint_id) {
            $original = $c;
            break;
        }
    }
    
    if (!$original) continue;
    
    $folio = $original['folio'] ?? str_pad($complaint_id, 6, '0', STR_PAD_LEFT);
    $created_date = new DateTime($original['created_at']);
    $now = new DateTime();
    $interval = $created_date->diff($now);
    $days_ago = $interval->days;
    $antiguedad = $days_ago == 0 ? 'Hoy' : ($days_ago == 1 ? 'Ayer' : "Hace {$days_ago} días");
    
    // Buscar nombre de categoría
    $categoria_nombre = 'Sin categoría';
    if (isset($analysis['categoria_id']) && $analysis['categoria_id']) {
        foreach ($categories_list as $cat) {
            if (intval($cat['id']) === intval($analysis['categoria_id'])) {
                $categoria_nombre = $cat['name'];
                break;
            }
        }
    }
    
    $accion = $analysis['accion'] ?? 'procesar';
    
    $results[] = [
        'complaint_id' => $complaint_id,
        'folio' => $folio,
        'description_short' => mb_substr(trim($original['description']), 0, 100) . (mb_strlen($original['description']) > 100 ? '...' : ''),
        'antiguedad' => $antiguedad,
        'user_name' => $original['is_anonymous'] ? 'Anónimo' : ($original['user_name'] ?? 'No especificado'),
        'current_category' => $original['category_name'] ?? 'Sin categoría',
        'success' => true,
        'error' => null,
        'data' => [
            'accion' => $accion,
            'duplicado_de' => $analysis['duplicado_de'] ?? null,
            'motivo_cierre' => $analysis['motivo_cierre'] ?? null,
            'tipo' => $analysis['tipo'] ?? 'queja',
            'categoria_id' => intval($analysis['categoria_id'] ?? 0),
            'categoria_nombre' => $categoria_nombre,
            'lista_departamentos' => $analysis['departamentos'] ?? [],
            'resumen' => $analysis['resumen'] ?? 'Sin resumen'
        ]
    ];
}

// Agregar reportes no analizados
foreach ($complaints as $complaint) {
    $complaint_id = intval($complaint['id']);
    if (!isset($analyzed_ids[$complaint_id])) {
        $folio = $complaint['folio'] ?? str_pad($complaint_id, 6, '0', STR_PAD_LEFT);
        $created_date = new DateTime($complaint['created_at']);
        $now = new DateTime();
        $interval = $created_date->diff($now);
        $days_ago = $interval->days;
        $antiguedad = $days_ago == 0 ? 'Hoy' : ($days_ago == 1 ? 'Ayer' : "Hace {$days_ago} días");
        
        $results[] = [
            'complaint_id' => $complaint_id,
            'folio' => $folio,
            'description_short' => mb_substr(trim($complaint['description']), 0, 100) . '...',
            'antiguedad' => $antiguedad,
            'user_name' => $complaint['is_anonymous'] ? 'Anónimo' : ($complaint['user_name'] ?? 'No especificado'),
            'current_category' => $complaint['category_name'] ?? 'Sin categoría',
            'success' => false,
            'error' => 'No fue incluido en el análisis',
            'data' => null
        ];
    }
}

echo json_encode([
    'success' => true,
    'data' => $results,
    'total' => count($results),
    'analyzed' => count(array_filter($results, fn($r) => $r['success']))
]);
