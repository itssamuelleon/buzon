<?php
/**
 * Endpoint AJAX para aplicar sugerencias de Gemini a múltiples reportes
 * Soporta: asignar departamentos, marcar como inválido/duplicado
 * Solo para admins
 */

require_once 'config.php';
require_once 'config/email_config.php';
require_once 'send_email.php';

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

if (!isset($input['suggestions']) || !is_array($input['suggestions'])) {
    echo json_encode(['success' => false, 'error' => 'No se proporcionaron sugerencias']);
    exit;
}

$suggestions = $input['suggestions'];
$results = [];
$success_count = 0;
$error_count = 0;

foreach ($suggestions as $suggestion) {
    $complaint_id = isset($suggestion['complaint_id']) ? intval($suggestion['complaint_id']) : 0;
    $accion = isset($suggestion['accion']) ? $suggestion['accion'] : 'procesar';
    $categoria_id = isset($suggestion['categoria_id']) ? intval($suggestion['categoria_id']) : null;
    $departamentos = isset($suggestion['departamentos']) ? $suggestion['departamentos'] : [];
    $motivo_cierre = isset($suggestion['motivo_cierre']) ? $suggestion['motivo_cierre'] : null;
    $duplicado_de = isset($suggestion['duplicado_de']) ? intval($suggestion['duplicado_de']) : null;
    
    if ($complaint_id === 0) {
        $results[] = ['complaint_id' => $complaint_id, 'success' => false, 'error' => 'ID de reporte inválido'];
        $error_count++;
        continue;
    }
    
    try {
        $conn->begin_transaction();
        
        // Obtener datos del reporte
        $stmt = $conn->prepare("SELECT c.*, cat.name as category_name FROM complaints c LEFT JOIN categories cat ON c.category_id = cat.id WHERE c.id = ?");
        $stmt->bind_param("i", $complaint_id);
        $stmt->execute();
        $complaint = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$complaint) {
            throw new Exception('Reporte no encontrado');
        }
        
        // Manejar según la acción
        if ($accion === 'invalido') {
            // Marcar como inválido
            $stmt_update = $conn->prepare("UPDATE complaints SET status = 'invalid', attended_at = NOW() WHERE id = ?");
            $stmt_update->bind_param("i", $complaint_id);
            $stmt_update->execute();
            $stmt_update->close();
            
            // Agregar comentario del sistema
            if ($motivo_cierre) {
                $admin_id = $_SESSION['user_id'];
                $comentario = "⚠️ Reporte marcado como INVÁLIDO por análisis de IA.\n\nMotivo: " . $motivo_cierre;
                $stmt_comment = $conn->prepare("INSERT INTO complaint_comments (complaint_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())");
                $stmt_comment->bind_param("iis", $complaint_id, $admin_id, $comentario);
                $stmt_comment->execute();
                $stmt_comment->close();
            }
            
            $conn->commit();
            $results[] = [
                'complaint_id' => $complaint_id,
                'success' => true,
                'action' => 'invalid',
                'message' => 'Marcado como inválido'
            ];
            $success_count++;
            
        } elseif ($accion === 'duplicado') {
            // Marcar como duplicado
            $stmt_update = $conn->prepare("UPDATE complaints SET status = 'duplicate', attended_at = NOW() WHERE id = ?");
            $stmt_update->bind_param("i", $complaint_id);
            $stmt_update->execute();
            $stmt_update->close();
            
            // Agregar comentario del sistema
            $admin_id = $_SESSION['user_id'];
            $folio_duplicado = '';
            if ($duplicado_de) {
                $stmt_folio = $conn->prepare("SELECT folio FROM complaints WHERE id = ?");
                $stmt_folio->bind_param("i", $duplicado_de);
                $stmt_folio->execute();
                $folio_result = $stmt_folio->get_result()->fetch_assoc();
                $folio_duplicado = $folio_result ? ('#' . ($folio_result['folio'] ?? str_pad($duplicado_de, 6, '0', STR_PAD_LEFT))) : "ID:{$duplicado_de}";
                $stmt_folio->close();
            }
            
            $comentario = "🔄 Reporte marcado como DUPLICADO por análisis de IA.";
            if ($folio_duplicado) {
                $comentario .= "\n\nDuplicado del reporte: " . $folio_duplicado;
            }
            if ($motivo_cierre) {
                $comentario .= "\n\nMotivo: " . $motivo_cierre;
            }
            
            $stmt_comment = $conn->prepare("INSERT INTO complaint_comments (complaint_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())");
            $stmt_comment->bind_param("iis", $complaint_id, $admin_id, $comentario);
            $stmt_comment->execute();
            $stmt_comment->close();
            
            $conn->commit();
            $results[] = [
                'complaint_id' => $complaint_id,
                'success' => true,
                'action' => 'duplicate',
                'message' => 'Marcado como duplicado'
            ];
            $success_count++;
            
        } else {
            // Procesar normalmente (asignar categoría y departamentos)
            
            // Actualizar categoría si se proporcionó
            if ($categoria_id) {
                $stmt_cat = $conn->prepare("UPDATE complaints SET category_id = ? WHERE id = ?");
                $stmt_cat->bind_param("ii", $categoria_id, $complaint_id);
                $stmt_cat->execute();
                $stmt_cat->close();
            }
            
            // Asignar departamentos
            $departments_assigned = [];
            if (!empty($departamentos)) {
                foreach ($departamentos as $dept) {
                    $dept_id = is_array($dept) ? intval($dept['id']) : intval($dept);
                    
                    // Verificar si ya está asignado
                    $stmt_check = $conn->prepare("SELECT 1 FROM complaint_departments WHERE complaint_id = ? AND department_id = ?");
                    $stmt_check->bind_param("ii", $complaint_id, $dept_id);
                    $stmt_check->execute();
                    $exists = $stmt_check->get_result()->num_rows > 0;
                    $stmt_check->close();
                    
                    if (!$exists) {
                        // Insertar asignación
                        $stmt_insert = $conn->prepare("INSERT INTO complaint_departments (complaint_id, department_id, assigned_at) VALUES (?, ?, NOW())");
                        $stmt_insert->bind_param("ii", $complaint_id, $dept_id);
                        $stmt_insert->execute();
                        $stmt_insert->close();
                        
                        // Obtener datos del departamento
                        $stmt_dept = $conn->prepare("SELECT id, name, manager, email FROM departments WHERE id = ?");
                        $stmt_dept->bind_param("i", $dept_id);
                        $stmt_dept->execute();
                        $department = $stmt_dept->get_result()->fetch_assoc();
                        $stmt_dept->close();
                        
                        if ($department) {
                            $departments_assigned[] = $department['name'];
                            
                            // Enviar notificación por correo
                            if (!empty($department['email'])) {
                                $stmt_cat_name = $conn->prepare("SELECT name FROM categories WHERE id = ?");
                                $cat_id_for_email = $categoria_id ?? $complaint['category_id'];
                                $stmt_cat_name->bind_param("i", $cat_id_for_email);
                                $stmt_cat_name->execute();
                                $cat_result = $stmt_cat_name->get_result()->fetch_assoc();
                                $stmt_cat_name->close();
                                
                                $complaint_for_email = [
                                    'id' => $complaint_id,
                                    'folio' => $complaint['folio'] ?? str_pad($complaint_id, 6, '0', STR_PAD_LEFT),
                                    'description' => $complaint['description'],
                                    'category_name' => $cat_result['name'] ?? 'Sin categoría',
                                    'created_at' => $complaint['created_at']
                                ];
                                
                                sendDepartmentNotification($department, $complaint_for_email);
                            }
                        }
                    }
                }
            }
            
            $conn->commit();
            
            $results[] = [
                'complaint_id' => $complaint_id,
                'success' => true,
                'action' => 'assigned',
                'departments_assigned' => $departments_assigned
            ];
            $success_count++;
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $results[] = [
            'complaint_id' => $complaint_id,
            'success' => false,
            'error' => $e->getMessage()
        ];
        $error_count++;
    }
}

echo json_encode([
    'success' => true,
    'results' => $results,
    'summary' => [
        'total' => count($suggestions),
        'success' => $success_count,
        'errors' => $error_count
    ]
]);
