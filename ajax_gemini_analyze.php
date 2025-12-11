<?php
// Start output buffering to catch any unexpected output
ob_start();

// Suppress error display (errors will still be logged)
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Set up error handler to return JSON on any PHP error
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Set up shutdown handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Clean any previous output
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'Error del servidor: ' . $error['message'],
            'debug' => [
                'file' => $error['file'],
                'line' => $error['line']
            ]
        ]);
    }
});

require_once 'config.php';
require_once 'services/gemini_service.php';

// Fallback for servers without mbstring extension
if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null) {
        return $length === null ? substr($str, $start) : substr($str, $start, $length);
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen($str) {
        return strlen($str);
    }
}

// Clean any output that might have been generated
ob_end_clean();

// Start new output buffer for safety
ob_start();

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

// Calcular antigüedad del reporte
$created_date = new DateTime($complaint_for_ai['created_at']);
$now = new DateTime();
$interval = $created_date->diff($now);
$days_ago = $interval->days;

if ($days_ago == 0) {
    $antiguedad = 'Hoy';
} elseif ($days_ago == 1) {
    $antiguedad = 'Ayer (hace 1 día)';
} else {
    $antiguedad = 'Hace ' . $days_ago . ' días';
}

// Traducir estado técnico a lenguaje legible
$status_map = [
    'unattended_ontime' => 'Sin atender (dentro del plazo)',
    'unattended_late' => 'Sin atender (fuera de plazo / retrasado)',
    'attended_ontime' => 'Atendido a tiempo',
    'attended_late' => 'Atendido a destiempo (fuera del plazo)',
    'in_progress' => 'En proceso de atención',
    'pending' => 'Pendiente',
    'closed' => 'Cerrado',
    'resolved' => 'Resuelto'
];
$status_raw = $complaint_for_ai['status'] ?? 'desconocido';
$status_legible = $status_map[$status_raw] ?? $status_raw;

$context_lines = [
    'Folio del reporte: ' . $folio,
    'Fecha de envío: ' . date('d/m/Y H:i', strtotime($complaint_for_ai['created_at'])),
    'Antigüedad: ' . $antiguedad,
    'Estado actual: ' . $status_legible,
    'Categoría seleccionada por el usuario: ' . ($complaint_for_ai['category_name'] ?? 'Sin categoría'),
    'Es anónimo: ' . ($complaint_for_ai['is_anonymous'] ? 'sí' : 'no'),
];

if (!$complaint_for_ai['is_anonymous']) {
    $context_lines[] = 'Nombre de quien reporta: ' . ($complaint_for_ai['user_name'] ?? 'No especificado');
    $context_lines[] = 'Correo de quien reporta: ' . ($complaint_for_ai['user_email'] ?? 'No especificado');
} else {
    $context_lines[] = 'Nota: Este reporte fue enviado de forma ANÓNIMA';
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

// Obtener reportes existentes del año para detectar duplicados
$startOfYear = (new DateTime('first day of january ' . date('Y')))->format('Y-m-d 00:00:00');
$existing_query = "SELECT c.id, c.folio, c.description, c.status, c.created_at,
                          cat.name as category_name
                   FROM complaints c 
                   LEFT JOIN categories cat ON c.category_id = cat.id 
                   WHERE c.created_at >= '{$startOfYear}'
                   AND c.status NOT IN ('invalid', 'duplicate')
                   AND c.id != {$complaint_id}
                   ORDER BY c.created_at DESC
                   LIMIT 80";
$existing_result = $conn->query($existing_query);
$existing_reports = [];
if ($existing_result) {
    while ($row = $existing_result->fetch_assoc()) {
        $existing_reports[] = $row;
    }
}

// Construir contexto de reportes existentes
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
        $rep_folio = $rep['folio'] ?? str_pad($rep['id'], 6, '0', STR_PAD_LEFT);
        $rep_desc = mb_substr(trim($rep['description']), 0, 120);
        $existing_context .= "Existente #{$rep_folio} (ID:{$rep['id']}): {$rep_desc}\n";
    }
}

// Agregar contexto de reportes existentes al contexto principal
$context .= $existing_context;

// Obtener categorías disponibles
$stmt_cat = $conn->query("SELECT id, name, description FROM categories ORDER BY name");
$categories_list = $stmt_cat->fetch_all(MYSQLI_ASSOC);

$categories_text = "Categorías disponibles:\n";
foreach ($categories_list as $cat) {
    $categories_text .= "- ID: " . $cat['id'] . ", Nombre: " . $cat['name'] . " (" . ($cat['description'] ?? '') . ")\n";
}

// Información detallada de departamentos para Gemini
$departments_info = [
    // 1. Áreas de Dirección y Gestión (Alto Nivel)
    1 => [
        'nombre' => 'Dirección General',
        'funcion' => 'Máxima autoridad, planeación estratégica y gestión de recursos.',
        'quejas_tipicas' => 'Falta de respuesta de subdirecciones, problemas graves de seguridad institucional, denuncias éticas graves, quejas sobre gestión general que no fueron atendidas en niveles inferiores.'
    ],
    3 => [
        'nombre' => 'Dirección Académica',
        'funcion' => 'Responsable de toda la operación docente y de investigación.',
        'quejas_tipicas' => 'Quejas que no fueron resueltas por las Divisiones de Carrera, problemas con la calidad académica general.'
    ],
    4 => [
        'nombre' => 'Dirección de Administración, Planeación y Vinculación',
        'funcion' => 'Administra recursos financieros, humanos y la vinculación con el entorno.',
        'quejas_tipicas' => 'Inconformidad general con servicios administrativos, infraestructura mayor o transparencia.'
    ],
    5 => [
        'nombre' => 'Subdirección Académica',
        'funcion' => 'Coordina directamente a las jefaturas de carrera y docentes.',
        'quejas_tipicas' => 'Problemas de carga académica, conflictos no resueltos con jefes de división.'
    ],
    6 => [
        'nombre' => 'Subdirección de Posgrado e Investigación',
        'funcion' => 'Coordina maestrías y líneas de investigación.',
        'quejas_tipicas' => 'Problemas con becas de posgrado (CONAHCYT), falta de asesores de tesis de maestría.'
    ],
    7 => [
        'nombre' => 'Subdirección de Servicios Administrativos',
        'funcion' => 'Supervisa RH, Financieros y Mantenimiento.',
        'quejas_tipicas' => 'Fallas recurrentes en servicios básicos (luz, agua, limpieza) que no se atienden.'
    ],
    8 => [
        'nombre' => 'Subdirección de Planeación',
        'funcion' => 'Planeación institucional y equipamiento.',
        'quejas_tipicas' => 'Falta de crecimiento en infraestructura, quejas sobre procesos de evaluación institucional.'
    ],
    9 => [
        'nombre' => 'Subdirección de Vinculación',
        'funcion' => 'Relaciones externas, egresados y eventos.',
        'quejas_tipicas' => 'Falta de convenios con empresas, problemas generales con servicio social.'
    ],
    
    // 2. Divisiones Académicas (Carreras)
    10 => [
        'nombre' => 'División de Ingeniería en Electromecánica',
        'funcion' => 'Coordina horarios, docentes y atención al estudiante de Ingeniería en Electromecánica.',
        'quejas_tipicas' => 'Profesor falta a clases, empalme de horarios, trato inadecuado de docente, laboratorio sin equipo, problemas con materias de la carrera.'
    ],
    11 => [
        'nombre' => 'División de Ingeniería en Industrias Alimentarias',
        'funcion' => 'Coordina horarios, docentes y atención al estudiante de Ingeniería en Industrias Alimentarias.',
        'quejas_tipicas' => 'Profesor falta a clases, empalme de horarios, trato inadecuado de docente, laboratorio sin equipo, problemas con materias de la carrera.'
    ],
    12 => [
        'nombre' => 'División de Ingeniería en Sistemas Computacionales',
        'funcion' => 'Coordina horarios, docentes y atención al estudiante de Ingeniería en Sistemas Computacionales.',
        'quejas_tipicas' => 'Profesor falta a clases, empalme de horarios, trato inadecuado de docente, laboratorio de cómputo sin equipo o con equipos dañados, problemas con materias de la carrera, falta de software.'
    ],
    13 => [
        'nombre' => 'División de Ingeniería Industrial',
        'funcion' => 'Coordina horarios, docentes y atención al estudiante de Ingeniería Industrial.',
        'quejas_tipicas' => 'Profesor falta a clases, empalme de horarios, trato inadecuado de docente, laboratorio sin equipo, problemas con materias de la carrera.'
    ],
    14 => [
        'nombre' => 'División de Gastronomía',
        'funcion' => 'Coordina horarios, docentes y atención al estudiante de Gastronomía.',
        'quejas_tipicas' => 'Profesor falta a clases, empalme de horarios, trato inadecuado de docente, cocina/laboratorio sin equipo o insumos, problemas con materias de la carrera.'
    ],
    15 => [
        'nombre' => 'División de Ingeniería en Gestión Empresarial',
        'funcion' => 'Coordina horarios, docentes y atención al estudiante de Ingeniería en Gestión Empresarial.',
        'quejas_tipicas' => 'Profesor falta a clases, empalme de horarios, trato inadecuado de docente, problemas con materias de la carrera.'
    ],
    16 => [
        'nombre' => 'División de Arquitectura',
        'funcion' => 'Coordina horarios, docentes y atención al estudiante de Arquitectura.',
        'quejas_tipicas' => 'Profesor falta a clases, empalme de horarios, trato inadecuado de docente, taller sin equipo, problemas con materias de la carrera.'
    ],
    17 => [
        'nombre' => 'División Licenciatura en Administración',
        'funcion' => 'Coordina horarios, docentes y atención al estudiante de Licenciatura en Administración.',
        'quejas_tipicas' => 'Profesor falta a clases, empalme de horarios, trato inadecuado de docente, problemas con materias de la carrera.'
    ],
    
    // 3. Departamentos de Apoyo Académico
    19 => [
        'nombre' => 'Departamento de Ciencias Básicas',
        'funcion' => 'Coordina materias de tronco común (Matemáticas, Física, Química).',
        'quejas_tipicas' => 'El maestro de Cálculo/Química/Física no enseña bien, índices de reprobación excesivos, falta de reactivos en laboratorios de ciencias.'
    ],
    18 => [
        'nombre' => 'Departamento de Desarrollo Académico',
        'funcion' => 'Capacitación docente, tutorías, material didáctico.',
        'quejas_tipicas' => 'Mi tutor no me atiende, los profesores no usan herramientas digitales, falta de cursos de actualización docente.'
    ],
    
    // 4. Servicios Escolares y Trámites
    25 => [
        'nombre' => 'Departamento de Control Escolar',
        'funcion' => 'Inscripciones, kardex, certificados, títulos, bajas, seguro facultativo.',
        'quejas_tipicas' => 'Errores en historial académico, tardanza en entrega de títulos/constancias, problemas con inscripción en el SII, mal trato en ventanilla.'
    ],
    22 => [
        'nombre' => 'Departamento de Recursos Financieros',
        'funcion' => 'Caja, cobros, facturación.',
        'quejas_tipicas' => 'Caja cerrada en horario de servicio, no aceptan pago, problemas con referencias bancarias.'
    ],
    23 => [
        'nombre' => 'Departamento de Estadística y Evaluación',
        'funcion' => 'Datos institucionales, indicadores.',
        'quejas_tipicas' => 'Errores en reportes estadísticos públicos (poco frecuente para alumnos).'
    ],
    21 => [
        'nombre' => 'Departamento de Planeación y Programación',
        'funcion' => 'Presupuestos y programación de obras.',
        'quejas_tipicas' => 'Obras inconclusas o mal planificadas.'
    ],
    
    // 5. Servicios Generales e Infraestructura
    26 => [
        'nombre' => 'Departamento de Servicios Generales',
        'funcion' => 'Limpieza (intendencia), vigilancia, mantenimiento menor, transporte escolar.',
        'quejas_tipicas' => 'Baños sucios o sin papel, falta de aire acondicionado en aulas, basura en pasillos, inseguridad en estacionamiento, transporte escolar maneja mal.'
    ],
    24 => [
        'nombre' => 'Departamento de Recursos Materiales y Servicios',
        'funcion' => 'Compras, almacén, inventarios.',
        'quejas_tipicas' => 'Falta de mobiliario (pupitres/sillas), no hay insumos (borradores/plumones) en almacén.'
    ],
    
    // 6. Recursos Humanos y Calidad
    20 => [
        'nombre' => 'Departamento de Personal',
        'funcion' => 'Contratación, nómina, control de asistencia del personal.',
        'quejas_tipicas' => 'Personal administrativo ausente en horas laborales, conflictos laborales, mal trato por parte de personal administrativo.'
    ],
    2 => [
        'nombre' => 'Departamento de Certificaciones',
        'funcion' => 'Sistemas de Gestión de Calidad (ISO), Ambiental, Equidad de Género.',
        'quejas_tipicas' => 'Discriminación o acoso, procesos burocráticos lentos (quejas de calidad), no se respeta el cuidado ambiental/reciclaje.'
    ],
    30 => [
        'nombre' => 'Buzón de Quejas, Sugerencias y Felicitaciones',
        'funcion' => 'Administración del propio sistema de quejas.',
        'quejas_tipicas' => 'El sistema de quejas no funciona, reporte de errores técnicos en la plataforma.'
    ],
    
    // 7. Vinculación con el Entorno
    27 => [
        'nombre' => 'Departamento de Vinculación',
        'funcion' => 'Relación empresa-escuela, visitas industriales.',
        'quejas_tipicas' => 'Falta de visitas a empresas, bolsa de trabajo deficiente.'
    ],
    28 => [
        'nombre' => 'Departamento Residencias Profesionales y Servicio Social',
        'funcion' => 'Gestión de trámites de servicio social y residencias.',
        'quejas_tipicas' => 'No encuentro lugar para residencia, tardanza en liberar cartas de servicio social, poca variedad de convenios.'
    ],
    29 => [
        'nombre' => 'Departamento de Difusión y Concertación',
        'funcion' => 'Imagen institucional, redes sociales, eventos.',
        'quejas_tipicas' => 'Información desactualizada en Facebook/Web, falta de difusión de eventos importantes.'
    ],
];

// Construir texto de departamentos con información detallada
$departments_text = "=== DEPARTAMENTOS DISPONIBLES CON SUS FUNCIONES ===\n\n";

$departments_text .= "IMPORTANTE: Selecciona el departamento basándote en su FUNCIÓN y TIPO DE QUEJAS que atiende.\n\n";

$departments_text .= "--- ÁREAS DE DIRECCIÓN (Solo para quejas no resueltas en niveles inferiores o muy graves) ---\n";
foreach ([1, 3, 4, 5, 6, 7, 8, 9] as $id) {
    if (isset($departments_info[$id])) {
        $d = $departments_info[$id];
        $departments_text .= "ID: {$id} | {$d['nombre']}\n";
        $departments_text .= "  Función: {$d['funcion']}\n";
        $departments_text .= "  Quejas típicas: {$d['quejas_tipicas']}\n\n";
    }
}

$departments_text .= "--- DIVISIONES ACADÉMICAS (Problemas con carreras, docentes, horarios) ---\n";
foreach ([10, 11, 12, 13, 14, 15, 16, 17] as $id) {
    if (isset($departments_info[$id])) {
        $d = $departments_info[$id];
        $departments_text .= "ID: {$id} | {$d['nombre']}\n";
        $departments_text .= "  Función: {$d['funcion']}\n";
        $departments_text .= "  Quejas típicas: {$d['quejas_tipicas']}\n\n";
    }
}

$departments_text .= "--- APOYO ACADÉMICO ---\n";
foreach ([18, 19] as $id) {
    if (isset($departments_info[$id])) {
        $d = $departments_info[$id];
        $departments_text .= "ID: {$id} | {$d['nombre']}\n";
        $departments_text .= "  Función: {$d['funcion']}\n";
        $departments_text .= "  Quejas típicas: {$d['quejas_tipicas']}\n\n";
    }
}

$departments_text .= "--- SERVICIOS ESCOLARES Y TRÁMITES ---\n";
foreach ([25, 22, 23, 21] as $id) {
    if (isset($departments_info[$id])) {
        $d = $departments_info[$id];
        $departments_text .= "ID: {$id} | {$d['nombre']}\n";
        $departments_text .= "  Función: {$d['funcion']}\n";
        $departments_text .= "  Quejas típicas: {$d['quejas_tipicas']}\n\n";
    }
}

$departments_text .= "--- SERVICIOS GENERALES E INFRAESTRUCTURA ---\n";
foreach ([26, 24] as $id) {
    if (isset($departments_info[$id])) {
        $d = $departments_info[$id];
        $departments_text .= "ID: {$id} | {$d['nombre']}\n";
        $departments_text .= "  Función: {$d['funcion']}\n";
        $departments_text .= "  Quejas típicas: {$d['quejas_tipicas']}\n\n";
    }
}

$departments_text .= "--- RECURSOS HUMANOS Y CALIDAD ---\n";
foreach ([20, 2, 30] as $id) {
    if (isset($departments_info[$id])) {
        $d = $departments_info[$id];
        $departments_text .= "ID: {$id} | {$d['nombre']}\n";
        $departments_text .= "  Función: {$d['funcion']}\n";
        $departments_text .= "  Quejas típicas: {$d['quejas_tipicas']}\n\n";
    }
}

$departments_text .= "--- VINCULACIÓN CON EL ENTORNO ---\n";
foreach ([27, 28, 29] as $id) {
    if (isset($departments_info[$id])) {
        $d = $departments_info[$id];
        $departments_text .= "ID: {$id} | {$d['nombre']}\n";
        $departments_text .= "  Función: {$d['funcion']}\n";
        $departments_text .= "  Quejas típicas: {$d['quejas_tipicas']}\n\n";
    }
}

$system_instruction = <<<TXT
Eres un asistente de clasificación y análisis para el Buzón de Quejas del Instituto Tecnológico Superior de Ciudad Constitución (ITSCC).

OBJETIVOS PRINCIPALES:
1. PRIMERO determina si el reporte es VÁLIDO, INVÁLIDO o DUPLICADO.
2. Si es válido: clasifica, sugiere categoría, departamentos y genera resumen.
3. Si es inválido o duplicado: indica la acción y el motivo.

=== DETECCIÓN DE REPORTES INVÁLIDOS ===
Un reporte es INVÁLIDO si:
- Contiene solo texto sin sentido, spam o caracteres aleatorios
- No tiene contenido relevante o está prácticamente vacío
- Es ofensivo sin ninguna queja, sugerencia o felicitación real
- No está relacionado con la institución educativa

=== DETECCIÓN DE REPORTES DUPLICADOS ===
Un reporte es DUPLICADO si:
- Describe el MISMO problema de INFRAESTRUCTURA que otro reporte existente (internet, baños, aires, mobiliario)
- Se queja del MISMO profesor o personal por el MISMO motivo
- Reporta el MISMO evento o situación ya reportada

Un reporte NO es duplicado si:
- Son problemas INDIVIDUALES de cada estudiante (inscripción, pago, calificación, trámite personal)
- Son quejas de DIFERENTES profesores o materias
- Son situaciones que afectan personalmente a cada usuario de forma individual

GUÍA PARA SELECCIONAR DEPARTAMENTOS (solo si es válido):
- Para quejas sobre docentes, horarios o materias de una carrera específica → usa la DIVISIÓN de esa carrera.
- Para quejas sobre materias de tronco común (Cálculo, Física, Química) → usa "Departamento de Ciencias Básicas" (ID: 19).
- Para quejas sobre tutores → usa "Departamento de Desarrollo Académico" (ID: 18).
- Para quejas sobre inscripciones, kardex, títulos → usa "Departamento de Control Escolar" (ID: 25).
- Para quejas sobre pagos o caja → usa "Departamento de Recursos Financieros" (ID: 22).
- Para quejas sobre limpieza, baños, vigilancia, aire acondicionado → usa "Departamento de Servicios Generales" (ID: 26).
- Para quejas sobre mobiliario o insumos → usa "Departamento de Recursos Materiales y Servicios" (ID: 24).
- Para quejas sobre residencias profesionales o servicio social → usa "Departamento Residencias Profesionales y Servicio Social" (ID: 28).
- Para quejas sobre personal administrativo → usa "Departamento de Personal" (ID: 20).
- Para quejas sobre discriminación, acoso o calidad → usa "Departamento de Certificaciones" (ID: 2).
- Solo escala a Direcciones/Subdirecciones si la queja es MUY grave o no fue atendida previamente.

INFORMACIÓN QUE DEBE INCLUIR EL RESUMEN (solo si es válido):
- SIEMPRE menciona QUIÉN envió el reporte (nombre de la persona o "una persona de forma anónima" si es anónimo).
- SIEMPRE menciona HACE CUÁNTO TIEMPO se envió (ejemplo: "hace 5 días", "ayer", "hoy").
- SIEMPRE menciona el ESTADO ACTUAL del reporte de forma clara.
- Si hay comentarios de seguimiento, incluye las acciones tomadas.
- Si el reporte está retrasado o fuera de plazo, menciónalo claramente.

ESTILO DE REDACCIÓN DEL RESUMEN:
- Usa lenguaje SENCILLO y CLARO, evita términos técnicos.
- Escribe de forma que cualquier persona pueda entender fácilmente.
- Redacta en tercera persona y tono formal pero accesible.
- El resumen debe ser de 80-150 palabras.

$categories_text

$departments_text

FORMATO DE SALIDA JSON:
{
  "accion": "procesar|invalido|duplicado",
  "duplicado_de": ID_DEL_REPORTE_ORIGINAL_O_NULL,
  "motivo_cierre": "Razón si es inválido/duplicado, null si es válido",
  "tipo": "queja|sugerencia|felicitacion",
  "categoria_id": ID_NUMERICO_DE_LA_CATEGORIA,
  "lista_departamentos": [
    {
      "id": ID_NUMERICO_DEL_DEPARTAMENTO,
      "nombre": "Nombre exacto del departamento",
      "motivo": "Explicación breve de por qué este departamento"
    }
  ],
  "resumen": "Texto del resumen COMPLETO"
}

REGLAS IMPORTANTES:
- Si accion = "invalido" o "duplicado": motivo_cierre es OBLIGATORIO, los demás campos pueden estar vacíos.
- Si accion = "procesar": tipo, categoria_id, lista_departamentos y resumen son OBLIGATORIOS.
- El campo "tipo" debe ser exactamente "queja", "sugerencia" o "felicitacion".
- lista_departamentos SIEMPRE debe tener al menos 1 departamento (máximo 3) si accion = "procesar".
- Usa SOLO IDs numéricos de las listas proporcionadas.
- Si detectas que es duplicado, indica en "duplicado_de" el ID del reporte original.
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
