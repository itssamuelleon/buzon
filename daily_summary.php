<?php
// daily_summary.php
// Script para enviar reporte diario de quejas
// Ejecutar con cron diariamente a las 7:00 AM

// Asegurar que se ejecuta desde la línea de comandos
if (php_sapi_name() !== 'cli') {
    die('Este script solo puede ejecutarse desde la línea de comandos.');
}

// Definir ruta base
define('BASE_PATH', __DIR__);

// Cargar configuración y dependencias
require_once BASE_PATH . '/config.php';
require_once BASE_PATH . '/config/email_config.php';
require_once BASE_PATH . '/status_helper.php';
require_once BASE_PATH . '/PHPMailer/src/PHPMailer.php';
require_once BASE_PATH . '/PHPMailer/src/SMTP.php';
require_once BASE_PATH . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Actualizar estados pendientes para asegurar precisión
echo "Actualizando estados de reportes...\n";
updateAllPendingStatuses($conn);

// 2. Obtener configuración de admin (Modo Prueba)
$stmt_settings = $conn->prepare("SELECT setting_key, setting_value FROM admin_settings WHERE setting_key IN ('test_mode', 'test_email')");
$stmt_settings->execute();
$result_settings = $stmt_settings->get_result();
$settings = [];
while ($row = $result_settings->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$test_mode = isset($settings['test_mode']) && $settings['test_mode'] == '1';
$test_email = isset($settings['test_email']) ? $settings['test_email'] : '';
$target_email = ($test_mode && !empty($test_email)) ? $test_email : 'buzon@cdconstitucion.tecnm.mx';

echo "Modo prueba: " . ($test_mode ? "SI ($target_email)" : "NO ($target_email)") . "\n";

// 3. Obtener Estadísticas
// Total reportes
$result_total = $conn->query("SELECT COUNT(*) as total FROM complaints");
$total_reports = $result_total->fetch_assoc()['total'];

// Sin atender (unattended_ontime, unattended_late)
$result_unattended = $conn->query("SELECT COUNT(*) as total FROM complaints WHERE status IN ('unattended_ontime', 'unattended_late')");
$unattended_reports = $result_unattended->fetch_assoc()['total'];

// Sin asignar (No existen registros en complaint_departments para este reporte)
$result_unassigned = $conn->query("
    SELECT COUNT(*) as total 
    FROM complaints c 
    LEFT JOIN complaint_departments cd ON c.id = cd.complaint_id 
    WHERE cd.department_id IS NULL
");
$unassigned_reports = $result_unassigned->fetch_assoc()['total'];

// 4. Obtener detalles para la tabla (Sin atender o Sin asignar)
// Usamos una subconsulta o LEFT JOIN para determinar si tiene departamentos asignados
$query_details = "
    SELECT c.description, c.status, c.created_at, 
           (SELECT COUNT(*) FROM complaint_departments cd WHERE cd.complaint_id = c.id) as dept_count
    FROM complaints c 
    WHERE c.status IN ('unattended_ontime', 'unattended_late') 
       OR NOT EXISTS (SELECT 1 FROM complaint_departments cd WHERE cd.complaint_id = c.id)
    ORDER BY c.created_at ASC
";
$result_details = $conn->query($query_details);
$details = [];
while ($row = $result_details->fetch_assoc()) {
    $details[] = $row;
}

// 5. Construir el correo HTML
$date_str = date('d/m/Y');
$subject = "Resumen Diario de Quejas - $date_str";

// Estilos CSS en línea para email
$css_body = "font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background-color: #f4f7fa; margin: 0; padding: 0;";
$css_container = "max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);";
$css_header = "background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 30px 20px; text-align: center; color: #ffffff;";
$css_content = "padding: 30px;";
$css_stat_grid = "display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px;";
// Fallback para clientes de correo que no soportan grid
$css_stat_table = "width: 100%; border-collapse: collapse; margin-bottom: 30px;";
$css_stat_cell = "text-align: center; padding: 15px; background-color: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;";
$css_stat_number = "display: block; font-size: 24px; font-weight: bold; color: #0f172a;";
$css_stat_label = "font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;";

$css_table = "width: 100%; border-collapse: collapse; font-size: 14px;";
$css_th = "background-color: #f1f5f9; color: #475569; font-weight: 600; text-align: left; padding: 12px; border-bottom: 2px solid #e2e8f0;";
$css_td = "padding: 12px; border-bottom: 1px solid #e2e8f0; color: #334155;";
$css_badge = "display: inline-block; padding: 4px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600;";

$html_content = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>$subject</title>
</head>
<body style=\"$css_body\">
    <div style=\"$css_container\">
        <div style=\"$css_header\">
            <h1 style=\"margin: 0; font-size: 24px; font-weight: 700;\">📊 Resumen Diario</h1>
            <p style=\"margin: 5px 0 0; opacity: 0.8;\">$date_str</p>
        </div>
        
        <div style=\"$css_content\">
            <h2 style=\"margin-top: 0; color: #1e293b;\">¡Buenos días! ☀️</h2>
            <p style=\"color: #64748b; margin-bottom: 25px;\">Aquí tienes el estado actual del Buzón de Quejas.</p>

            <!-- Estadísticas -->
            <table style=\"$css_stat_table\">
                <tr>
                    <td style=\"width: 33%; padding: 5px;\">
                        <div style=\"$css_stat_cell\">
                            <span style=\"$css_stat_number\">$total_reports</span>
                            <span style=\"$css_stat_label\">Total</span>
                        </div>
                    </td>
                    <td style=\"width: 33%; padding: 5px;\">
                        <div style=\"$css_stat_cell\">
                            <span style=\"$css_stat_number; color: #ef4444;\">$unattended_reports</span>
                            <span style=\"$css_stat_label\">Sin Atender</span>
                        </div>
                    </td>
                    <td style=\"width: 33%; padding: 5px;\">
                        <div style=\"$css_stat_cell\">
                            <span style=\"$css_stat_number; color: #f59e0b;\">$unassigned_reports</span>
                            <span style=\"$css_stat_label\">Sin Asignar</span>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Tabla de Detalles -->
            <h3 style=\"color: #334155; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 0;\">⚠️ Atención Requerida</h3>
            
            <table style=\"$css_table\">
                <thead>
                    <tr>
                        <th style=\"$css_th\">Descripción</th>
                        <th style=\"$css_th\">Estado</th>
                        <th style=\"$css_th\">Antigüedad</th>
                    </tr>
                </thead>
                <tbody>";

if (empty($details)) {
    $html_content .= "<tr><td colspan='3' style=\"$css_td; text-align: center; color: #94a3b8;\">¡Excelente! No hay reportes pendientes de atención. 🎉</td></tr>";
} else {
    foreach ($details as $row) {
        $desc = htmlspecialchars(substr($row['description'], 0, 50)) . (strlen($row['description']) > 50 ? '...' : '');
        
        // Determinar estado legible y estilo
        $status_label = '';
        $status_style = '';
        
        if ($row['dept_count'] == 0) {
            $status_label = 'Sin Asignar';
            $status_style = "background-color: #fef3c7; color: #92400e;"; // Amarillo
        } elseif ($row['status'] == 'unattended_ontime') {
            $status_label = 'Sin Atender';
            $status_style = "background-color: #dbeafe; color: #1e40af;"; // Azul
        } elseif ($row['status'] == 'unattended_late') {
            $status_label = 'Retrasado';
            $status_style = "background-color: #fee2e2; color: #991b1b;"; // Rojo
        } else {
            $status_label = $row['status'];
            $status_style = "background-color: #f1f5f9; color: #475569;"; // Gris
        }

        // Calcular días
        $created = new DateTime($row['created_at']);
        $now = new DateTime();
        $interval = $created->diff($now);
        $days_ago = $interval->days;
        $time_text = $days_ago == 0 ? 'Hoy' : ($days_ago == 1 ? 'Ayer' : "Hace $days_ago días");

        $html_content .= "
                    <tr>
                        <td style=\"$css_td\">$desc</td>
                        <td style=\"$css_td\"><span style=\"$css_badge $status_style\">$status_label</span></td>
                        <td style=\"$css_td\">$time_text</td>
                    </tr>";
    }
}

$html_content .= "
                </tbody>
            </table>
            
            <div style=\"margin-top: 30px; text-align: center; font-size: 12px; color: #94a3b8;\">
                <p>Este es un reporte automático generado por el sistema Buzón Digital ITSCC.</p>
            </div>
        </div>
    </div>
</body>
</html>";

// 6. Enviar Correo
$mail = new PHPMailer(true);

try {
    // Configuración del servidor
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    $mail->CharSet = 'UTF-8';
    
    // Remitente y destinatario
    $mail->setFrom(SMTP_USERNAME, 'Reporte Diario - Buzón ITSCC');
    $mail->addAddress($target_email);
    
    // Contenido
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $html_content;
    $mail->AltBody = "Resumen Diario: Total: $total_reports, Sin Atender: $unattended_reports, Sin Asignar: $unassigned_reports.";
    
    $mail->send();
    echo "Correo enviado exitosamente a $target_email\n";
} catch (Exception $e) {
    echo "Error al enviar el correo: {$mail->ErrorInfo}\n";
}
?>
