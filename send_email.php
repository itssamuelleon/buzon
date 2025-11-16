<?php
require_once 'config/email_config.php';

// Intentar cargar PHPMailer
$phpmailer_loaded = false;

// Intentar cargar desde Composer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
    $phpmailer_loaded = true;
} 
// Intentar cargar manualmente
elseif (file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
    require __DIR__ . '/PHPMailer/src/Exception.php';
    require __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require __DIR__ . '/PHPMailer/src/SMTP.php';
    $phpmailer_loaded = true;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envía un correo de notificación a un departamento sobre un nuevo reporte asignado
 * 
 * @param array $department Información del departamento (name, manager, email)
 * @param array $complaint Información del reporte (id, description, category_name, created_at)
 * @return array ['success' => bool, 'message' => string]
 */
function sendDepartmentNotification($department, $complaint) {
    global $phpmailer_loaded;
    global $conn;
    
    if (!$phpmailer_loaded) {
        return [
            'success' => false,
            'message' => 'PHPMailer no está instalado. Por favor, instala PHPMailer para enviar correos.'
        ];
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        // Remitente
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Destinatario (según modo de prueba)
        $recipient_email = getEmailRecipient($department['email']);
        $mail->addAddress($recipient_email, $department['manager']);
        
        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = 'Nuevo Reporte Asignado - Reporte #' . str_pad($complaint['id'], 6, '0', STR_PAD_LEFT);

        // Obtener y procesar adjuntos del reporte
        $inlineImagesHtml = '';
        if (isset($conn)) {
            $stmt_att = $conn->prepare("SELECT file_name, file_path, file_type FROM attachments WHERE complaint_id = ?");
            $stmt_att->bind_param("i", $complaint['id']);
            $stmt_att->execute();
            $attachments = $stmt_att->get_result()->fetch_all(MYSQLI_ASSOC);

            if ($attachments) {
                $inlineImagesHtml .= '<div style="margin-top:20px">';
                $inlineImagesHtml .= '<h3 style="margin:0 0 10px 0; color:#1f2937;">Evidencia adjunta (imágenes)</h3>';
                foreach ($attachments as $i => $att) {
                    $path = $att['file_path'];
                    $name = $att['file_name'];
                    // Adjuntar siempre todos los archivos
                    if (is_file($path)) {
                        $mail->addAttachment($path, $name);
                        // Incrustar imágenes
                        if (stripos($att['file_type'], 'image/') === 0) {
                            $cid = 'img' . $i . '_' . md5($path);
                            $mail->addEmbeddedImage($path, $cid, $name);
                            $inlineImagesHtml .= '<div style="margin-bottom:10px;"><img src="cid:' . $cid . '" alt="' . htmlspecialchars($name) . '" style="max-width:100%; border:1px solid #e5e7eb; border-radius:6px;"/></div>';
                        }
                    }
                }
                $inlineImagesHtml .= '</div>';
            }
        }

        // Cuerpo del correo en HTML (sin botón de acceso) y con imágenes incrustadas
        $mail->Body = generateEmailBody($department, $complaint, $inlineImagesHtml);

        // Texto alternativo (sin HTML)
        $mail->AltBody = generateEmailTextBody($department, $complaint);
        
        // Enviar correo
        $mail->send();
        
        $mode_text = isTestMode() ? ' (Modo Prueba - enviado a ' . SMTP_USERNAME . ')' : '';
        
        return [
            'success' => true,
            'message' => 'Correo enviado exitosamente a ' . $department['name'] . $mode_text
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error al enviar correo: ' . $mail->ErrorInfo
        ];
    }
}

/**
 * Genera el cuerpo del correo en HTML
 */
function generateEmailBody($department, $complaint, $inlineImagesHtml) {
    $complaint_id = str_pad($complaint['id'], 6, '0', STR_PAD_LEFT);
    
    $mode_notice = '';
    if (isTestMode()) {
        $mode_notice = '<div style="background-color: #FEF3C7; border-left: 4px solid #F59E0B; padding: 12px; margin-bottom: 20px;">
            <p style="margin: 0; color: #92400E; font-weight: bold;">⚠️ MODO DE PRUEBA ACTIVADO</p>
            <p style="margin: 5px 0 0 0; color: #92400E; font-size: 14px;">Este correo debería enviarse a: ' . htmlspecialchars($department['email']) . '</p>
        </div>';
    }
    
    $html = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Nuevo Reporte Asignado</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #2563EB; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
            <h1 style="margin: 0; font-size: 24px;">ITSCC Buzón de Quejas</h1>
            <p style="margin: 10px 0 0 0; font-size: 14px;">Sistema de Gestión de Reportes</p>
        </div>
        
        <div style="background-color: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">
            ' . $mode_notice . '
            
            <h2 style="color: #1f2937; margin-top: 0;">Estimado/a ' . htmlspecialchars($department['manager']) . ',</h2>
            
            <p>Se ha asignado un nuevo reporte al departamento de <strong>' . htmlspecialchars($department['name']) . '</strong>. Le solicitamos amablemente darle seguimiento a la brevedad posible.</p>
            
            <div style="background-color: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h3 style="color: #2563EB; margin-top: 0; border-bottom: 2px solid #2563EB; padding-bottom: 10px;">
                    📋 Detalles del Reporte #' . $complaint_id . '
                </h3>
                
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; font-weight: bold; color: #6b7280; width: 30%;">Categoría:</td>
                        <td style="padding: 8px 0;">' . htmlspecialchars($complaint['category_name']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-weight: bold; color: #6b7280;">Fecha:</td>
                        <td style="padding: 8px 0;">' . date('d/m/Y H:i', strtotime($complaint['created_at'])) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-weight: bold; color: #6b7280; vertical-align: top;">Descripción:</td>
                        <td style="padding: 8px 0;">' . nl2br(htmlspecialchars(substr($complaint['description'], 0, 200))) . (strlen($complaint['description']) > 200 ? '...' : '') . '</td>
                    </tr>
                </table>
            </div>
            
            <div style="background-color: #EFF6FF; border-left: 4px solid #2563EB; padding: 12px; margin-top: 20px;">
                <p style="margin: 0; color: #1e40af; font-size: 14px;">
                    <strong>Nota:</strong> Por favor, responde a este correo con el texto de la respuesta al reporte. El administrador tomará una captura de tu respuesta y la registrará como evidencia en el sistema.
                </p>
            </div>

            ' . $inlineImagesHtml . '
        </div>
        
        <div style="text-align: center; padding: 20px; color: #6b7280; font-size: 12px;">
            <p style="margin: 0;">Este es un correo automático del Sistema de Buzón de Quejas ITSCC</p>
            <p style="margin: 5px 0 0 0;">Por favor, no respondas directamente a este correo</p>
        </div>
    </body>
    </html>
    ';
    
    return $html;
}

/**
 * Genera el cuerpo del correo en texto plano
 */
function generateEmailTextBody($department, $complaint) {
    $complaint_id = str_pad($complaint['id'], 6, '0', STR_PAD_LEFT);
    
    $mode_notice = '';
    if (isTestMode()) {
        $mode_notice = "\n⚠️ MODO DE PRUEBA ACTIVADO\nEste correo debería enviarse a: " . $department['email'] . "\n\n";
    }
    
    $text = "ITSCC BUZÓN DE QUEJAS - NUEVO REPORTE ASIGNADO\n\n";
    $text .= $mode_notice;
    $text .= "Estimado/a " . $department['manager'] . ",\n\n";
    $text .= "Se ha asignado un nuevo reporte al departamento de " . $department['name'] . ". ";
    $text .= "Le solicitamos amablemente darle seguimiento a la brevedad posible.\n\n";
    $text .= "DETALLES DEL REPORTE #" . $complaint_id . "\n";
    $text .= "----------------------------------------\n";
    $text .= "Categoría: " . $complaint['category_name'] . "\n";
    $text .= "Fecha: " . date('d/m/Y H:i', strtotime($complaint['created_at'])) . "\n";
    $text .= "Descripción: " . substr($complaint['description'], 0, 200) . (strlen($complaint['description']) > 200 ? '...' : '') . "\n\n";
    $text .= "Nota: Por favor, responde a este correo con el texto de la respuesta al reporte. El administrador tomará una captura de tu respuesta y la registrará como evidencia en el sistema.\n\n";
    $text .= "---\n";
    $text .= "Este es un correo automático del Sistema de Buzón de Quejas ITSCC\n";
    $text .= "Por favor, no respondas directamente a este correo";
    
    return $text;
}
?>
