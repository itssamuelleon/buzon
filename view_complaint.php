<?php 
// Cargar configuración y dependencias ANTES de cualquier salida HTML
require_once 'config.php';
require_once 'config/email_config.php';
require_once 'status_helper.php';
require_once 'services/gemini_service.php';

// Verificar autenticación
if (!isLoggedIn()) {
    $current_page = 'view_complaint.php?' . $_SERVER['QUERY_STRING'];
    header('Location: login.php?redirect=' . urlencode($current_page));
    exit;
}

$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($complaint_id === 0) {
    header('Location: index.php');
    exit;
}

// Get complaint details FIRST - before processing any logic
$stmt = $conn->prepare("
    SELECT c.*, u.name as user_name, u.email as user_email, u.profile_photo as user_profile_photo, cat.name as category_name 
    FROM complaints c 
    LEFT JOIN users u ON c.user_id = u.id 
    LEFT JOIN categories cat ON c.category_id = cat.id 
    WHERE c.id = ?
");
$stmt->bind_param("i", $complaint_id);
$stmt->execute();
$complaint = $stmt->get_result()->fetch_assoc();

if (!$complaint) {
    header('Location: dashboard.php');
    exit;
}

// Check if dashboard is restricted and verify permissions
$stmt_restrict = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'restrict_dashboard_access'");
$stmt_restrict->execute();
$result_restrict = $stmt_restrict->get_result();
$is_dashboard_restricted = false;
if ($row_restrict = $result_restrict->fetch_assoc()) {
    $is_dashboard_restricted = $row_restrict['setting_value'] == '1';
}

// If dashboard is restricted, verify user has permission to view this complaint
if ($is_dashboard_restricted && !isAdmin()) {
    $can_view = false;
    
    // Check if user is the complaint owner
    if ($complaint['user_id'] == $_SESSION['user_id']) {
        $can_view = true;
    }
    
    // Check if user is a manager assigned to this complaint
    if (!$can_view && isset($_SESSION['role']) && $_SESSION['role'] === 'manager') {
        // Get user's email to check if they're assigned to this complaint
        $user_email = $_SESSION['email'];
        
        // Check if user's department is assigned to this complaint
        $stmt_check = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM complaint_departments cd
            JOIN departments d ON cd.department_id = d.id
            WHERE cd.complaint_id = ? AND d.email = ?
        ");
        $stmt_check->bind_param("is", $complaint_id, $user_email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $row_check = $result_check->fetch_assoc();
        
        if ($row_check['count'] > 0) {
            $can_view = true;
        }
    }
    
    // If user doesn't have permission, redirect
    if (!$can_view) {
        header('Location: my_complaints.php');
        exit;
    }
}

// Helper to check if user is staff (admin or manager) or original complaint author
function isStaff() {
    global $complaint;
    return isAdmin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager') || (isset($_SESSION['user_id']) && isset($complaint['user_id']) && $_SESSION['user_id'] == $complaint['user_id']);
}

// Helper to check if user can close reports (admin or assigned manager)
function canCloseReport() {
    global $complaint, $complaint_id, $conn;
    
    // Admins can always close reports
    if (isAdmin()) {
        return true;
    }
    
    // Managers can close reports only if they are assigned to this complaint's departments
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager' && isset($_SESSION['email'])) {
        // Check if this manager's email is in one of the departments assigned to this complaint
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM complaint_departments cd
            JOIN departments d ON cd.department_id = d.id
            WHERE cd.complaint_id = ? AND d.email = ?
        ");
        $stmt->bind_param("is", $complaint_id, $_SESSION['email']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'] > 0;
    }
    
    return false;
}

// Handle Comment Submission
// Allow: admin, manager, or the original complaint author
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $can_comment = isAdmin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager') || (isset($_SESSION['user_id']) && isset($complaint['user_id']) && $_SESSION['user_id'] == $complaint['user_id']);
    
    if (!$can_comment) {
        $_SESSION['error_message'] = 'No tienes permiso para agregar comentarios a este reporte.';
        header("Location: view_complaint.php?id=" . $complaint_id);
        exit;
    }
    $comment_text = trim($_POST['comment']);
    
    if (!empty($comment_text) || !empty($_FILES['attachments']['name'][0])) {
        $conn->begin_transaction();
        try {
            // Insert comment
            $stmt = $conn->prepare("INSERT INTO complaint_comments (complaint_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $complaint_id, $_SESSION['user_id'], $comment_text);
            $stmt->execute();
            $comment_id = $stmt->insert_id;
            
            // Handle Attachments
            if (!empty($_FILES['attachments']['name'][0])) {
                $upload_dir = 'uploads/comments/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $count = count($_FILES['attachments']['name']);
                for ($i = 0; $i < $count; $i++) {
                    if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                        $file_name = basename($_FILES['attachments']['name'][$i]);
                        $file_type = $_FILES['attachments']['type'][$i];
                        $file_size = $_FILES['attachments']['size'][$i];
                        $unique_name = uniqid() . '_' . $file_name;
                        $target_path = $upload_dir . $unique_name;
                        
                        if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $target_path)) {
                            $stmt_att = $conn->prepare("INSERT INTO comment_attachments (comment_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                            $stmt_att->bind_param("isssi", $comment_id, $file_name, $target_path, $file_type, $file_size);
                            $stmt_att->execute();
                        }
                    }
                }
            }

            $conn->commit();
            
            // Enviar notificación por correo al autor del reporte
            // Solo si quien comenta NO es el propio autor
            if ($_SESSION['user_id'] != $complaint['user_id']) {
                try {
                    require_once 'send_email.php';
                    $commenter_name = $_SESSION['name'] ?? 'Usuario';
                    $commenter_role = $_SESSION['role'] ?? 'student';
                    $notification_result = sendCommentNotification(
                        $complaint,
                        $commenter_name,
                        $comment_text,
                        $commenter_role
                    );
                    if ($notification_result['success']) {
                        error_log("Notificación de comentario enviada para reporte #" . $complaint_id);
                    } else {
                        error_log("Error al enviar notificación de comentario: " . $notification_result['message']);
                        $_SESSION['warning_message'] = $notification_result['message'];
                        
                        // Registrar el fallo en la cola para que aparezca en el dashboard
                        $stmt_fail = $conn->prepare("INSERT INTO email_queue (complaint_id, department_id, status, attempts, max_attempts, error_message) VALUES (?, 0, 'failed', 1, 1, ?)");
                        $error_msg = $notification_result['message'];
                        $stmt_fail->bind_param("is", $complaint_id, $error_msg);
                        $stmt_fail->execute();
                    }
                } catch (Exception $email_error) {
                    // No bloquear la operación si falla el correo
                    error_log("Excepción al enviar notificación de comentario: " . $email_error->getMessage());
                    $email_err_msg = translateSmtpError($email_error->getMessage());
                    $_SESSION['warning_message'] = $email_err_msg;
                    
                    // Registrar el fallo en la cola
                    $stmt_fail = $conn->prepare("INSERT INTO email_queue (complaint_id, department_id, status, attempts, max_attempts, error_message) VALUES (?, 0, 'failed', 1, 1, ?)");
                    $stmt_fail->bind_param("is", $complaint_id, $email_err_msg);
                    $stmt_fail->execute();
                }
            }
            
            $_SESSION['success_message'] = 'Respuesta agregada exitosamente.';
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "Error al guardar el comentario: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "El comentario no puede estar vacío si no hay archivos adjuntos.";
    }
}

// Handle status update and department assignments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isAdmin() || canCloseReport())) {
    if (isset($_POST['status'])) {
        $new_status = $_POST['status'];
        
        // Si se selecciona 'unattended', calcular automáticamente si es a tiempo o tarde
        if ($new_status === 'unattended') {
            // Obtener fecha de creación del reporte
            $stmt_get = $conn->prepare("SELECT created_at FROM complaints WHERE id = ?");
            $stmt_get->bind_param("i", $complaint_id);
            $stmt_get->execute();
            $result = $stmt_get->get_result();
            $complaint_data = $result->fetch_assoc();
            
            // Calcular el estado correcto basado en días hábiles
            $new_status = determineReportStatus($complaint_data['created_at'], null);
            
            // Limpiar attended_at
            $stmt = $conn->prepare("UPDATE complaints SET status = ?, attended_at = NULL WHERE id = ?");
            $stmt->bind_param("si", $new_status, $complaint_id);
            $stmt->execute();
            $_SESSION['success_message'] = "Estado actualizado a 'Sin atender'. El sistema calculó automáticamente si está a tiempo o tarde.";
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        } elseif ($new_status === 'attended') {
            // Cuando se marca como atendido, calcular automáticamente si es a tiempo o a destiempo
            $stmt_get = $conn->prepare("SELECT created_at FROM complaints WHERE id = ?");
            $stmt_get->bind_param("i", $complaint_id);
            $stmt_get->execute();
            $result = $stmt_get->get_result();
            $complaint_data = $result->fetch_assoc();
            
            // Calcular el estado correcto basado en días hábiles (attended_ontime o attended_late)
            // Pasar la fecha actual en formato string, no timestamp
            $attended_date = date('Y-m-d H:i:s');
            $new_status = determineReportStatus($complaint_data['created_at'], $attended_date);
            
            // Actualizar el estado y la fecha de atención
            $stmt = $conn->prepare("UPDATE complaints SET status = ?, attended_at = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_status, $attended_date, $complaint_id);
            $stmt->execute();
            $_SESSION['success_message'] = "Reporte cerrado como " . ($new_status === 'attended_ontime' ? 'atendido (a tiempo)' : 'atendido (a destiempo)') . ".";
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        } else {
            $valid_statuses = ['invalid', 'duplicate'];
            if (in_array($new_status, $valid_statuses)) {
                $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $new_status, $complaint_id);
                $stmt->execute();
                $_SESSION['success_message'] = "Estado actualizado correctamente.";
                header("Location: view_complaint.php?id=" . $complaint_id);
                exit;
            }
        }
    } elseif (isset($_POST['assign_departments'])) {
        // Handle department assignments - Only admins
        if (!isAdmin()) {
            $_SESSION['error_message'] = "No tienes permiso para asignar departamentos.";
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        }
        $selected_departments = isset($_POST['departments']) ? $_POST['departments'] : [];
        
        $conn->begin_transaction();
        try {
            // Obtener departamentos actualmente asignados
            $stmt_current = $conn->prepare("SELECT department_id FROM complaint_departments WHERE complaint_id = ?");
            $stmt_current->bind_param("i", $complaint_id);
            $stmt_current->execute();
            $current_result = $stmt_current->get_result();
            $current_dept_ids = [];
            while ($row = $current_result->fetch_assoc()) {
                $current_dept_ids[] = intval($row['department_id']);
            }
            
            // Determinar departamentos NUEVOS (los que no estaban asignados antes)
            $new_dept_ids = [];
            foreach ($selected_departments as $dept_id) {
                if (is_numeric($dept_id) && !in_array(intval($dept_id), $current_dept_ids)) {
                    $new_dept_ids[] = intval($dept_id);
                }
            }
            
            // Remove all current assignments
            $stmt = $conn->prepare("DELETE FROM complaint_departments WHERE complaint_id = ?");
            $stmt->bind_param("i", $complaint_id);
            $stmt->execute();
            
            // Add new assignments and queue emails ONLY for NEW departments
            $queued_count = 0;
            
            if (!empty($selected_departments)) {
                $stmt = $conn->prepare("INSERT INTO complaint_departments (complaint_id, department_id) VALUES (?, ?)");
                $stmt_queue = $conn->prepare("INSERT INTO email_queue (complaint_id, department_id, status) VALUES (?, ?, 'pending')");
                
                foreach ($selected_departments as $dept_id) {
                    if (is_numeric($dept_id)) {
                        $dept_id = intval($dept_id);
                        
                        // Add to complaint_departments
                        $stmt->bind_param("ii", $complaint_id, $dept_id);
                        $stmt->execute();
                        
                        // Queue email ONLY for departments that are NEW and not already in queue
                        if (in_array($dept_id, $new_dept_ids)) {
                            // Verificar que no exista ya en la cola
                            $stmt_queue_check = $conn->prepare("SELECT 1 FROM email_queue WHERE complaint_id = ? AND department_id = ? AND status IN ('pending', 'sent')");
                            $stmt_queue_check->bind_param("ii", $complaint_id, $dept_id);
                            $stmt_queue_check->execute();
                            $queue_exists = $stmt_queue_check->get_result()->num_rows > 0;
                            $stmt_queue_check->close();
                            
                            if (!$queue_exists) {
                                $stmt_queue->bind_param("ii", $complaint_id, $dept_id);
                                $stmt_queue->execute();
                                $queued_count++;
                            }
                        }
                    }
                }
            }
            
            $conn->commit();
            
            // Prepare success message
            $success_msg = "Departamentos asignados correctamente.";
            $is_test_mode = function_exists('isTestMode') && isTestMode();
            if ($is_test_mode) {
                $test_email_dest = function_exists('getTestEmail') ? getTestEmail() : SMTP_USERNAME;
                $success_msg .= " Modo de prueba activado: los correos se enviarán a " . $test_email_dest . " en lugar de los departamentos.";
            } else {
                $success_msg .= " Los correos de notificación se enviarán en segundo plano.";
            }
            
            // Trigger background email processing
            if ($queued_count > 0) {
                // Try to trigger async processing (non-blocking)
                $this_file = $_SERVER['PHP_SELF'];
                $process_url = str_replace(basename($this_file), 'process_email_queue.php', $this_file);
                
                // Attempt async call using file_get_contents with timeout
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => rtrim(APP_URL, '/') . '/process_email_queue.php',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT_MS => 100,
                        CURLOPT_CONNECTTIMEOUT_MS => 100,
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }
            }
            
            $_SESSION['success_message'] = $success_msg;
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "Error al asignar departamentos: " . $e->getMessage();
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        }
    } elseif (isset($_POST['update_category'])) {
        // Handle category update - Only admins
        if (!isAdmin()) {
            $_SESSION['error_message'] = "No tienes permiso para actualizar la categoría.";
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        }
        $new_category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? intval($_POST['category_id']) : null;
        
        try {
            $stmt = $conn->prepare("UPDATE complaints SET category_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_category_id, $complaint_id);
            $stmt->execute();
            
            // Get the new category name for the success message
            $category_name = 'Sin categoría';
            if ($new_category_id) {
                $stmt_cat = $conn->prepare("SELECT name FROM categories WHERE id = ?");
                $stmt_cat->bind_param("i", $new_category_id);
                $stmt_cat->execute();
                $cat_result = $stmt_cat->get_result()->fetch_assoc();
                if ($cat_result) {
                    $category_name = $cat_result['name'];
                }
            }
            
            $_SESSION['success_message'] = "Categoría actualizada a: " . htmlspecialchars($category_name);
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error al actualizar la categoría: " . $e->getMessage();
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        }
    } elseif (isset($_POST['apply_gemini_suggestions'])) {
        // Handle applying Gemini suggestions - Only admins
        if (!isAdmin()) {
            $_SESSION['error_message'] = "No tienes permiso para aplicar sugerencias de Gemini.";
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        }
        
        $accion = isset($_POST['gemini_accion']) ? $_POST['gemini_accion'] : 'procesar';
        $categoria_id = isset($_POST['gemini_categoria_id']) ? intval($_POST['gemini_categoria_id']) : null;
        $departamentos_json = isset($_POST['gemini_departamentos']) ? $_POST['gemini_departamentos'] : '[]';
        $motivo_cierre = isset($_POST['gemini_motivo_cierre']) ? $_POST['gemini_motivo_cierre'] : null;
        $duplicado_de = isset($_POST['gemini_duplicado_de']) ? intval($_POST['gemini_duplicado_de']) : null;
        
        $conn->begin_transaction();
        try {
            if ($accion === 'invalido') {
                // Marcar como inválido
                $stmt = $conn->prepare("UPDATE complaints SET status = 'invalid', attended_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $complaint_id);
                $stmt->execute();
                
                // Agregar comentario del sistema
                $admin_id = $_SESSION['user_id'];
                $comentario = "⚠️ Reporte marcado como INVÁLIDO por análisis de IA.";
                if ($motivo_cierre) {
                    $comentario .= "\n\nMotivo: " . $motivo_cierre;
                }
                $stmt_comment = $conn->prepare("INSERT INTO complaint_comments (complaint_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())");
                $stmt_comment->bind_param("iis", $complaint_id, $admin_id, $comentario);
                $stmt_comment->execute();
                
                $conn->commit();
                $_SESSION['success_message'] = "Reporte marcado como inválido correctamente.";
                header("Location: view_complaint.php?id=" . $complaint_id);
                exit;
                
            } elseif ($accion === 'duplicado') {
                // Marcar como duplicado
                $stmt = $conn->prepare("UPDATE complaints SET status = 'duplicate', attended_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $complaint_id);
                $stmt->execute();
                
                // Buscar folio del reporte original
                $folio_duplicado = '';
                if ($duplicado_de > 0) {
                    $stmt_folio = $conn->prepare("SELECT folio FROM complaints WHERE id = ?");
                    $stmt_folio->bind_param("i", $duplicado_de);
                    $stmt_folio->execute();
                    $folio_result = $stmt_folio->get_result()->fetch_assoc();
                    $folio_duplicado = $folio_result ? ('#' . ($folio_result['folio'] ?? str_pad($duplicado_de, 6, '0', STR_PAD_LEFT))) : "ID:{$duplicado_de}";
                    $stmt_folio->close();
                }
                
                // Agregar comentario del sistema
                $admin_id = $_SESSION['user_id'];
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
                
                $conn->commit();
                $_SESSION['success_message'] = "Reporte marcado como duplicado correctamente.";
                header("Location: view_complaint.php?id=" . $complaint_id);
                exit;
                
            } else {
                // Acción normal: procesar (asignar categoría y departamentos)
                
                // 1. Update category if provided
                if ($categoria_id && $categoria_id > 0) {
                    $stmt = $conn->prepare("UPDATE complaints SET category_id = ? WHERE id = ?");
                    $stmt->bind_param("ii", $categoria_id, $complaint_id);
                    $stmt->execute();
                }
                
                // 2. Update departments (remove all and add new ones)
                $departamentos_data = json_decode($departamentos_json, true);
                $department_ids = [];
                
                if (is_array($departamentos_data) && !empty($departamentos_data)) {
                    // Extract department IDs from the data
                    foreach ($departamentos_data as $dept) {
                        if (isset($dept['id']) && is_numeric($dept['id'])) {
                            $department_ids[] = intval($dept['id']);
                        }
                    }
                }
                
                // Remove all current department assignments
                $stmt = $conn->prepare("DELETE FROM complaint_departments WHERE complaint_id = ?");
                $stmt->bind_param("i", $complaint_id);
                $stmt->execute();
                
                // Add new department assignments and queue emails for background processing
                $queued_count = 0;
                
                if (!empty($department_ids)) {
                    $stmt = $conn->prepare("INSERT INTO complaint_departments (complaint_id, department_id) VALUES (?, ?)");
                    $stmt_queue = $conn->prepare("INSERT INTO email_queue (complaint_id, department_id, status) VALUES (?, ?, 'pending')");
                    
                    foreach ($department_ids as $dept_id) {
                        $stmt->bind_param("ii", $complaint_id, $dept_id);
                        $stmt->execute();
                        
                        // Queue email for background processing
                        $stmt_queue->bind_param("ii", $complaint_id, $dept_id);
                        $stmt_queue->execute();
                        $queued_count++;
                    }
                }
                
                $conn->commit();
                
                // Prepare success message
                $success_msg = "Sugerencias de IA aplicadas correctamente. ";
                if ($categoria_id && $categoria_id > 0) {
                    $success_msg .= "Categoría actualizada. ";
                }
                if (!empty($department_ids)) {
                    $success_msg .= "Departamentos asignados.";
                    $is_test_mode = function_exists('isTestMode') && isTestMode();
                    if ($is_test_mode) {
                        $test_email_dest = function_exists('getTestEmail') ? getTestEmail() : SMTP_USERNAME;
                        $success_msg .= " Modo de prueba activado: los correos se enviarán a " . $test_email_dest . " en lugar de los departamentos.";
                    } else {
                        $success_msg .= " Los correos de notificación se enviarán en segundo plano.";
                    }
                }
                
                // Trigger background email processing
                if ($queued_count > 0) {
                    // Try to trigger async processing (non-blocking)
                    $this_file = $_SERVER['PHP_SELF'];
                    $process_url = str_replace(basename($this_file), 'process_email_queue.php', $this_file);
                    
                    // Attempt async call using file_get_contents with timeout
                    if (function_exists('curl_init')) {
                        $ch = curl_init();
                        curl_setopt_array($ch, [
                            CURLOPT_URL => rtrim(APP_URL, '/') . '/process_email_queue.php',
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT_MS => 100,
                            CURLOPT_CONNECTTIMEOUT_MS => 100,
                        ]);
                        curl_exec($ch);
                        curl_close($ch);
                    }
                }
                
                $_SESSION['success_message'] = $success_msg;
                header("Location: view_complaint.php?id=" . $complaint_id);
                exit;
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "Error al aplicar sugerencias de IA: " . $e->getMessage();
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        }
    }
}

// Complaint details already loaded at the top of the file

$stmt_att = $conn->prepare("SELECT * FROM attachments WHERE complaint_id = ?");
$stmt_att->bind_param("i", $complaint_id);
$stmt_att->execute();
$attachments = $stmt_att->get_result()->fetch_all(MYSQLI_ASSOC);

// Get comments with attachments
$stmt_comments = $conn->prepare("
    SELECT cc.*, u.name as user_name, u.role as user_role, u.profile_photo as user_profile_photo, c.is_anonymous
    FROM complaint_comments cc 
    LEFT JOIN users u ON cc.user_id = u.id 
    LEFT JOIN complaints c ON cc.complaint_id = c.id
    WHERE cc.complaint_id = ? 
    ORDER BY cc.created_at ASC
");
$stmt_comments->bind_param("i", $complaint_id);
$stmt_comments->execute();
$comments = $stmt_comments->get_result()->fetch_all(MYSQLI_ASSOC);

// Get attachments for each comment
foreach ($comments as $key => $comment) {
    $stmt_att = $conn->prepare("SELECT * FROM comment_attachments WHERE comment_id = ?");
    $stmt_att->bind_param("i", $comment['id']);
    $stmt_att->execute();
    $comments[$key]['attachments'] = $stmt_att->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Recuperar mensajes de sesión
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;
$warning = isset($_SESSION['warning_message']) ? $_SESSION['warning_message'] : null;
$gemini_result_data = isset($_SESSION['gemini_result_data']) ? $_SESSION['gemini_result_data'] : null;
$gemini_result_raw = isset($_SESSION['gemini_result_raw']) ? $_SESSION['gemini_result_raw'] : null;

// Limpiar mensajes de sesión después de recuperarlos
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
unset($_SESSION['warning_message']);
unset($_SESSION['gemini_result_data']);
unset($_SESSION['gemini_result_raw']);

// Correos enviados (para toast)
$emails_sent_count = isset($_SESSION['emails_sent']) ? intval($_SESSION['emails_sent']) : 0;
unset($_SESSION['emails_sent']);

// Usar la función del helper para obtener información del estado
function getStatusInfo($status) {
    return getStatusDisplayInfo($status);
}

function getFileIcon($file_type) {
    if (str_contains($file_type, 'image')) return 'ph-image';
    if (str_contains($file_type, 'pdf')) return 'ph-file-pdf';
    if (str_contains($file_type, 'word')) return 'ph-file-doc';
    return 'ph-file';
}

// AHORA sí incluir el header después de todo el procesamiento
$page_title = 'Detalles del Reporte - Buzón de Quejas';
$show_global_blobs = false; // Disable global header blobs to use institutional background
include 'components/header.php';
?>

</style>

<!-- Background Image (Institutional) -->
<div class="fixed inset-0 overflow-hidden pointer-events-none -z-10 bg-slate-50 dark:bg-transparent transition-colors duration-500">
    <div class="fixed inset-0 bg-institutional">
        <div class="absolute inset-0 bg-gradient-to-b from-slate-50/70 via-transparent to-slate-50/70 dark:from-slate-900/80 dark:via-transparent dark:to-slate-900/80"></div>
    </div>
</div>

<div class="bg-transparent min-h-screen" 
     x-data="{ 
         isModalOpen: false, 
         modalImageUrl: '', 
         isAdminPanelOpen: false,
         activeTab: 'departments',
         isUploadModalOpen: false,
         adminModalMode: 'full'
     }" 
     @keydown.escape.window="isModalOpen = false; isAdminPanelOpen = false; isUploadModalOpen = false; adminModalMode = 'full'">
    
    <!-- Image Preview Modal -->
    <div x-show="isModalOpen" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" 
         style="display: none;">
        <div @click="isModalOpen = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-4xl max-h-full">
            <img :src="modalImageUrl" alt="Vista previa de la evidencia" class="w-full h-auto object-contain rounded-lg shadow-2xl" style="max-height: 90vh;">
            <button @click="isModalOpen = false" class="absolute -top-4 -right-4 w-10 h-10 bg-white text-gray-700 rounded-full flex items-center justify-center shadow-lg hover:bg-gray-200 transition">
                <i class="ph-x text-2xl"></i>
            </button>
        </div>
    </div>

    <!-- Upload Evidence Modal -->
    <div x-show="isUploadModalOpen" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" 
         style="display: none;">
        <div @click="isUploadModalOpen = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative bg-white w-full max-w-2xl rounded-xl shadow-2xl" @click.stop>
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between rounded-t-xl">
                <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="ph-upload-simple text-blue-600"></i>
                    Agregar Evidencia de Respuesta
                </h3>
                <button @click="isUploadModalOpen = false" class="text-gray-400 hover:text-gray-500">
                    <i class="ph-x text-2xl"></i>
                </button>
            </div>

            <div class="p-6" x-data="{ 
                isDragging: false, 
                hasFiles: false,
                fileCount: 0,
                previews: [],
                updateFileCount() {
                    const input = document.getElementById('response_evidence_input_modal');
                    this.fileCount = input.files.length;
                    this.hasFiles = this.fileCount > 0;
                    this.generatePreviews(input.files);
                },
                generatePreviews(files) {
                    this.previews = [];
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = (e) => {
                                this.previews.push(e.target.result);
                            };
                            reader.readAsDataURL(file);
                        }
                    }
                }
            }">
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <!-- Drag & Drop Zone -->
                    <div @drop.prevent="isDragging = false; $refs.fileInputModal.files = $event.dataTransfer.files; updateFileCount()"
                         @dragover.prevent="isDragging = true"
                         @dragleave.prevent="isDragging = false"
                         @click="$refs.fileInputModal.click()"
                         :class="{'border-blue-500 bg-blue-50': isDragging, 'border-gray-300 bg-white': !isDragging}"
                         class="relative border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-all hover:border-blue-400 hover:bg-blue-50">
                        
                        <input type="file" 
                               id="response_evidence_input_modal"
                               name="response_evidence[]" 
                               multiple 
                               accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                               @change="updateFileCount()"
                               x-ref="fileInputModal"
                               class="hidden">
                        
                        <div class="flex flex-col items-center gap-3">
                            <div x-show="previews.length === 0" class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="ph-upload-simple text-3xl text-blue-600"></i>
                            </div>
                            
                            <!-- Image Previews -->
                            <div x-show="previews.length > 0" class="grid grid-cols-3 gap-2 w-full max-w-md">
                                <template x-for="(preview, index) in previews" :key="index">
                                    <div class="relative aspect-square rounded-lg overflow-hidden border-2 border-blue-200">
                                        <img :src="preview" class="w-full h-full object-cover">
                                    </div>
                                </template>
                            </div>
                            
                            <div>
                                <p class="text-base font-semibold text-gray-700 mb-1">
                                    <span x-show="!isDragging && !hasFiles">Arrastra archivos aquí o haz clic para seleccionar</span>
                                    <span x-show="isDragging" class="text-blue-600">Suelta los archivos aquí</span>
                                    <span x-show="hasFiles && !isDragging" class="text-green-600">
                                        <i class="ph-check-circle mr-1"></i>
                                        <span x-text="fileCount"></span> archivo<span x-show="fileCount > 1">s</span> listo<span x-show="fileCount > 1">s</span> para subir
                                    </span>
                                </p>
                                <p class="text-sm text-gray-500">Imágenes, PDF, Word, Excel</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-sm text-blue-700 flex items-center gap-2">
                            <i class="ph-info"></i>
                            Agregar más evidencia al reporte
                        </p>
                        <button type="submit" 
                                :disabled="!hasFiles"
                                :class="hasFiles ? 'bg-blue-600 hover:bg-blue-700 cursor-pointer' : 'bg-gray-300 cursor-not-allowed'"
                                class="inline-flex items-center px-6 py-2.5 text-white font-semibold rounded-lg transition-colors shadow-md disabled:opacity-50">
                            <i class="ph-upload-simple text-lg mr-2"></i>
                            Subir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Asignar Departamentos -->
    <div x-show="isAdminPanelOpen && adminModalMode === 'departments'" x-cloak 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" 
         style="display: none;">
        <div @click="isAdminPanelOpen = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative bg-white dark:bg-slate-900 w-full max-w-2xl max-h-[90vh] rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-white/20 dark:border-slate-700">
            <div class="sticky top-0 bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl border-b border-gray-200 dark:border-slate-700/80 px-6 py-4 flex items-center justify-between z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow-md">
                        <i class="ph-buildings text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white">Asignar Departamentos</h3>
                </div>
                <button type="button" @click="isAdminPanelOpen = false" class="w-8 h-8 rounded-full bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-slate-700 hover:text-gray-700 dark:hover:text-white transition-all">
                    <i class="ph-x text-lg"></i>
                </button>
            </div>

            <form method="POST" class="flex flex-col flex-1 min-h-0 bg-gray-50/30 dark:bg-slate-800/20" x-data="{ searchQuery: '' }">
                <div class="p-6 overflow-y-auto flex-1">
                    <?php
                    // Get all departments for admin form (excluding hidden ones), sorted with assigned ones at the top
                    $stmt_all = $conn->prepare("
                        SELECT d.*, 
                               EXISTS(SELECT 1 FROM complaint_departments cd WHERE cd.department_id = d.id AND cd.complaint_id = ?) as is_assigned
                        FROM departments d 
                        WHERE is_hidden = 0 
                        ORDER BY is_assigned DESC, d.name ASC
                    ");
                    $stmt_all->bind_param("i", $complaint_id);
                    $stmt_all->execute();
                    $all_departments = $stmt_all->get_result();
                    
                    // Populate assigned_dept_ids for the checkbox state management below
                    $assigned_dept_ids = [];
                    while ($dept_row = $all_departments->fetch_assoc()) {
                        if ($dept_row['is_assigned']) {
                            $assigned_dept_ids[] = $dept_row['id'];
                        }
                        $all_departments_data[] = $dept_row;
                    }
                    ?>
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Selecciona los departamentos responsables:</label>
                            
                            <!-- Search Bar -->
                            <div class="relative mb-6">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="ph-magnifying-glass text-gray-400 dark:text-gray-500 text-lg"></i>
                                </div>
                                <input type="text" 
                                       x-model="searchQuery"
                                       placeholder="Buscar por nombre, encargado o email..."
                                       class="w-full rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-800 dark:text-white shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:focus:border-blue-400 outline-none transition-all p-3.5 pl-11">
                            </div>
                            
                            <div class="grid gap-3">
                                <?php foreach ($all_departments_data as $dept): 
                                    $isChecked = in_array($dept['id'], $assigned_dept_ids);
                                ?>
                                    <label x-show="searchQuery === '' || 
                                                   '<?php echo strtolower(htmlspecialchars($dept['name'])); ?>'.includes(searchQuery.toLowerCase()) || 
                                                   '<?php echo strtolower(htmlspecialchars($dept['manager'])); ?>'.includes(searchQuery.toLowerCase()) || 
                                                   '<?php echo strtolower(htmlspecialchars($dept['email'])); ?>'.includes(searchQuery.toLowerCase())"
                                           class="group relative flex items-start gap-4 p-4 rounded-xl border-2 transition-all cursor-pointer bg-white dark:bg-slate-800/80 hover:shadow-md <?php echo $isChecked ? 'border-blue-500 dark:border-blue-400 ring-1 ring-blue-500 dark:ring-blue-400' : 'border-transparent hover:border-blue-300 dark:hover:border-blue-600 border-gray-100 dark:border-slate-700/50'; ?>">
                                        <div class="flex items-center h-5 mt-1">
                                            <input type="checkbox" 
                                                   name="departments[]" 
                                                   value="<?php echo $dept['id']; ?>"
                                                   <?php echo $isChecked ? 'checked' : ''; ?>
                                                   class="h-5 w-5 rounded border-gray-300 dark:border-slate-500 text-blue-600 dark:text-blue-500 focus:ring-blue-500 bg-white dark:bg-slate-700 transition-colors">
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-bold text-gray-900 dark:text-white text-base <?php echo $isChecked ? 'text-blue-800 dark:text-blue-300' : ''; ?>"><?php echo htmlspecialchars($dept['name']); ?></p>
                                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1 flex flex-wrap items-center gap-1.5">
                                                <span class="font-medium flex items-center gap-1"><i class="ph-user"></i> <?php echo htmlspecialchars($dept['manager']); ?></span>
                                                <span class="hidden md:inline text-gray-300 dark:text-gray-600">•</span>
                                                <a href="mailto:<?php echo htmlspecialchars($dept['email']); ?>" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors flex items-center gap-1" @click.stop>
                                                    <i class="ph-envelope-simple"></i> <?php echo htmlspecialchars($dept['email']); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sticky Button at Bottom -->
                <div class="sticky bottom-0 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-gray-200 dark:border-slate-700/80 p-5 flex flex-shrink-0 justify-end gap-3 rounded-b-2xl z-20">
                    <button type="button" @click="isAdminPanelOpen = false" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit" 
                            name="assign_departments" 
                            class="inline-flex items-center gap-2 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white font-semibold py-2.5 px-6 rounded-lg transition-all shadow-md hover:shadow-lg active:scale-95">
                        <i class="ph-floppy-disk text-lg"></i>
                        Guardar Asignaciones
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Cambiar Estado del Reporte -->
    <div x-show="isAdminPanelOpen && adminModalMode === 'status'" x-cloak 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" 
         style="display: none;">
        <div @click="isAdminPanelOpen = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative bg-white w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-xl shadow-2xl">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-800">Cambiar Estado del Reporte</h3>
                <button @click="isAdminPanelOpen = false" class="text-gray-400 hover:text-gray-500">
                    <i class="ph-x text-2xl"></i>
                </button>
            </div>

            <div class="p-6">
                <!-- Status Form -->
                <div class="space-y-4">
                    <!-- Nota informativa -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <i class="ph-info text-blue-600 text-xl flex-shrink-0 mt-0.5"></i>
                            <div>
                                <h4 class="text-sm font-semibold text-blue-900 mb-1">Actualización Manual de Estado</h4>
                                <p class="text-sm text-blue-700">
                                    Este apartado permite actualizar manualmente el estado del reporte en caso de que exista alguna discrepancia con el cálculo automático de días hábiles. 
                                    El sistema calcula automáticamente los estados basándose en días hábiles (lunes a viernes), pero puedes ajustarlo manualmente desde aquí si es necesario.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" class="space-y-4">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Estado actual del reporte:</label>
                            <select name="status" id="status" class="w-full rounded-lg border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition p-2.5">
                                <option value="unattended" <?php echo (in_array($complaint['status'], ['unattended_ontime', 'unattended_late'])) ? 'selected' : ''; ?>>Sin atender</option>
                                <option value="attended_ontime" <?php echo $complaint['status'] == 'attended_ontime' ? 'selected' : ''; ?>>Atendido (a tiempo)</option>
                                <option value="attended_late" <?php echo $complaint['status'] == 'attended_late' ? 'selected' : ''; ?>>Atendido (a destiempo)</option>
                                <option value="invalid" <?php echo $complaint['status'] == 'invalid' ? 'selected' : ''; ?>>Inválido</option>
                                <option value="duplicate" <?php echo $complaint['status'] == 'duplicate' ? 'selected' : ''; ?>>Duplicado</option>
                            </select>
                            <p class="mt-2 text-xs text-gray-500">
                                <i class="ph-info mr-1"></i>
                                Al seleccionar "Sin atender", el sistema calculará automáticamente si está a tiempo o tarde según los días hábiles transcurridos.
                            </p>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white font-semibold py-2.5 px-6 rounded-lg hover:bg-blue-700 transition-colors shadow">
                            Actualizar Estado
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Cerrar Reporte -->
    <div x-show="isAdminPanelOpen && adminModalMode === 'close'" x-cloak 
         @click.away="isAdminPanelOpen = false;"
         @keydown.escape.window="isAdminPanelOpen = false;"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" 
         style="display: none;">
        <div @click="isAdminPanelOpen = false;" class="fixed inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative bg-white w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-xl shadow-2xl" @click.stop>
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-800">Cerrar Reporte</h3>
                <button @click="isAdminPanelOpen = false;" class="text-gray-400 hover:text-gray-500">
                    <i class="ph-x text-2xl"></i>
                </button>
            </div>

            <div class="p-6">
                <!-- Advertencia -->
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <i class="ph-warning text-amber-600 text-xl flex-shrink-0 mt-0.5"></i>
                        <div>
                            <h4 class="text-sm font-semibold text-amber-900 mb-1">Importante</h4>
                            <p class="text-sm text-amber-700">
                                Asegúrate de haber respondido completamente al reporte antes de cerrarlo. Una vez cerrado, no se podrán agregar más comentarios a este reporte.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Close Form -->
                <form method="POST" class="space-y-6" x-data="{ selectedCloseStatus: '' }">
                    <input type="hidden" name="status" :value="selectedCloseStatus">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-4">
                            Cerrar como
                        </label>
                        
                        <!-- Radio Buttons Grid -->
                        <div class="grid grid-cols-3 gap-4">
                            <!-- Option 1: Atendido -->
                            <label class="cursor-pointer" @click="selectedCloseStatus === 'attended' ? selectedCloseStatus = '' : selectedCloseStatus = 'attended'">
                                <div :class="selectedCloseStatus === 'attended' ? 'border-green-500 bg-green-50 ring-2 ring-green-500' : 'border-gray-200 bg-white hover:border-green-300'" class="border-2 rounded-xl p-4 transition-all h-full flex flex-col">
                                    <div class="flex items-center justify-center mb-3 flex-shrink-0">
                                        <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-emerald-500 rounded-lg flex items-center justify-center">
                                            <i class="ph-check-circle text-white text-2xl"></i>
                                        </div>
                                    </div>
                                    <h3 class="font-semibold text-gray-800 text-center mb-2 flex-shrink-0">Atendido</h3>
                                    <p class="text-xs text-gray-600 text-center flex-shrink-0">Se le dio seguimiento y se llegó a una solución</p>
                                </div>
                            </label>

                            <!-- Option 2: Duplicado -->
                            <label class="cursor-pointer" @click="selectedCloseStatus === 'duplicate' ? selectedCloseStatus = '' : selectedCloseStatus = 'duplicate'">
                                <div :class="selectedCloseStatus === 'duplicate' ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-500' : 'border-gray-200 bg-white hover:border-blue-300'" class="border-2 rounded-xl p-4 transition-all h-full flex flex-col">
                                    <div class="flex items-center justify-center mb-3 flex-shrink-0">
                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-indigo-500 rounded-lg flex items-center justify-center">
                                            <i class="ph-copy text-white text-2xl"></i>
                                        </div>
                                    </div>
                                    <h3 class="font-semibold text-gray-800 text-center mb-2 flex-shrink-0">Duplicado</h3>
                                    <p class="text-xs text-gray-600 text-center flex-shrink-0">Es un duplicado de otro reporte existente</p>
                                </div>
                            </label>

                            <!-- Option 3: Inválido -->
                            <label class="cursor-pointer" @click="selectedCloseStatus === 'invalid' ? selectedCloseStatus = '' : selectedCloseStatus = 'invalid'">
                                <div :class="selectedCloseStatus === 'invalid' ? 'border-red-500 bg-red-50 ring-2 ring-red-500' : 'border-gray-200 bg-white hover:border-red-300'" class="border-2 rounded-xl p-4 transition-all h-full flex flex-col">
                                    <div class="flex items-center justify-center mb-3 flex-shrink-0">
                                        <div class="w-12 h-12 bg-gradient-to-br from-red-400 to-rose-500 rounded-lg flex items-center justify-center">
                                            <i class="ph-prohibit text-white text-2xl"></i>
                                        </div>
                                    </div>
                                    <h3 class="font-semibold text-gray-800 text-center mb-2 flex-shrink-0">Inválido</h3>
                                    <p class="text-xs text-gray-600 text-center flex-shrink-0">No cumple con los requisitos o contiene información inválida</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <button type="submit" 
                            :disabled="!selectedCloseStatus"
                            :class="selectedCloseStatus ? 'bg-blue-600 hover:bg-blue-700 cursor-pointer' : 'bg-gray-300 cursor-not-allowed'"
                            class="w-full text-white font-semibold py-2.5 px-6 rounded-lg transition-colors shadow disabled:opacity-50">
                        Cerrar Reporte
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Editar Categoría -->
    <div x-show="isAdminPanelOpen && adminModalMode === 'category'" x-cloak 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" 
         style="display: none;">
        <div @click="isAdminPanelOpen = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative bg-white dark:bg-slate-900 w-full max-w-2xl max-h-[90vh] rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-white/20 dark:border-slate-700">
            <div class="sticky top-0 bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl border-b border-gray-200 dark:border-slate-700/80 px-6 py-4 flex items-center justify-between z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow-md">
                        <i class="ph-tag text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white">Editar Categoría</h3>
                </div>
                <button @click="isAdminPanelOpen = false" class="w-8 h-8 rounded-full bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-slate-700 hover:text-gray-700 dark:hover:text-white transition-all">
                    <i class="ph-x text-lg"></i>
                </button>
            </div>

            <form method="POST" class="flex flex-col flex-1 min-h-0 bg-gray-50/30 dark:bg-slate-800/20">
                <div class="p-6 overflow-y-auto flex-1" x-data="{ searchQuery: '' }">
                    <?php
                    // Get all categories for admin form
                    $all_categories = $conn->query("SELECT id, name, description FROM categories ORDER BY name");
                    $current_category_id = $complaint['category_id'] ?? null;
                    
                    // Category info with icons and colors (same as index.php)
                    $category_info = [
                        0  => ['from' => 'from-gray-500',    'to' => 'to-slate-500',   'icon' => 'ph-file-text'],
                        1  => ['from' => 'from-blue-500',    'to' => 'to-cyan-500',    'icon' => 'ph-wifi-high'],
                        2  => ['from' => 'from-indigo-500',  'to' => 'to-purple-500',  'icon' => 'ph-armchair'],
                        3  => ['from' => 'from-emerald-500', 'to' => 'to-teal-500',    'icon' => 'ph-books'],
                        4  => ['from' => 'from-amber-500',   'to' => 'to-orange-500',  'icon' => 'ph-flask'],
                        5  => ['from' => 'from-green-500',   'to' => 'to-emerald-600', 'icon' => 'ph-basketball'],
                        6  => ['from' => 'from-amber-500',   'to' => 'to-orange-600',  'icon' => 'ph-fork-knife'],
                        7  => ['from' => 'from-sky-500',     'to' => 'to-blue-500',    'icon' => 'ph-toilet'],
                        8  => ['from' => 'from-zinc-500',    'to' => 'to-slate-600',   'icon' => 'ph-car'],
                        9  => ['from' => 'from-fuchsia-500', 'to' => 'to-purple-600',  'icon' => 'ph-chalkboard-teacher'],
                        10 => ['from' => 'from-indigo-500',  'to' => 'to-blue-600',    'icon' => 'ph-book-open'],
                        11 => ['from' => 'from-yellow-500',  'to' => 'to-amber-600',   'icon' => 'ph-exam'],
                        12 => ['from' => 'from-blue-500',    'to' => 'to-indigo-600',  'icon' => 'ph-folders'],
                        13 => ['from' => 'from-emerald-500', 'to' => 'to-teal-600',    'icon' => 'ph-handshake'],
                        14 => ['from' => 'from-rose-500',    'to' => 'to-pink-600',    'icon' => 'ph-credit-card'],
                        15 => ['from' => 'from-sky-500',     'to' => 'to-cyan-600',    'icon' => 'ph-headset'],
                        16 => ['from' => 'from-violet-500',  'to' => 'to-purple-600',  'icon' => 'ph-megaphone'],
                        17 => ['from' => 'from-red-600',     'to' => 'to-rose-700',    'icon' => 'ph-prohibit'],
                        18 => ['from' => 'from-red-500',     'to' => 'to-orange-600',  'icon' => 'ph-warning'],
                        19 => ['from' => 'from-green-600',   'to' => 'to-emerald-700', 'icon' => 'ph-shield-check'],
                        20 => ['from' => 'from-pink-500',    'to' => 'to-fuchsia-600', 'icon' => 'ph-target'],
                    ];
                    $default_info = ['from' => 'from-gray-500', 'to' => 'to-slate-500', 'icon' => 'ph-file-text'];
                    ?>
                    
                    <!-- Search Input -->
                    <div class="relative mb-6">
                        <input type="text" 
                               x-model="searchQuery" 
                               placeholder="Buscar categoría..." 
                               class="w-full rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:focus:border-indigo-400 outline-none transition-all p-3.5 pl-11">
                        <i class="ph-magnifying-glass absolute left-4 top-4 text-gray-400 dark:text-gray-500 text-lg"></i>
                    </div>

                    <!-- Categories List (scrollable) -->
                    <div class="grid grid-cols-1 gap-3">
                        <!-- No Category Option -->
                        <label class="group relative flex items-start gap-3 p-4 rounded-xl border-2 transition-all cursor-pointer bg-white dark:bg-slate-800/80 hover:shadow-md <?php echo $current_category_id == null ? 'border-indigo-500 dark:border-indigo-400 ring-1 ring-indigo-500 dark:ring-indigo-400' : 'border-transparent hover:border-indigo-300 dark:hover:border-indigo-600 border-gray-100 dark:border-slate-700/50'; ?>">
                            <input type="radio" name="category_id" value="" <?php echo $current_category_id == null ? 'checked' : ''; ?> class="mt-1 w-4 h-4 text-indigo-600 dark:text-indigo-500 border-gray-300 dark:border-slate-500 focus:ring-indigo-500">
                            <div class="flex items-start gap-3 flex-1 min-w-0">
                                <div class="w-10 h-10 bg-gradient-to-br from-gray-400 to-slate-500 rounded-lg flex items-center justify-center flex-shrink-0 shadow-sm group-hover:scale-105 transition-transform">
                                    <i class="ph-file-text ph-fill text-white text-lg"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-gray-900 dark:text-white text-sm <?php echo $current_category_id == null ? 'text-indigo-800 dark:text-indigo-300' : ''; ?>">Sin categoría</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">No asignar categoría</p>
                                </div>
                            </div>
                        </label>

                        <?php 
                        $all_categories->data_seek(0);
                        while ($cat = $all_categories->fetch_assoc()): 
                            $cat_id = $cat['id'];
                            $info = $category_info[$cat_id] ?? $default_info;
                            $cat_name = htmlspecialchars($cat['name']);
                            $cat_desc = htmlspecialchars($cat['description'] ?? '');
                            $isChecked = ($current_category_id == $cat_id);
                        ?>
                            <label class="group relative flex items-start gap-3 p-4 rounded-xl border-2 transition-all cursor-pointer bg-white dark:bg-slate-800/80 hover:shadow-md <?php echo $isChecked ? 'border-indigo-500 dark:border-indigo-400 ring-1 ring-indigo-500 dark:ring-indigo-400' : 'border-transparent hover:border-indigo-300 dark:hover:border-indigo-600 border-gray-100 dark:border-slate-700/50'; ?>"
                                   x-show="searchQuery === '' || '<?php echo strtolower($cat_name); ?>'.includes(searchQuery.toLowerCase()) || '<?php echo strtolower($cat_desc); ?>'.includes(searchQuery.toLowerCase())">
                                <input type="radio" name="category_id" value="<?php echo $cat_id; ?>" <?php echo $isChecked ? 'checked' : ''; ?> class="mt-1 w-4 h-4 text-indigo-600 dark:text-indigo-500 border-gray-300 dark:border-slate-500 focus:ring-indigo-500">
                                <div class="flex items-start gap-3 flex-1 min-w-0">
                                    <div class="w-10 h-10 bg-gradient-to-br <?php echo $info['from'] . ' ' . $info['to']; ?> rounded-lg flex items-center justify-center flex-shrink-0 shadow-sm group-hover:scale-105 transition-transform">
                                        <i class="<?php echo $info['icon']; ?> ph-fill text-white text-lg"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-gray-900 dark:text-white text-sm truncate <?php echo $isChecked ? 'text-indigo-800 dark:text-indigo-300' : ''; ?>"><?php echo $cat_name; ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2" title="<?php echo $cat_desc; ?>"><?php echo $cat_desc; ?></p>
                                    </div>
                                </div>
                            </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Sticky Button at Bottom -->
                <div class="sticky bottom-0 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-gray-200 dark:border-slate-700/80 p-5 flex flex-shrink-0 justify-end gap-3 rounded-b-2xl z-20">
                    <button type="button" @click="isAdminPanelOpen = false" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit" 
                            name="update_category"
                            class="inline-flex items-center gap-2 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white font-semibold py-2.5 px-6 rounded-lg transition-all shadow-md hover:shadow-lg active:scale-95">
                        <i class="ph-floppy-disk text-lg"></i>
                        Actualizar Categoría
                    </button>
                </div>
            </form>
        </div>
    </div>

    <main class="container mx-auto px-4 py-12">
        <div class="max-w-4xl mx-auto">
            
            <!-- Mensajes de éxito/error globales -->
            <?php if ($success_message): ?>
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-lg flex items-center gap-3 shadow-md">
                    <i class="ph-check-circle text-2xl"></i>
                    <span class="font-medium"><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-lg flex items-center gap-3 shadow-md">
                    <i class="ph-warning-circle text-2xl"></i>
                    <span class="font-medium"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($warning): ?>
                <div class="mb-6 bg-amber-50 border border-amber-400 text-amber-800 px-6 py-4 rounded-lg flex items-center gap-3 shadow-md">
                    <i class="ph-warning text-2xl text-amber-500"></i>
                    <span class="font-medium"><?php echo htmlspecialchars($warning); ?></span>
                </div>
            <?php endif; ?>
            

            <?php
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            $is_staff_user = function_exists('isAdmin') && (isAdmin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'));
            
            if (strpos($referer, 'my_complaints.php') !== false) {
                $backUrl = 'my_complaints.php';
                $backText = 'Volver a Mis Reportes';
            } elseif (!$is_staff_user) {
                $backUrl = 'my_complaints.php';
                $backText = 'Volver a Mis Reportes';
            } else {
                $backUrl = 'dashboard.php';
                $backText = 'Volver al Dashboard';
            }
            ?>
            <div class="liquid-glass rounded-2xl overflow-visible"
                 <?php if (isAdmin()): ?>
                 x-data="{ 
                    isLoading: false, 
                    result: <?php echo $gemini_result_data ? htmlspecialchars(json_encode($gemini_result_data)) : 'null'; ?>, 
                    error: null,
                    analyze() {
                        this.isLoading = true;
                        this.error = null;
                        this.result = null;
                        
                        fetch('ajax_gemini_analyze.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ complaint_id: <?php echo $complaint_id; ?> })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.result = data.data;
                            } else {
                                this.error = data.error || 'Ocurrió un error desconocido.';
                            }
                        })
                        .catch(err => {
                            this.error = 'Error de conexión: ' + err.message;
                        })
                        .finally(() => {
                            this.isLoading = false;
                        });
                    }
                 }"
                 <?php endif; ?>
            >
                <div class="px-6 py-6 md:px-12 md:pt-8 md:pb-12">
                    <!-- Breadcrumb Inside Card -->
                    <div class="mb-4 md:mb-6">
                        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="inline-flex items-center text-black hover:text-gray-600 dark:text-white dark:hover:text-gray-300 font-bold transition-colors group text-sm md:text-base bg-transparent">
                            <i class="ph-arrow-left text-lg mr-2 group-hover:-translate-x-1 transition-transform"></i>
                            <?php echo $backText; ?>
                        </a>
                    </div>

                    <div class="border-b border-white/10 dark:border-white/5 pb-4 md:pb-8 mb-4 md:mb-8">
                        <div class="flex flex-col md:flex-row justify-between items-start gap-2 md:gap-4">
                            <div class="w-full md:w-auto">
                                <h1 class="text-3xl md:text-4xl font-bold text-gray-800">Detalles del Reporte</h1>
                                <div class="flex items-center gap-2 md:gap-3 mt-1">
                                    <p class="text-gray-500 text-sm md:text-lg">
                                        Folio #<?php echo $complaint['folio'] ?? str_pad($complaint['id'], 6, '0', STR_PAD_LEFT); ?>
                                    </p>
                                    
                                    <?php if (isAdmin()): ?>
                                        <!-- Gemini Analyze Button (Inline with Folio) -->
                                        <button type="button"
                                                @click="analyze()"
                                                :disabled="isLoading"
                                                class="inline-flex items-center gap-1 md:gap-2 text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300 font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                            <template x-if="!isLoading">
                                                <div class="flex items-center gap-1 md:gap-2">
                                                    <i class="ph-sparkle text-base md:text-lg"></i>
                                                    <span class="text-xs md:text-sm">Analizar con Gemini</span>
                                                </div>
                                            </template>
                                            <template x-if="isLoading">
                                                <div class="flex items-center gap-1 md:gap-2">
                                                    <svg class="animate-spin h-3 w-3 md:h-4 md:w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    <span class="text-xs md:text-sm">Analizando...</span>
                                                </div>
                                            </template>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php $statusInfo = getStatusInfo($complaint['status']); ?>
                            <div class="inline-flex items-center gap-x-1.5 md:gap-x-2 py-1.5 md:py-2 px-3 md:px-4 rounded-full text-xs md:text-base font-medium <?php echo $statusInfo['class']; ?> ring-1 ring-inset">
                                <i class="<?php echo $statusInfo['icon']; ?> text-sm md:text-lg"></i>
                                <?php echo $statusInfo['text']; ?>
                                <?php if (isAdmin()): ?>
                                    <button @click="isAdminPanelOpen = true; adminModalMode = 'status';"
                                            class="ml-1 transition-all hover:scale-110" title="Editar estado">
                                        <i class="ph-pencil-simple text-sm md:text-lg"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (isAdmin()): ?>
                        <div class="mb-8">
                            
                            <!-- Error Message -->
                            <div x-show="error" style="display: none;" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-3">
                                <i class="ph-warning-circle text-xl"></i>
                                <span x-text="error"></span>
                            </div>

                            <!-- Results Container -->
                            <div x-show="result" style="display: none;" 
                                 :class="{
                                    'bg-gray-50 border-gray-200': result?.accion === 'invalido',
                                    'bg-orange-50 border-orange-200': result?.accion === 'duplicado',
                                    'bg-purple-50 border-purple-200': result?.accion === 'procesar' || !result?.accion
                                 }"
                                 class="border rounded-xl p-4 transition-all duration-500 ease-in-out">
                                <div class="flex items-start gap-3 mb-3">
                                    <div :class="{
                                        'bg-gray-100 text-gray-600': result?.accion === 'invalido',
                                        'bg-orange-100 text-orange-600': result?.accion === 'duplicado',
                                        'bg-purple-100 text-purple-600': result?.accion === 'procesar' || !result?.accion
                                    }" class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0">
                                        <i :class="{
                                            'ph-prohibit': result?.accion === 'invalido',
                                            'ph-copy': result?.accion === 'duplicado',
                                            'ph-sparkle': result?.accion === 'procesar' || !result?.accion
                                        }" class="text-xl"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h2 :class="{
                                            'text-gray-900': result?.accion === 'invalido',
                                            'text-orange-900': result?.accion === 'duplicado',
                                            'text-purple-900': result?.accion === 'procesar' || !result?.accion
                                        }" class="text-base font-semibold">
                                            <span x-show="result?.accion === 'invalido'">⚠️ Reporte detectado como Inválido</span>
                                            <span x-show="result?.accion === 'duplicado'">🔄 Reporte detectado como Duplicado</span>
                                            <span x-show="result?.accion === 'procesar' || !result?.accion">Sugerencias automáticas de IA</span>
                                        </h2>
                                        <p :class="{
                                            'text-gray-700': result?.accion === 'invalido',
                                            'text-orange-700': result?.accion === 'duplicado',
                                            'text-purple-700': result?.accion === 'procesar' || !result?.accion
                                        }" class="text-xs">
                                            <span x-show="result?.accion === 'invalido'">Este reporte no cumple con los requisitos para ser procesado.</span>
                                            <span x-show="result?.accion === 'duplicado'">Este reporte parece ser duplicado de otro existente.</span>
                                            <span x-show="result?.accion === 'procesar' || !result?.accion">Revisa la categorización propuesta, departamentos sugeridos y resumen.</span>
                                        </p>
                                    </div>
                                </div>

                                <!-- Motivo de cierre (para inválido/duplicado) -->
                                <template x-if="result?.accion === 'invalido' || result?.accion === 'duplicado'">
                                    <div class="mb-4">
                                        <div :class="{
                                            'bg-gray-100 border-gray-300': result?.accion === 'invalido',
                                            'bg-orange-100 border-orange-300': result?.accion === 'duplicado'
                                        }" class="rounded-lg p-3 border">
                                            <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Motivo</h3>
                                            <p class="text-sm text-gray-700" x-text="result?.motivo_cierre || 'No se especificó un motivo'"></p>
                                            <template x-if="result?.duplicado_de">
                                                <p class="text-xs text-gray-500 mt-1">
                                                    Posible duplicado del reporte ID: <span x-text="result.duplicado_de" class="font-bold"></span>
                                                </p>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <!-- Contenido normal (solo para procesar) -->
                                <template x-if="result?.accion === 'procesar' || !result?.accion">
                                    <div class="space-y-3">
                                        <!-- Tipo/Categoría (Left) y Departamentos (Right) -->
                                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                            <!-- Left: Tipo y Categoría apilados -->
                                            <div class="space-y-3">
                                                <!-- Tipo -->
                                                <div>
                                                    <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Tipo</h3>
                                                    <p class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold"
                                                       :class="{
                                                            'bg-red-100 text-red-700': result?.tipo === 'queja',
                                                            'bg-blue-100 text-blue-700': result?.tipo === 'sugerencia',
                                                            'bg-green-100 text-green-700': result?.tipo === 'felicitacion',
                                                            'bg-gray-100 text-gray-700': !['queja', 'sugerencia', 'felicitacion'].includes(result?.tipo)
                                                       }">
                                                        <i class="ph-seal-check"></i>
                                                        <span x-text="result?.tipo ? result.tipo.charAt(0).toUpperCase() + result.tipo.slice(1) : 'Sin definir'"></span>
                                                    </p>
                                                </div>

                                                <!-- Categoría Sugerida -->
                                                <div>
                                                    <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Categoría Sugerida</h3>
                                                    <p class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold bg-purple-100 text-purple-700">
                                                        <i class="ph-tag"></i>
                                                        <span x-text="result?.categoria_nombre || 'ID: ' + (result?.categoria_id || 'N/A')"></span>
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Right: Departamentos Sugeridos -->
                                            <div>
                                                <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Departamentos sugeridos</h3>
                                                <template x-if="result?.lista_departamentos && result.lista_departamentos.length > 0">
                                                    <div class="space-y-2">
                                                        <template x-for="dept in result.lista_departamentos" :key="dept.id">
                                                            <div class="rounded-lg border border-purple-200 bg-white p-3">
                                                                <p class="font-semibold text-gray-900 flex items-center gap-2 text-sm">
                                                                    <i class="ph-buildings text-purple-500"></i>
                                                                    <span x-text="dept.nombre"></span>
                                                                </p>
                                                                <p class="mt-1 text-xs text-gray-600" x-text="dept.motivo"></p>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>
                                                <template x-if="!result?.lista_departamentos || result.lista_departamentos.length === 0">
                                                    <p class="text-xs text-gray-500 italic">No hay departamentos sugeridos para este reporte.</p>
                                                </template>
                                            </div>
                                        </div>

                                        <!-- Resumen (Full Width Below) -->
                                        <template x-if="result?.resumen">
                                            <div>
                                                <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Resumen generado</h3>
                                                <p class="text-sm text-gray-700 leading-relaxed bg-white border border-purple-200 rounded-lg p-3" x-text="result.resumen"></p>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <!-- Formulario de Aplicar Sugerencias -->
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="apply_gemini_suggestions" value="1">
                                    <input type="hidden" name="gemini_accion" :value="result?.accion || 'procesar'">
                                    <input type="hidden" name="gemini_categoria_id" :value="result?.categoria_id || 0">
                                    <input type="hidden" name="gemini_departamentos" :value="JSON.stringify(result?.lista_departamentos || [])">
                                    <input type="hidden" name="gemini_motivo_cierre" :value="result?.motivo_cierre || ''">
                                    <input type="hidden" name="gemini_duplicado_de" :value="result?.duplicado_de || ''">
                                    
                                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
                                        <!-- Note (Left) -->
                                        <p :class="{
                                            'bg-gray-50 border-gray-200 text-gray-600': result?.accion === 'invalido',
                                            'bg-orange-50 border-orange-200 text-orange-600': result?.accion === 'duplicado',
                                            'bg-green-50 border-green-200 text-gray-600': result?.accion === 'procesar' || !result?.accion
                                        }" class="text-xs border rounded-lg p-2.5 flex-1">
                                            <i :class="{
                                                'text-gray-600': result?.accion === 'invalido',
                                                'text-orange-600': result?.accion === 'duplicado',
                                                'text-green-600': result?.accion === 'procesar' || !result?.accion
                                            }" class="ph-info mr-1"></i>
                                            <span x-show="result?.accion === 'invalido'">Se marcará el reporte como inválido y se agregará un comentario con el motivo.</span>
                                            <span x-show="result?.accion === 'duplicado'">Se marcará el reporte como duplicado y se agregará un comentario con el motivo.</span>
                                            <span x-show="result?.accion === 'procesar' || !result?.accion">Se actualizarán la categoría y departamentos asignados. Se enviarán correos de notificación a los departamentos.</span>
                                        </p>
                                        
                                        <!-- Button (Right) -->
                                        <button type="submit" 
                                                :class="{
                                                    'bg-gray-600 hover:bg-gray-700': result?.accion === 'invalido',
                                                    'bg-orange-600 hover:bg-orange-700': result?.accion === 'duplicado',
                                                    'bg-green-600 hover:bg-green-700': result?.accion === 'procesar' || !result?.accion
                                                }"
                                                class="inline-flex items-center justify-center gap-2 text-white font-semibold py-2.5 px-5 rounded-lg transition-colors shadow whitespace-nowrap">
                                            <i :class="{
                                                'ph-prohibit': result?.accion === 'invalido',
                                                'ph-copy': result?.accion === 'duplicado',
                                                'ph-check-circle': result?.accion === 'procesar' || !result?.accion
                                            }" class="text-lg"></i>
                                            <span x-show="result?.accion === 'invalido'">Marcar como Inválido</span>
                                            <span x-show="result?.accion === 'duplicado'">Marcar como Duplicado</span>
                                            <span x-show="result?.accion === 'procesar' || !result?.accion">Aplicar Sugerencias</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4 md:mb-8">
                        <!-- First Row: Enviado Por, Fecha, Categoría -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-6 mb-3 md:mb-6">
                            <!-- Enviado Por -->
                            <div class="flex items-start gap-3">
                                <?php if ($complaint['is_anonymous']): ?>
                                    <!-- Anonymous - Always show the detective icon -->
                                    <div class="w-10 h-10 md:w-12 md:h-12 bg-gradient-to-br from-gray-400 to-gray-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                        <i class="ph-detective text-white text-xl md:text-2xl"></i>
                                    </div>
                                <?php else: ?>
                                    <!-- Not anonymous - show profile photo or initials -->
                                    <?php if (!empty($complaint['user_profile_photo'])): ?>
                                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-xl overflow-hidden flex items-center justify-center flex-shrink-0 border-2 border-gray-200">
                                            <img src="data:image/jpeg;base64,<?php echo $complaint['user_profile_photo']; ?>" 
                                                 alt="Profile" 
                                                 class="w-full h-full object-cover"
                                                 onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-10 h-10 md:w-12 md:h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center\'><span class=\'text-white font-bold text-lg md:text-xl\'><?php echo strtoupper(substr($complaint['user_name'], 0, 1)); ?></span></div>';">
                                        </div>
                                    <?php else: ?>
                                        <div class="w-10 h-10 md:w-12 md:h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                            <span class="text-white font-bold text-lg md:text-xl">
                                                <?php echo strtoupper(substr($complaint['user_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-semibold text-gray-500 text-xs md:text-sm">Enviado Por</h3>
                                    <?php if ($complaint['is_anonymous']): ?>
                                        <div class="space-y-0.5">
                                            <p class="text-sm md:text-base font-bold text-gray-800">Usuario Anónimo</p>
                                            <?php if ($complaint['user_id'] == $_SESSION['user_id']): ?>
                                                <p class="text-[10px] md:text-xs text-purple-600">
                                                    <i class="ph-info"></i>
                                                    Este es tu reporte anónimo
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-sm md:text-base font-bold text-gray-800 truncate"><?php echo htmlspecialchars($complaint['user_name']); ?></p>
                                        <p class="text-[10px] md:text-xs text-gray-500 truncate"><?php echo htmlspecialchars($complaint['user_email']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Fecha de Envío -->
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 md:w-12 md:h-12 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="ph-calendar text-xl md:text-2xl text-gray-500"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-semibold text-gray-500 text-xs md:text-sm">Fecha de Envío</h3>
                                    <p class="text-sm md:text-base font-bold text-gray-800">
                                        <?php echo date('d/m/Y \a \l\a\s H:i', strtotime($complaint['created_at'])); ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Categoría -->
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 md:w-12 md:h-12 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="ph-tag text-xl md:text-2xl text-gray-500"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <h3 class="font-semibold text-gray-500 text-xs md:text-sm">Categoría</h3>
                                        <?php if (isAdmin()): ?>
                                            <button type="button"
                                                    @click="isAdminPanelOpen = true; adminModalMode = 'category'; activeTab = 'category';"
                                                    class="inline-flex items-center gap-1 text-xs md:text-sm font-semibold text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors group">
                                                <i class="ph-pencil-simple text-sm md:text-base group-hover:scale-110 transition-transform duration-300"></i>
                                                <span class="hidden md:inline">Editar</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm md:text-base font-bold text-gray-800 truncate">
                                        <?php echo $complaint['category_name'] ? htmlspecialchars($complaint['category_name']) : 'Sin categoría'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Second Row: Departamentos Asignados (Full Width) -->
                        <?php 
                        // Get assigned departments
                        if (!isset($assigned_departments)) {
                            $stmt_dept = $conn->prepare("
                                SELECT d.*, cd.assigned_at, u.profile_photo 
                                FROM departments d 
                                JOIN complaint_departments cd ON d.id = cd.department_id 
                                LEFT JOIN users u ON u.email = d.email
                                WHERE cd.complaint_id = ?
                                ORDER BY cd.assigned_at DESC
                            ");
                            $stmt_dept->bind_param("i", $complaint_id);
                            $stmt_dept->execute();
                            $assigned_departments = $stmt_dept->get_result();
                        } else {
                            $assigned_departments->data_seek(0);
                        }
                        ?>
                        <div class="mt-4 md:mt-5 pt-4 md:pt-5 border-t border-gray-200 dark:border-gray-700/50">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2.5">
                                    <h3 class="text-sm md:text-base font-bold text-gray-800 dark:text-white">Departamentos Asignados</h3>
                                </div>
                                <?php if (isAdmin()): ?>
                                    <button type="button"
                                            @click="isAdminPanelOpen = true; adminModalMode = 'departments'; activeTab = 'departments';"
                                            class="inline-flex items-center gap-1 text-xs md:text-sm font-semibold text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors group">
                                        <i class="ph-pencil-simple text-sm md:text-base group-hover:scale-110 transition-transform duration-300"></i>
                                        <span class="hidden md:inline">Editar</span>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <?php if ($assigned_departments->num_rows == 0): ?>
                                <div class="bg-yellow-50/80 dark:bg-yellow-900/20 backdrop-blur-sm rounded-lg p-3 border border-yellow-200 dark:border-yellow-700/50 flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-yellow-100 dark:bg-yellow-800/60 flex items-center justify-center flex-shrink-0 text-yellow-600 dark:text-yellow-400">
                                        <i class="ph-warning-circle text-xl"></i>
                                    </div>
                                    <p class="font-semibold text-yellow-800 dark:text-yellow-300 text-xs md:text-sm">Reporte sin asignar</p>
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 md:gap-3">
                                    <?php while ($dept = $assigned_departments->fetch_assoc()): ?>
                                        <div class="bg-gray-50/80 dark:bg-slate-800/60 backdrop-blur-sm rounded-lg p-2.5 md:p-3 border border-white dark:border-slate-700/50 shadow-sm hover:shadow-md transition-all flex items-center gap-3 relative hover:z-50 group">
                                            <!-- Decorative bg safely bounded -->
                                            <div class="absolute inset-0 rounded-lg overflow-hidden pointer-events-none">
                                                <div class="absolute -right-4 -top-4 w-12 h-12 bg-gradient-to-br from-indigo-500/10 to-purple-500/10 rounded-full blur-xl hover:bg-indigo-500/20 transition-all"></div>
                                            </div>
                                            
                                            <?php if (!empty($dept['profile_photo'])): ?>
                                                <div class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center flex-shrink-0 shadow-sm transition-transform duration-300 border-2 border-white dark:border-slate-700 relative z-10">
                                                    <img src="data:image/jpeg;base64,<?php echo $dept['profile_photo']; ?>" alt="Profile" class="w-full h-full object-cover">
                                                </div>
                                            <?php else: ?>
                                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white flex-shrink-0 shadow-sm transition-transform duration-300 font-bold text-lg border border-white/50 dark:border-slate-700/50 relative z-10">
                                                    <?php echo strtoupper(substr($dept['manager'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-1 min-w-0 relative z-10">
                                                <div class="flex items-center justify-between gap-2">
                                                    <div class="relative group/name min-w-0 flex-1 cursor-default">
                                                        <h4 class="font-bold text-gray-900 dark:text-white text-xs md:text-sm truncate"><?php echo htmlspecialchars($dept['name']); ?></h4>
                                                        <div class="absolute bottom-full left-0 mb-2 opacity-0 group-hover/name:opacity-100 transition-all duration-200 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white text-xs font-semibold rounded-lg py-1.5 px-2.5 pointer-events-none z-50 shadow-xl border border-gray-200 dark:border-gray-700 w-max max-w-[250px] whitespace-normal break-words">
                                                            <?php echo htmlspecialchars($dept['name']); ?>
                                                        </div>
                                                    </div>
                                                    <span class="text-[9px] md:text-[10px] text-gray-400 dark:text-gray-500 whitespace-nowrap flex-shrink-0">
                                                        <?php echo date('d/m/y H:i', strtotime($dept['assigned_at'])); ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="flex items-center gap-3 mt-0.5">
                                                    <div class="relative group/mgr min-w-0 max-w-[50%] cursor-default">
                                                        <p class="text-[10px] md:text-xs text-gray-600 dark:text-gray-400 flex items-center gap-1 truncate">
                                                            <span class="font-medium truncate"><?php echo htmlspecialchars($dept['manager']); ?></span>
                                                        </p>
                                                        <div class="absolute bottom-full left-0 mb-2 opacity-0 group-hover/mgr:opacity-100 transition-all duration-200 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white text-[10px] md:text-xs font-semibold rounded-lg py-1.5 px-2.5 pointer-events-none z-50 shadow-xl border border-gray-200 dark:border-gray-700 w-max max-w-[200px] whitespace-normal break-words">
                                                            <?php echo htmlspecialchars($dept['manager']); ?>
                                                        </div>
                                                    </div>
                                                    <div class="relative group/mail min-w-0 flex-1">
                                                        <a href="mailto:<?php echo htmlspecialchars($dept['email']); ?>" class="text-[10px] md:text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 hover:underline flex items-center gap-1 truncate transition-colors">
                                                            <i class="ph-envelope-simple text-blue-500 flex-shrink-0"></i>
                                                            <span class="truncate"><?php echo htmlspecialchars($dept['email']); ?></span>
                                                        </a>
                                                        <div class="absolute bottom-full right-0 md:left-0 md:right-auto mb-2 opacity-0 group-hover/mail:opacity-100 transition-all duration-200 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white text-[10px] md:text-xs font-semibold rounded-lg py-1.5 px-2.5 pointer-events-none z-50 shadow-xl border border-gray-200 dark:border-gray-700 w-max max-w-[200px] whitespace-normal break-words">
                                                            <?php echo htmlspecialchars($dept['email']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-4 md:mb-8">
                        <h2 class="text-lg md:text-xl font-bold text-gray-800 dark:text-white mb-3 md:mb-4">Descripción del Reporte</h2>
                        <div class="glass-inner rounded-xl p-4 md:p-6">
                            <p class="text-gray-700 dark:text-gray-200 whitespace-pre-wrap leading-relaxed text-sm md:text-base"><?php echo htmlspecialchars($complaint['description']); ?></p>
                        </div>
                    </div>

                    <div class="mb-4 md:mb-8">
                        <h2 class="text-lg md:text-xl font-bold text-gray-800 dark:text-white mb-3 md:mb-4">Evidencia Adjunta</h2>
                        <?php if (empty($attachments)): ?>
                            <div class="glass-inner rounded-xl p-6 text-center border-2 border-dashed border-gray-200 dark:border-gray-700">
                                <p class="text-gray-500 dark:text-gray-400">No se adjuntó ninguna evidencia para este reporte.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-2 md:gap-4">
                                <?php foreach ($attachments as $attachment): ?>
                                    <div class="glass-inner rounded-xl overflow-hidden group transition-shadow hover:shadow-md border border-gray-100 dark:border-gray-700">
                                        <?php if (str_contains($attachment['file_type'], 'image/')): ?>
                                            <button @click="isModalOpen = true; modalImageUrl = '<?php echo htmlspecialchars($attachment['file_path']); ?>'" class="w-full h-24 md:h-40 block">
                                                <img src="<?php echo htmlspecialchars($attachment['file_path']); ?>" alt="<?php echo htmlspecialchars($attachment['file_name']); ?>" class="w-full h-full object-cover">
                                            </button>
                                        <?php else: ?>
                                            <div class="w-full h-24 md:h-40 bg-gray-100/50 dark:bg-slate-800/50 flex items-center justify-center">
                                                <i class="<?php echo getFileIcon($attachment['file_type']); ?> text-3xl md:text-6xl text-gray-400 dark:text-gray-500"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="p-2 md:p-4">
                                            <p class="text-xs md:text-sm font-semibold text-gray-700 dark:text-gray-200 truncate"><?php echo htmlspecialchars($attachment['file_name']); ?></p>
                                            <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank" download
                                               class="inline-flex items-center mt-1 md:mt-2 text-xs md:text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 font-semibold group/link">
                                                Descargar <i class="ph-download-simple text-sm md:text-lg ml-1 group-hover/link:translate-y-0.5 transition-transform"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Comments Section -->
                    <div class="mb-8" x-data="{ 
                        showForm: false,
                        showAttachments: false,
                        comment: '', 
                        hasFiles: false,
                        fileCount: 0,
                        files: [],
                        fileObjects: [],
                        isDragging: false,
                        updateFileCount() {
                            const input = document.getElementById('comment_attachments');
                            this.fileCount = input.files.length;
                            this.hasFiles = this.fileCount > 0;
                            this.fileObjects = Array.from(input.files);
                            this.generateFileList();
                        },
                        handleFileSelect(event) {
                            const input = event.target;
                            const dt = new DataTransfer();
                            
                            // Add existing files first
                            this.fileObjects.forEach(file => {
                                dt.items.add(file);
                            });
                            
                            // Add newly selected files
                            Array.from(input.files).forEach(file => {
                                dt.items.add(file);
                            });
                            
                            input.files = dt.files;
                            this.updateFileCount();
                        },
                        async generateFileList() {
                            const newFiles = [];
                            
                            for (let i = 0; i < this.fileObjects.length; i++) {
                                const file = this.fileObjects[i];
                                const fileObj = {
                                    index: i,
                                    name: file.name,
                                    size: this.formatFileSize(file.size),
                                    type: file.type,
                                    isImage: file.type.startsWith('image/'),
                                    preview: null
                                };
                                
                                if (fileObj.isImage) {
                                    try {
                                        fileObj.preview = await this.readFileAsDataURL(file);
                                    } catch (error) {
                                        console.error('Error reading file:', error);
                                    }
                                }
                                
                                newFiles.push(fileObj);
                            }
                            
                            this.files = newFiles;
                        },
                        readFileAsDataURL(file) {
                            return new Promise((resolve, reject) => {
                                const reader = new FileReader();
                                reader.onload = (e) => resolve(e.target.result);
                                reader.onerror = reject;
                                reader.readAsDataURL(file);
                            });
                        },
                        formatFileSize(bytes) {
                            if (bytes === 0) return '0 Bytes';
                            const k = 1024;
                            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                            const i = Math.floor(Math.log(bytes) / Math.log(k));
                            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
                        },
                        removeFile(index) {
                            this.fileObjects.splice(index, 1);
                            const input = document.getElementById('comment_attachments');
                            const dt = new DataTransfer();
                            
                            this.fileObjects.forEach((file) => {
                                dt.items.add(file);
                            });
                            
                            input.files = dt.files;
                            this.updateFileCount();
                        },
                        handleDrop(e) {
                            const input = document.getElementById('comment_attachments');
                            const dt = new DataTransfer();
                            
                            // Add existing files first
                            Array.from(input.files).forEach(file => {
                                dt.items.add(file);
                            });
                            
                            // Add new dropped files
                            Array.from(e.dataTransfer.files).forEach(file => {
                                dt.items.add(file);
                            });
                            
                            input.files = dt.files;
                            this.updateFileCount();
                            this.isDragging = false;
                        },
                        getFileIcon(type) {
                            if (type.includes('pdf')) return 'ph-file-pdf';
                            if (type.includes('word') || type.includes('document')) return 'ph-file-doc';
                            if (type.includes('excel') || type.includes('spreadsheet')) return 'ph-file-xls';
                            return 'ph-file';
                        }
                    }">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-4">
                            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                                Respuestas y Seguimiento
                            </h2>
                            
                            <?php if (isStaff()): ?>
                                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                                    <!-- Cerrar Reporte Button - Solo para admins y managers asignados -->
                                    <?php if (canCloseReport()): ?>
                                    <button 
                                        type="button"
                                        @click="isAdminPanelOpen = true; adminModalMode = 'close';"
                                        class="flex-1 sm:flex-none inline-flex justify-center items-center gap-2 px-4 py-2 text-gray-700 font-semibold hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors border border-gray-200 sm:border-transparent">
                                        <i class="ph-check-circle text-lg"></i>
                                        <span class="whitespace-nowrap">Cerrar Reporte</span>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <!-- Responder Button (Moved to Header) -->
                                    <button 
                                        type="button"
                                        @click="showForm = !showForm"
                                        x-show="!showForm"
                                        class="flex-1 sm:flex-none inline-flex justify-center items-center gap-2 px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-md">
                                        <i class="ph-chat-circle-dots text-lg"></i>
                                        Responder
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (isStaff()): ?>
                            <!-- Comment Form Container -->
                            <div class="mb-6">
                                
                                <!-- Add Comment Form (Collapsible) -->
                                <div 
                                    x-show="showForm"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 transform scale-95"
                                    x-transition:enter-end="opacity-100 transform scale-100"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100 transform scale-100"
                                    x-transition:leave-end="opacity-0 transform scale-95"
                                    style="display: none;"
                                    class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-6 border border-blue-200 dark:border-blue-800/50 relative">
                                    
                                    <!-- Cancel Button (Top Right, Icon Only) -->
                                    <button 
                                        type="button"
                                        @click="showForm = false; showAttachments = false; comment = ''; hasFiles = false; fileCount = 0; files = []; document.getElementById('comment_attachments').value = '';"
                                        class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center text-gray-500 hover:text-gray-700 hover:bg-gray-200 rounded-full transition-colors">
                                        <i class="ph-x text-xl"></i>
                                    </button>
                                    
                                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                        <input type="hidden" name="add_comment" value="1">
                                        
                                        <div>
                                            <label for="comment" class="block text-sm font-semibold text-gray-700 mb-2">
                                                Agregar Respuesta
                                            </label>
                                            <div class="relative">
                                                <!-- Textarea with Drag and Drop -->
                                                <textarea 
                                                    id="comment" 
                                                    name="comment" 
                                                    rows="4"
                                                    x-model="comment"
                                                    @drop.prevent="handleDrop($event)"
                                                    @dragover.prevent="isDragging = true"
                                                    @dragleave.prevent="isDragging = false"
                                                    :class="isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300'"
                                                    placeholder="Escribe tu respuesta o arrastra archivos aquí..."
                                                    class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none transition-colors"></textarea>
                                                
                                                <!-- Attachment Button (Bottom Left of Textarea) - Opens File Dialog -->
                                                <button 
                                                    type="button"
                                                    @click="document.getElementById('comment_attachments').click();"
                                                    class="absolute bottom-3 left-2 inline-flex items-center gap-1 px-2 py-1 text-sm font-medium transition-colors text-gray-500 hover:text-blue-500 dark:text-gray-400 dark:hover:text-blue-400">
                                                    <i class="ph-paperclip text-lg"></i>
                                                    <span>Adjuntar</span>
                                                </button>
                                                
                                                <!-- Hidden File Input -->
                                                <input 
                                                    type="file" 
                                                    id="comment_attachments"
                                                    name="attachments[]" 
                                                    multiple 
                                                    accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                                                    @change="handleFileSelect($event)"
                                                    class="hidden">
                                            </div>
                                        </div>
                                        
                                        <!-- Attachments Preview Section (Shows when files selected) -->
                                        <div 
                                            x-show="hasFiles"
                                            x-transition:enter="transition ease-out duration-200"
                                            x-transition:enter-start="opacity-0 max-h-0"
                                            x-transition:enter-end="opacity-100 max-h-screen"
                                            x-transition:leave="transition ease-in duration-150"
                                            x-transition:leave-start="opacity-100 max-h-screen"
                                            x-transition:leave-end="opacity-0 max-h-0"
                                            style="display: none;"
                                            class="overflow-hidden">
                                            <div class="bg-white rounded-lg p-4 border border-blue-200">
                                                <div class="flex items-center justify-between mb-3">
                                                    <label class="block text-sm font-semibold text-gray-700">
                                                        <i class="ph-images mr-1"></i>
                                                        Archivos Adjuntos (<span x-text="fileCount"></span>)
                                                    </label>
                                                    <button 
                                                        type="button"
                                                        @click="document.getElementById('comment_attachments').value = ''; hasFiles = false; fileCount = 0; files = [];"
                                                        class="text-xs text-red-600 hover:text-red-800 font-medium">
                                                        <i class="ph-trash"></i> Limpiar Todo
                                                    </button>
                                                </div>
                                                
                                                <!-- File Cards -->
                                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                                    <template x-for="(file, index) in files" :key="index">
                                                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200 hover:border-blue-300 transition-colors group">
                                                            <!-- Icon/Thumbnail (Left) -->
                                                            <div class="flex-shrink-0 w-12 h-12 rounded-lg overflow-hidden bg-white border border-gray-200 flex items-center justify-center">
                                                                <template x-if="file.isImage && file.preview">
                                                                    <img :src="file.preview" class="w-full h-full object-cover">
                                                                </template>
                                                                <template x-if="!file.isImage || !file.preview">
                                                                    <i :class="getFileIcon(file.type)" class="text-2xl text-gray-600"></i>
                                                                </template>
                                                            </div>
                                                            
                                                            <!-- File Info (Center) -->
                                                            <div class="flex-1 min-w-0">
                                                                <p class="text-sm font-medium text-gray-900 truncate" x-text="file.name"></p>
                                                                <p class="text-xs text-gray-500" x-text="file.size"></p>
                                                            </div>
                                                            
                                                            <!-- Remove Button (Right) -->
                                                            <button 
                                                                type="button"
                                                                @click="removeFile(file.index)"
                                                                class="flex-shrink-0 w-6 h-6 flex items-center justify-center text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors opacity-0 group-hover:opacity-100">
                                                                <i class="ph-x text-lg"></i>
                                                            </button>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center justify-between">
                                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                                <i class="ph-info mr-1"></i>
                                                Máximo 25 MB por archivo
                                            </p>
                                            <button 
                                                type="submit"
                                                :disabled="comment.trim() === '' && !hasFiles"
                                                :class="(comment.trim() !== '' || hasFiles) ? 'bg-blue-600 hover:bg-blue-700 cursor-pointer' : 'bg-gray-300 cursor-not-allowed'"
                                                class="inline-flex items-center px-6 py-2.5 text-white font-semibold rounded-lg transition-colors shadow-md disabled:opacity-50">
                                                <i class="ph-paper-plane-tilt text-lg mr-2"></i>
                                                Enviar Respuesta
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Comments Timeline -->
                        <?php if (empty($comments)): ?>
                            <div class="liquid-glass rounded-lg p-8 text-center border border-white/20">
                                <i class="ph-chats text-5xl text-gray-400 mb-3"></i>
                                <p class="text-gray-500 dark:text-gray-400 font-medium">Aún no hay respuestas para este reporte.</p>
                                <?php if (isStaff()): ?>
                                    <p class="text-gray-400 text-sm mt-1">Sé el primero en responder usando el formulario de arriba.</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php foreach ($comments as $comment): ?>
                                    <div class="liquid-glass rounded-xl border border-white/20 shadow-sm hover:shadow-md transition-shadow">
                                        <!-- Comment Header -->
                                        <div class="px-6 py-4 border-b border-white/10">
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="flex items-start gap-3 flex-1 min-w-0">
                                                    <?php 
                                                    $is_anonymous_author = ($comment['user_id'] == $complaint['user_id'] && $comment['is_anonymous']);
                                                    $avatar_char = $is_anonymous_author ? '?' : strtoupper(substr($comment['user_name'] ?? '?', 0, 1));
                                                    ?>
                                                    <?php if (!$is_anonymous_author && !empty($comment['user_profile_photo'])): ?>
                                                        <div class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center flex-shrink-0 border-2 border-white/20">
                                                            <img src="data:image/jpeg;base64,<?php echo $comment['user_profile_photo']; ?>" 
                                                                 alt="Profile" 
                                                                 class="w-full h-full object-cover"
                                                                 onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold\'><?php echo $avatar_char; ?></div>';">
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br <?php echo $is_anonymous_author ? 'from-gray-400 to-gray-600' : 'from-blue-500 to-indigo-600'; ?> flex items-center justify-center text-white font-bold flex-shrink-0">
                                                            <?php if ($is_anonymous_author): ?>
                                                                <i class="ph-detective text-xl"></i>
                                                            <?php else: ?>
                                                                <?php echo $avatar_char; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2 flex-wrap mb-1">
                                                            <p class="font-semibold text-gray-900 dark:text-white">
                                                                <?php echo $is_anonymous_author ? 'Usuario Anónimo' : htmlspecialchars($comment['user_name']); ?>
                                                            </p>
                                                            <?php if ($comment['user_id'] == $complaint['user_id']): ?>
                                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-500/10 text-green-600 dark:text-green-400">
                                                                    <i class="ph-check-circle text-xs"></i>
                                                                    Autor
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <div class="flex items-center gap-2 flex-wrap">
                                                            <?php if ($is_anonymous_author): ?>
                                                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Estudiante</p>
                                                            <?php elseif ($comment['user_role'] === 'admin'): ?>
                                                                <p class="text-xs text-purple-600 dark:text-purple-400 font-medium">Administrador</p>
                                                            <?php elseif ($comment['user_role'] === 'manager'): ?>
                                                                <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">
                                                                    <?php 
                                                                    $stmt_dept = $conn->prepare("SELECT name FROM departments WHERE email = (SELECT email FROM users WHERE id = ?)");
                                                                    $stmt_dept->bind_param("i", $comment['user_id']);
                                                                    $stmt_dept->execute();
                                                                    $dept_result = $stmt_dept->get_result();
                                                                    if ($dept_row = $dept_result->fetch_assoc()) {
                                                                        echo htmlspecialchars($dept_row['name']);
                                                                    } else {
                                                                        echo 'Encargado';
                                                                    }
                                                                    ?>
                                                                </p>
                                                            <?php elseif ($comment['user_role'] === 'student'): ?>
                                                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Estudiante</p>
                                                            <?php endif; ?>

                                                            <span class="text-gray-300 dark:text-gray-600 sm:hidden">&bull;</span>
                                                            <span class="text-xs text-gray-400 sm:hidden">
                                                                <?php echo date('d/m/y H:i', strtotime($comment['created_at'])); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="hidden sm:block text-right flex-shrink-0">
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                                        <i class="ph-clock text-xs mr-1"></i>
                                                        <?php echo date('d/m/Y', strtotime($comment['created_at'])); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                                        <?php echo date('H:i', strtotime($comment['created_at'])); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Comment Body -->
                                        <div class="px-6 py-4">
                                            <?php if (!empty($comment['comment'])): ?>
                                                <p class="text-gray-700 dark:text-gray-200 whitespace-pre-wrap leading-relaxed"><?php echo htmlspecialchars($comment['comment']); ?></p>
                                            <?php endif; ?>
                                            
                                            <!-- Attachments -->
                                            <?php if (!empty($comment['attachments'])): ?>
                                                <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                                                    <?php foreach ($comment['attachments'] as $attachment): ?>
                                                        <?php 
                                                        $is_image = str_contains($attachment['file_type'], 'image') || 
                                                                   preg_match('/\.(jpg|jpeg|png|gif|bmp|webp|svg)$/i', $attachment['file_name']);
                                                        ?>
                                                        <div class="group relative">
                                                            <?php if ($is_image): ?>
                                                                <button 
                                                                    type="button"
                                                                    @click="isModalOpen = true; modalImageUrl = '<?php echo addslashes($attachment['file_path']); ?>'"
                                                                    class="block w-full aspect-square rounded-lg overflow-hidden border border-white/20 hover:border-blue-400 transition-all cursor-pointer">
                                                                    <img 
                                                                        src="<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                                                        alt="<?php echo htmlspecialchars($attachment['file_name']); ?>"
                                                                        class="w-full h-full object-cover group-hover:scale-110 transition-transform"
                                                                        loading="lazy">
                                                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all flex items-center justify-center">
                                                                        <i class="ph-magnifying-glass-plus text-white text-2xl opacity-0 group-hover:opacity-100 transition-opacity drop-shadow-lg"></i>
                                                                    </div>
                                                                </button>
                                                            <?php else: ?>
                                                                <a 
                                                                    href="<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                                                    target="_blank" 
                                                                    download
                                                                    class="block w-full aspect-square rounded-lg border border-white/20 hover:border-blue-400 bg-white/5 flex flex-col items-center justify-center gap-2 transition-all group-hover:shadow-md">
                                                                    <i class="<?php echo getFileIcon($attachment['file_type']); ?> text-4xl text-gray-600 dark:text-gray-400"></i>
                                                                    <p class="text-xs text-gray-600 dark:text-gray-400 font-medium px-2 text-center truncate w-full">
                                                                        <?php echo htmlspecialchars($attachment['file_name']); ?>
                                                                    </p>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'components/footer.php'; ?>
