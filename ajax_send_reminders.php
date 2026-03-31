<?php
require_once 'config.php';
require_once 'send_email.php';

// Verify authentication and role
if (!isLoggedIn() || !isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

try {
    // Buscar todos los reportes pendientes que estén asignados a algún departamento
    $query = "
        SELECT 
            c.id, c.folio, c.description, c.created_at,
            d.id as department_id, d.name as department_name, d.manager as department_manager, d.email as department_email
        FROM complaints c
        JOIN complaint_departments cd ON c.id = cd.complaint_id
        JOIN departments d ON cd.department_id = d.id
        WHERE c.status IN ('unattended_ontime', 'unattended_late', 'pending')
        ORDER BY d.id, c.created_at DESC
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error al consultar reportes pendientes: " . $conn->error);
    }
    
    // Read input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $is_preview = isset($data['preview']) ? filter_var($data['preview'], FILTER_VALIDATE_BOOLEAN) : false;

    // Group reports by department
    $departments_with_pending_reports = [];
    
    while ($row = $result->fetch_assoc()) {
        $dept_id = $row['department_id'];
        
        if (!isset($departments_with_pending_reports[$dept_id])) {
            $departments_with_pending_reports[$dept_id] = [
                'department' => [
                    'id' => $row['department_id'],
                    'name' => $row['department_name'],
                    'manager' => $row['department_manager'],
                    'email' => $row['department_email']
                ],
                'complaints' => []
            ];
        }
        
        $departments_with_pending_reports[$dept_id]['complaints'][] = [
            'id' => $row['id'],
            'folio' => $row['folio'],
            'description' => $row['description'],
            'created_at' => $row['created_at']
        ];
    }
    
    // If it's just a preview, return the list of departments and count of pending reports
    if ($is_preview) {
        $preview_data = [];
        
        $is_test_mode = isTestMode();
        $test_email = '';
        if ($is_test_mode) {
             $test_email = function_exists('getTestEmail') ? getTestEmail() : SMTP_USERNAME;
        }

        foreach ($departments_with_pending_reports as $dept_id => $dept_data) {
            $display_email = $dept_data['department']['email'];
            $preview_data[] = [
                'id' => $dept_id,
                'department_name' => $dept_data['department']['name'],
                'department_email' => $display_email,
                'pending_count' => count($dept_data['complaints'])
            ];
        }
        
        echo json_encode([
            'success' => true,
            'departments' => $preview_data
        ]);
        exit;
    }
    
    $emails_sent = 0;
    $errors = [];
    
    // Send one email per department containing all its pending reports
    foreach ($departments_with_pending_reports as $dept_data) {
        $department = $dept_data['department'];
        $complaints = $dept_data['complaints'];
        
        if (!empty($department['email'])) {
            $mailResult = sendDepartmentReminderEmail($department, $complaints);
            
            if ($mailResult['success']) {
                $emails_sent++;
            } else {
                $errors[] = "Error al enviar al departamento " . $department['name'] . ": " . $mailResult['message'];
            }
        } else {
            $errors[] = "El departamento " . $department['name'] . " no tiene correo electrónico configurado.";
        }
    }
    
    if ($emails_sent > 0 || empty($errors)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Se enviaron ' . $emails_sent . ' correos recordatorios a los departamentos.',
            'sent_count' => $emails_sent,
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'No se pudo enviar ningún correo.',
            'errors' => $errors
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
