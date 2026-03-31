<?php
require_once 'config/email_config.php';
require_once 'config/email_antispam.php';

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
 * Traduce errores SMTP a mensajes amigables en español
 */
function translateSmtpError($errorInfo) {
    $error = strtolower($errorInfo);
    
    if (strpos($error, 'daily user sending limit exceeded') !== false || strpos($error, 'daily sending quota exceeded') !== false || strpos($error, 'exceeded the rate limit') !== false) {
        return 'Se alcanzó el límite diario de envío de correos de Microsoft 365. Los correos se enviarán cuando se restablezca el límite (generalmente en 24 horas).';
    }
    if (strpos($error, 'rate limit exceeded') !== false || strpos($error, 'too many') !== false || strpos($error, 'throttl') !== false) {
        return 'Se excedió el límite de velocidad de envío de Microsoft 365. Intenta de nuevo en unos minutos.';
    }
    if (strpos($error, 'authentication') !== false || strpos($error, 'credentials') !== false || strpos($error, '535') !== false) {
        return 'Error de autenticación SMTP. Verifica las credenciales del correo institucional en la configuración.';
    }
    if (strpos($error, 'connection') !== false || strpos($error, 'connect') !== false || strpos($error, 'timeout') !== false) {
        return 'No se pudo conectar al servidor de correo (Office 365). Verifica la conexión a internet y la configuración SMTP.';
    }
    if (strpos($error, 'recipient') !== false || strpos($error, 'mailbox') !== false) {
        return 'La dirección de correo del destinatario no es válida o no existe.';
    }
    
    // Error desconocido: devolver el original
    return 'Error al enviar correo: ' . $errorInfo;
}

/**
 * Envía un correo de notificación a un departamento sobre un nuevo reporte asignado
 * 
 * @param array $department Información del departamento (name, manager, email)
 * @param bool $is_new_report True si es un reporte recién creado y notificado al buzón maestro, false si es una asignación a un departamento
 * @return array ['success' => bool, 'message' => string]
 */
function sendDepartmentNotification($department, $complaint, $is_new_report = false) {
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
        
        // ========================================
        // CONFIGURACIONES ANTI-SPAM
        // ========================================
        applyAntiSpamConfig($mail, SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Destinatario (según modo de prueba)
        $recipient_email = getEmailRecipient($department['email']);
        $mail->addAddress($recipient_email, $department['manager']);
        
        // Contenido del correo
        $mail->isHTML(true);
        $folio = $complaint['folio'] ?? str_pad($complaint['id'], 6, '0', STR_PAD_LEFT);
        
        $subject_title = $is_new_report ? 'Nuevo Reporte' : 'Nuevo Reporte Asignado';
        $mail->Subject = $subject_title . ' - Folio #' . $folio;

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
        $mail->Body = generateEmailBody($department, $complaint, $inlineImagesHtml, $is_new_report);

        // Texto alternativo (sin HTML)
        $mail->AltBody = generateEmailTextBody($department, $complaint, $is_new_report);
        
        // Enviar correo
        $mail->send();
        
        $mode_text = '';
        if (isTestMode()) {
            $test_recipient = function_exists('getTestEmail') ? getTestEmail() : SMTP_USERNAME;
            $mode_text = ' (Modo Prueba - enviado a ' . $test_recipient . ')';
        }
        
        return [
            'success' => true,
            'message' => 'Correo enviado exitosamente a ' . $department['name'] . $mode_text
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => translateSmtpError($mail->ErrorInfo)
        ];
    }
}

/**
 * Genera el cuerpo del correo en HTML
 */
function generateEmailBody($department, $complaint, $inlineImagesHtml, $is_new_report = false) {
    $folio = $complaint['folio'] ?? str_pad($complaint['id'], 6, '0', STR_PAD_LEFT);
    
    // Generar URL del reporte
    $view_url = APP_URL . '/view_complaint.php?id=' . $complaint['id'];
    
    $mode_notice = '';
    if (isTestMode()) {
        $mode_notice = '<div style="background-color: #FEF3C7; border-left: 4px solid #F59E0B; padding: 12px; margin-bottom: 20px;">
            <p style="margin: 0; color: #92400E; font-weight: bold;">⚠️ MODO DE PRUEBA ACTIVADO</p>
            <p style="margin: 5px 0 0 0; color: #92400E; font-size: 14px;">Este correo debería enviarse a: ' . htmlspecialchars($department['email']) . '</p>
        </div>';
    }
    
    $title = $is_new_report ? 'Nuevo Reporte' : 'Nuevo Reporte Asignado';
    
    $html = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $title . '</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #2563EB; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
            <h1 style="margin: 0; font-size: 24px;">BUZÓN DE QUEJAS Y SUGERENCIAS</h1>
            <p style="margin: 10px 0 0 0; font-size: 14px;">TECNM / CIUDAD CONSTITUCIÓN</p>
        </div>
        
        <div style="background-color: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">
            ' . $mode_notice . '
            
            <h2 style="color: #1f2937; margin-top: 0;">Estimado/a ' . htmlspecialchars($department['manager']) . ',</h2>
            ';
            
    if ($is_new_report) {
        $html .= '<p>Se ha creado un nuevo reporte en el sistema y ha sido enviado al departamento de <strong>' . htmlspecialchars($department['name']) . '</strong>. Le solicitamos amablemente revisarlo y asignarle los departamentos correspondientes.</p>';
    } else {
        $html .= '<p>Se ha asignado un nuevo reporte al departamento de <strong>' . htmlspecialchars($department['name']) . '</strong>. Le solicitamos amablemente darle seguimiento a la brevedad posible.</p>';
    }
            
    $html .= '
            <div style="background-color: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h3 style="color: #2563EB; margin-top: 0; border-bottom: 2px solid #2563EB; padding-bottom: 10px;">
                    📋 Detalles del Reporte - Folio #' . $folio . '
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
            
            <!-- Botón de Acción -->
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . $view_url . '" style="display: inline-block; background-color: #2563EB; background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%); color: #ffffff !important; text-decoration: none; padding: 16px 32px; border-radius: 12px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3); border: none; mso-line-height-rule: exactly; line-height: 1.5;">
                    <span style="color: #ffffff !important; text-decoration: none; font-weight: bold;">🔍 Ver Reporte Completo y Dar Seguimiento</span>
                </a>
            </div>
            
            <div style="background-color: #EFF6FF; border-left: 4px solid #2563EB; padding: 12px; margin-top: 20px;">
                <p style="margin: 0; color: #1e40af; font-size: 14px;">
                    <strong>Importante:</strong> Por favor, accede al sistema usando el botón de arriba para revisar el reporte completo, agregar respuestas y actualizar el estado del caso.
                </p>
            </div>

            ' . $inlineImagesHtml . '
        </div>
        
        <div style="text-align: center; padding: 20px; color: #6b7280; font-size: 12px;">
            <p style="margin: 0;">Este es un correo automático de BUZÓN DE QUEJAS Y SUGERENCIAS - TECNM/CIUDAD CONSTITUCIÓN</p>
            <p style="margin: 5px 0 0 0;">© ' . date('Y') . ' Instituto Tecnológico Superior de Ciudad Constitución</p>
        </div>
    </body>
    </html>
    ';
    
    return $html;
}

/**
 * Genera el cuerpo del correo en texto plano
 */
function generateEmailTextBody($department, $complaint, $is_new_report = false) {
    $folio = $complaint['folio'] ?? str_pad($complaint['id'], 6, '0', STR_PAD_LEFT);
    
    // Generar URL del reporte
    $view_url = APP_URL . '/view_complaint.php?id=' . $complaint['id'];
    
    $mode_notice = '';
    if (isTestMode()) {
        $mode_notice = "\n⚠️ MODO DE PRUEBA ACTIVADO\nEste correo debería enviarse a: " . $department['email'] . "\n\n";
    }
    
    $title = $is_new_report ? 'NUEVO REPORTE' : 'NUEVO REPORTE ASIGNADO';
    $text = "ITSCC BUZÓN DE QUEJAS - " . $title . "\n\n";
    $text .= $mode_notice;
    $text .= "Estimado/a " . $department['manager'] . ",\n\n";
    
    if ($is_new_report) {
         $text .= "Se ha creado un nuevo reporte en el sistema y ha sido enviado al departamento de " . $department['name'] . ". ";
         $text .= "Le solicitamos amablemente revisarlo y asignarle los departamentos correspondientes.\n\n";
    } else {
         $text .= "Se ha asignado un nuevo reporte al departamento de " . $department['name'] . ". ";
         $text .= "Le solicitamos amablemente darle seguimiento a la brevedad posible.\n\n";
    }
    
    $text .= "DETALLES DEL REPORTE - FOLIO #" . $folio . "\n";
    $text .= "----------------------------------------\n";
    $text .= "Categoría: " . $complaint['category_name'] . "\n";
    $text .= "Fecha: " . date('d/m/Y H:i', strtotime($complaint['created_at'])) . "\n";
    $text .= "Descripción: " . substr($complaint['description'], 0, 200) . (strlen($complaint['description']) > 200 ? '...' : '') . "\n\n";
    $text .= "ACCEDER AL REPORTE:\n";
    $text .= $view_url . "\n\n";
    $text .= "Por favor, accede al sistema usando el enlace de arriba para revisar el reporte completo, agregar respuestas y actualizar el estado del caso.\n\n";
    $text .= "---\n";
    $text .= "Este es un correo automático del Sistema de Buzón de Quejas ITSCC\n";
    $text .= "© " . date('Y') . " Instituto Tecnológico Superior de Ciudad Constitución";
    
    return $text;
}

/**
 * Envía una notificación por correo al autor de una queja cuando se agrega un comentario/respuesta
 * 
 * @param array $complaint Información del reporte (id, folio, description, user_id, is_anonymous)
 * @param string $commenter_name Nombre de quien hizo el comentario
 * @param string $comment_text Texto del comentario
 * @param string $commenter_role Rol de quien comenta (admin, manager, student)
 * @return array ['success' => bool, 'message' => string]
 */
function sendCommentNotification($complaint, $commenter_name, $comment_text, $commenter_role = 'staff') {
    global $phpmailer_loaded;
    global $conn;
    
    if (!$phpmailer_loaded) {
        return [
            'success' => false,
            'message' => 'PHPMailer no está instalado.'
        ];
    }
    
    // Obtener datos del autor del reporte
    $stmt_author = $conn->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmt_author->bind_param("i", $complaint['user_id']);
    $stmt_author->execute();
    $author = $stmt_author->get_result()->fetch_assoc();
    
    if (!$author || empty($author['email'])) {
        return [
            'success' => false,
            'message' => 'No se pudo encontrar el correo del autor del reporte.'
        ];
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // Configuración SMTP
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
        
        // Anti-spam
        applyAntiSpamConfig($mail, SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Destinatario (respetando modo de prueba)
        $recipient_email = getEmailRecipient($author['email']);
        $mail->addAddress($recipient_email, $author['name']);
        
        // Datos del reporte
        $folio = $complaint['folio'] ?? str_pad($complaint['id'], 6, '0', STR_PAD_LEFT);
        $view_url = APP_URL . '/view_complaint.php?id=' . $complaint['id'];
        
        // Determinar etiqueta del rol
        $role_label = 'Personal del ITSCC';
        if ($commenter_role === 'admin') {
            $role_label = 'Administrador';
        } elseif ($commenter_role === 'manager') {
            $role_label = 'Encargado de Departamento';
        }
        
        // Asunto
        $mail->isHTML(true);
        $mail->Subject = 'Nueva Respuesta en tu Reporte - Folio #' . $folio;
        
        // Modo de prueba
        $mode_notice = '';
        if (isTestMode()) {
            $mode_notice = '<div style="background-color: #FEF3C7; border-left: 4px solid #F59E0B; padding: 12px; margin-bottom: 20px;">
                <p style="margin: 0; color: #92400E; font-weight: bold;">⚠️ MODO DE PRUEBA ACTIVADO</p>
                <p style="margin: 5px 0 0 0; color: #92400E; font-size: 14px;">Este correo debería enviarse a: ' . htmlspecialchars($author['email']) . '</p>
            </div>';
        }
        
        // Cuerpo HTML
        $comment_preview = htmlspecialchars(substr($comment_text, 0, 300));
        if (strlen($comment_text) > 300) {
            $comment_preview .= '...';
        }
        
        $mail->Body = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Nueva Respuesta en tu Reporte</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f4f7fa;">
            <div style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; padding: 25px; text-align: center;">
                    <h1 style="margin: 0; font-size: 22px;">💬 Nueva Respuesta en tu Reporte</h1>
                    <p style="margin: 8px 0 0 0; font-size: 14px; opacity: 0.9;">Folio #' . $folio . '</p>
                </div>
                
                <div style="padding: 30px;">
                    ' . $mode_notice . '
                    
                    <h2 style="color: #1f2937; margin-top: 0; font-size: 18px;">¡Hola, ' . htmlspecialchars($author['name']) . '!</h2>
                    
                    <p style="color: #4b5563;">Se ha agregado una nueva respuesta a tu reporte. Aquí tienes un resumen:</p>
                    
                    <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 20px; margin: 20px 0;">
                        <div style="margin-bottom: 12px;">
                            <span style="display: inline-block; background-color: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600;">' . htmlspecialchars($role_label) . '</span>
                        </div>
                        <p style="margin: 0 0 8px 0; font-weight: 600; color: #1f2937;">' . htmlspecialchars($commenter_name) . ' respondió:</p>
                        <p style="margin: 0; color: #374151; font-style: italic; border-left: 3px solid #10B981; padding-left: 12px;">' . nl2br($comment_preview) . '</p>
                    </div>
                    
                    <!-- Botón de Acción -->
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . $view_url . '" style="display: inline-block; background-color: #10B981; background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: #ffffff !important; text-decoration: none; padding: 14px 28px; border-radius: 10px; font-weight: bold; font-size: 15px; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3); border: none; mso-line-height-rule: exactly; line-height: 1.5;">
                            <span style="color: #ffffff !important; text-decoration: none; font-weight: bold;">📋 Ver Reporte y Responder</span>
                        </a>
                    </div>
                    
                    <div style="background-color: #EFF6FF; border-left: 4px solid #3B82F6; padding: 12px; margin-top: 20px; border-radius: 0 8px 8px 0;">
                        <p style="margin: 0; color: #1e40af; font-size: 14px;">
                            <strong>Tip:</strong> Puedes responder directamente desde el sistema haciendo clic en el botón de arriba.
                        </p>
                    </div>
                </div>
                
                <div style="text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background-color: #f9fafb;">
                    <p style="margin: 0;">Este es un correo automático de BUZÓN DE QUEJAS Y SUGERENCIAS - TECNM/CIUDAD CONSTITUCIÓN</p>
                    <p style="margin: 5px 0 0 0;">© ' . date('Y') . ' Instituto Tecnológico Superior de Ciudad Constitución</p>
                </div>
            </div>
        </body>
        </html>';
        
        // Texto alternativo
        $mail->AltBody = "NUEVA RESPUESTA EN TU REPORTE - FOLIO #" . $folio . "\n\n";
        $mail->AltBody .= "Hola, " . $author['name'] . "!\n\n";
        $mail->AltBody .= "Se ha agregado una nueva respuesta a tu reporte.\n\n";
        $mail->AltBody .= $commenter_name . " (" . $role_label . ") respondió:\n";
        $mail->AltBody .= "\"" . substr($comment_text, 0, 300) . "\"\n\n";
        $mail->AltBody .= "Ver reporte completo: " . $view_url . "\n\n";
        $mail->AltBody .= "---\n";
        $mail->AltBody .= "Este es un correo automático de BUZÓN DE QUEJAS Y SUGERENCIAS - TECNM/CIUDAD CONSTITUCIÓN\n";
        $mail->AltBody .= "© " . date('Y') . " Instituto Tecnológico Superior de Ciudad Constitución";
        
        $mail->send();
        
        $mode_text = isTestMode() ? ' (Modo Prueba - enviado a ' . SMTP_USERNAME . ')' : '';
        
        return [
            'success' => true,
            'message' => 'Notificación enviada al autor del reporte' . $mode_text
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => translateSmtpError($mail->ErrorInfo)
        ];
    }
}

/**
 * Genera el cuerpo del correo en HTML para recordatorios
 */
function generateReminderEmailBody($department, $complaints) {
    // Generar URL del dashboard
    $dashboard_url = APP_URL . '/dashboard.php';
    
    $mode_notice = '';
    if (isTestMode()) {
        $mode_notice = '<div style="background-color: #FEF3C7; border-left: 4px solid #F59E0B; padding: 12px; margin-bottom: 20px;">
            <p style="margin: 0; color: #92400E; font-weight: bold;">⚠️ MODO DE PRUEBA ACTIVADO</p>
            <p style="margin: 5px 0 0 0; color: #92400E; font-size: 14px;">Este correo debería enviarse a: ' . htmlspecialchars($department['email']) . '</p>
        </div>';
    }
    
    $title = 'Recordatorio: Reportes Sin Atender';
    
    $html = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $title . '</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #2563EB; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
            <h1 style="margin: 0; font-size: 24px;">BUZÓN DE QUEJAS Y SUGERENCIAS</h1>
            <p style="margin: 10px 0 0 0; font-size: 14px;">TECNM / CIUDAD CONSTITUCIÓN</p>
        </div>
        
        <div style="background-color: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">
            ' . $mode_notice . '
            
            <h2 style="color: #1f2937; margin-top: 0;">Estimado/a ' . htmlspecialchars($department['manager']) . ',</h2>
            <p>El departamento de <strong>' . htmlspecialchars($department['name']) . '</strong> tiene reportes asignados que aún <strong>no han sido atendidos</strong>. Le solicitamos amablemente darles seguimiento a la brevedad posible.</p>
            
            <h3 style="color: #2563EB; margin-top: 20px; border-bottom: 2px solid #2563EB; padding-bottom: 10px;">
                📋 Sus Reportes Pendientes
            </h3>
            
            <div style="margin: 20px 0;">';
            
    foreach ($complaints as $c) {
        $folio = htmlspecialchars($c['folio'] ?? str_pad($c['id'], 6, '0', STR_PAD_LEFT));
        $desc = htmlspecialchars(mb_substr($c['description'], 0, 100));
        if (mb_strlen($c['description']) > 100) $desc .= '...';
        
        $html .= '
                <div style="background-color: white; border: 1px solid #e5e7eb; border-radius: 6px; padding: 15px; margin-bottom: 10px;">
                    <div style="font-weight: bold; color: #4b5563; margin-bottom: 5px;">Folio #' . $folio . '</div>
                    <div style="color: #6b7280; font-size: 14px; margin-bottom: 5px;"><strong>Fecha:</strong> ' . date('d/m/Y H:i', strtotime($c['created_at'])) . '</div>
                    <div style="color: #374151; font-size: 14px;">' . $desc . '</div>
                </div>';
    }
            
    $html .= '
            </div>
            
            <!-- Botón de Acción -->
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . $dashboard_url . '" style="display: inline-block; background-color: #2563EB; background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%); color: #ffffff !important; text-decoration: none; padding: 16px 32px; border-radius: 12px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3); border: none; mso-line-height-rule: exactly; line-height: 1.5;">
                    <span style="color: #ffffff !important; text-decoration: none; font-weight: bold;">📊 Ir al Dashboard</span>
                </a>
            </div>
            
            <div style="background-color: #EFF6FF; border-left: 4px solid #2563EB; padding: 12px; margin-top: 20px;">
                <p style="margin: 0; color: #1e40af; font-size: 14px;">
                    <strong>Importante:</strong> Acceda al Dashboard usando el botón de arriba para revisar todos los reportes correspondientes a su departamento.
                </p>
            </div>
        </div>
        
        <div style="text-align: center; padding: 20px; color: #6b7280; font-size: 12px;">
            <p style="margin: 0;">Este es un correo automático de BUZÓN DE QUEJAS Y SUGERENCIAS - TECNM/CIUDAD CONSTITUCIÓN</p>
            <p style="margin: 5px 0 0 0;">© ' . date('Y') . ' Instituto Tecnológico Superior de Ciudad Constitución</p>
        </div>
    </body>
    </html>
    ';
    
    return $html;
}

/**
 * Genera el cuerpo del correo en texto plano para recordatorios
 */
function generateReminderEmailTextBody($department, $complaints) {
    $dashboard_url = APP_URL . '/dashboard.php';
    
    $text = "BUZÓN DE QUEJAS Y SUGERENCIAS\n";
    $text .= "TECNM / CIUDAD CONSTITUCIÓN\n\n";
    
    if (isTestMode()) {
        $text .= "[MODO DE PRUEBA ACTIVADO]\n";
        $text .= "Este correo debería enviarse a: " . $department['email'] . "\n\n";
    }
    
    $text .= "Estimado/a " . $department['manager'] . ",\n\n";
    $text .= "El departamento de " . $department['name'] . " tiene reportes asignados que aún NO han sido atendidos. Le solicitamos amablemente darles seguimiento a la brevedad posible.\n\n";
    
    $text .= "Sus Reportes Pendientes:\n";
    $text .= "--------------------------------------------------\n";
    
    foreach ($complaints as $c) {
        $folio = $c['folio'] ?? str_pad($c['id'], 6, '0', STR_PAD_LEFT);
        $text .= "Folio #" . $folio . "\n";
        $text .= "Fecha: " . date('d/m/Y H:i', strtotime($c['created_at'])) . "\n";
        
        $desc = mb_substr(strip_tags($c['description']), 0, 100);
        if (mb_strlen(strip_tags($c['description'])) > 100) $desc .= '...';
        
        $text .= "Descripción: " . $desc . "\n\n";
    }
    
    $text .= "--------------------------------------------------\n\n";
    $text .= "Por favor, visite el Dashboard para dar seguimiento:\n";
    $text .= $dashboard_url . "\n\n";
    
    $text .= "Este es un correo automático de BUZÓN DE QUEJAS Y SUGERENCIAS - TECNM/CIUDAD CONSTITUCIÓN\n";
    
    return $text;
}

/**
 * Envía un correo recordatorio a un departamento con todos sus reportes pendientes
 */
function sendDepartmentReminderEmail($department, $complaints) {
    global $phpmailer_loaded;
    
    if (!$phpmailer_loaded) {
        return [
            'success' => false,
            'message' => 'PHPMailer no está instalado.'
        ];
    }
    
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        // Remitente
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Anti-spam
        applyAntiSpamConfig($mail, SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Destinatario
        $recipient_email = getEmailRecipient($department['email']);
        $mail->addAddress($recipient_email, $department['manager']);
        
        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = 'Recordatorio: Reportes Sin Atender - ' . $department['name'];
        
        $mail->Body = generateReminderEmailBody($department, $complaints);
        $mail->AltBody = generateReminderEmailTextBody($department, $complaints);
        
        // Enviar correo
        $mail->send();
        
        $mode_text = '';
        if (isTestMode()) {
            $test_recipient = function_exists('getTestEmail') ? getTestEmail() : SMTP_USERNAME;
            $mode_text = ' (Modo Prueba - enviado a ' . $test_recipient . ')';
        }
        
        return [
            'success' => true,
            'message' => 'Correo recordatorio enviado a ' . $department['name'] . $mode_text
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => translateSmtpError($mail->ErrorInfo)
        ];
    }
}
?>
