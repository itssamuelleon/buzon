<?php
/**
 * Process Email Queue - Background Job
 * This script processes pending emails from the queue
 * Can be run via cron job or called asynchronously
 */

// Set execution time limit for background processing
set_time_limit(300);

// Include configuration
require_once 'config.php';
require_once 'send_email.php';

// Process pending emails
$stmt = $conn->prepare("
    SELECT eq.id, eq.complaint_id, eq.department_id, eq.attempts, eq.max_attempts
    FROM email_queue eq
    WHERE eq.status = 'pending' AND eq.attempts < eq.max_attempts
    ORDER BY eq.created_at ASC
    LIMIT 10
");

$stmt->execute();
$result = $stmt->get_result();
$processed = 0;
$failed = 0;

while ($queue_item = $result->fetch_assoc()) {
    $queue_id = $queue_item['id'];
    $complaint_id = $queue_item['complaint_id'];
    $department_id = $queue_item['department_id'];
    $attempts = $queue_item['attempts'];
    $max_attempts = $queue_item['max_attempts'];
    
    try {
        // Get complaint info
        $stmt_complaint = $conn->prepare("
            SELECT c.id, c.description, c.created_at, cat.name as category_name 
            FROM complaints c 
            LEFT JOIN categories cat ON c.category_id = cat.id 
            WHERE c.id = ?
        ");
        $stmt_complaint->bind_param("i", $complaint_id);
        $stmt_complaint->execute();
        $complaint_info = $stmt_complaint->get_result()->fetch_assoc();
        
        if (!$complaint_info) {
            // Mark as failed if complaint not found
            $stmt_update = $conn->prepare("
                UPDATE email_queue 
                SET status = 'failed', error_message = 'Complaint not found'
                WHERE id = ?
            ");
            $stmt_update->bind_param("i", $queue_id);
            $stmt_update->execute();
            $failed++;
            continue;
        }
        
        // Get department info
        $stmt_dept = $conn->prepare("
            SELECT id, name, manager, email 
            FROM departments 
            WHERE id = ?
        ");
        $stmt_dept->bind_param("i", $department_id);
        $stmt_dept->execute();
        $dept_info = $stmt_dept->get_result()->fetch_assoc();
        
        if (!$dept_info) {
            // Mark as failed if department not found
            $stmt_update = $conn->prepare("
                UPDATE email_queue 
                SET status = 'failed', error_message = 'Department not found'
                WHERE id = ?
            ");
            $stmt_update->bind_param("i", $queue_id);
            $stmt_update->execute();
            $failed++;
            continue;
        }
        
        // Send email
        $email_result = sendDepartmentNotification($dept_info, $complaint_info);
        
        if ($email_result['success']) {
            // Mark as sent
            $stmt_update = $conn->prepare("
                UPDATE email_queue 
                SET status = 'sent', sent_at = NOW(), attempts = attempts + 1
                WHERE id = ?
            ");
            $stmt_update->bind_param("i", $queue_id);
            $stmt_update->execute();
            $processed++;
        } else {
            // Increment attempts
            $new_attempts = $attempts + 1;
            $error_msg = $email_result['message'] ?? 'Unknown error';
            
            if ($new_attempts >= $max_attempts) {
                // Mark as failed after max attempts
                $stmt_update = $conn->prepare("
                    UPDATE email_queue 
                    SET status = 'failed', error_message = ?, attempts = ?
                    WHERE id = ?
                ");
                $stmt_update->bind_param("sii", $error_msg, $new_attempts, $queue_id);
                $stmt_update->execute();
                $failed++;
            } else {
                // Keep as pending for retry
                $stmt_update = $conn->prepare("
                    UPDATE email_queue 
                    SET error_message = ?, attempts = ?
                    WHERE id = ?
                ");
                $stmt_update->bind_param("sii", $error_msg, $new_attempts, $queue_id);
                $stmt_update->execute();
            }
        }
    } catch (Exception $e) {
        // Log error and mark as failed
        $error_msg = $e->getMessage();
        $new_attempts = $attempts + 1;
        
        if ($new_attempts >= $max_attempts) {
            $stmt_update = $conn->prepare("
                UPDATE email_queue 
                SET status = 'failed', error_message = ?, attempts = ?
                WHERE id = ?
            ");
            $stmt_update->bind_param("sii", $error_msg, $new_attempts, $queue_id);
            $stmt_update->execute();
            $failed++;
        } else {
            $stmt_update = $conn->prepare("
                UPDATE email_queue 
                SET error_message = ?, attempts = ?
                WHERE id = ?
            ");
            $stmt_update->bind_param("sii", $error_msg, $new_attempts, $queue_id);
            $stmt_update->execute();
        }
    }
}

// Return JSON response if called via AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'processed' => $processed,
        'failed' => $failed
    ]);
} else {
    // CLI output
    echo "Email Queue Processor\n";
    echo "Processed: $processed\n";
    echo "Failed: $failed\n";
}

$conn->close();
?>
