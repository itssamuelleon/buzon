<?php
/**
 * Función para enviar correo de verificación con código de 6 dígitos
 */

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/config/email_config.php';
require_once __DIR__ . '/config/email_antispam.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail($email, $name, $code, $type = 'register') {
    global $conn;
    
    // Check if test mode is enabled
    $stmt_test = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'test_mode'");
    $stmt_test->execute();
    $result_test = $stmt_test->get_result();
    $test_mode = false;
    $test_email = '';
    
    if ($row_test = $result_test->fetch_assoc()) {
        $test_mode = $row_test['setting_value'] == '1';
    }
    
    // If test mode is enabled, get test email
    if ($test_mode) {
        $stmt_email = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'test_email'");
        $stmt_email->execute();
        $result_email = $stmt_email->get_result();
        if ($row_email = $result_email->fetch_assoc()) {
            $test_email = $row_email['setting_value'];
        }
    }
    
    // Determine actual recipient
    $actual_recipient = ($test_mode && !empty($test_email)) ? $test_email : $email;
    $original_email = $email;
    
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
        $mail->setFrom(SMTP_USERNAME, 'ITSCC Buzón Digital');
        $mail->addAddress($actual_recipient, $name);
        
        // ========================================
        // CONFIGURACIONES ANTI-SPAM
        // ========================================
        applyAntiSpamConfig($mail, SMTP_USERNAME, 'ITSCC Buzón Digital');
        
        // Configurar contenido según el tipo
        $subject_prefix = ($type === 'password_reset') ? 'Recuperación de Contraseña' : 'Código de Verificación';
        $title = ($type === 'password_reset') ? '🔐 Recuperación de Contraseña' : '🔐 Verificación de Correo';
        
        $intro_text = "";
        if ($type === 'password_reset') {
            $intro_text = "Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en el <strong>Buzón Digital del ITSCC</strong>.";
            $action_text = "Ingresa el siguiente código para continuar con el proceso de recuperación:";
        } else {
            $intro_text = "Gracias por registrarte en el <strong>Buzón Digital del ITSCC</strong>. Para completar tu registro, necesitamos verificar tu correo electrónico.";
            $action_text = "Ingresa el siguiente código de verificación en la página de registro:";
        }

        // Add test mode notice to subject if in test mode
        $subject = $subject_prefix . ' - ITSCC Buzón Digital';
        if ($test_mode && $actual_recipient !== $original_email) {
            $subject = '[MODO PRUEBA] ' . $subject;
        }
        
        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = $subject;
        
        // Add test mode notice to body if applicable
        $test_notice = '';
        if ($test_mode && $actual_recipient !== $original_email) {
            $test_notice = "
            <div style='background-color: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin-bottom: 20px;'>
                <p style='margin: 0; color: #856404; font-weight: 600;'>
                    ⚠️ MODO DE PRUEBA ACTIVADO
                </p>
                <p style='margin: 5px 0 0 0; color: #856404; font-size: 14px;'>
                    Este correo estaba destinado a: <strong>{$original_email}</strong>
                </p>
            </div>
            ";
        }
        
        // Cuerpo HTML del correo
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f7fa; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px; text-align: center; }
                .header h1 { color: #ffffff; margin: 0; font-size: 28px; font-weight: 700; }
                .content { padding: 40px 30px; }
                .code-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 30px; text-align: center; margin: 30px 0; }
                .code { font-size: 48px; font-weight: 900; color: #ffffff !important; letter-spacing: 8px; font-family: 'Courier New', monospace; text-decoration: none; }
                .info { background-color: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
                .button { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 20px 0; }
                @media only screen and (max-width: 600px) {
                    .content { padding: 20px 15px; }
                    .code { font-size: 36px; letter-spacing: 4px; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$title}</h1>
                </div>
                <div class='content'>
                    {$test_notice}
                    <h2 style='color: #333; margin-top: 0;'>¡Hola, " . htmlspecialchars($name) . "!</h2>
                    <p style='font-size: 16px; color: #555;'>
                        {$intro_text}
                    </p>
                    
                    <p style='font-size: 16px; color: #555;'>
                        {$action_text}
                    </p>
                    
                    <div class='code-box' style='background-color: #667eea; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 30px; text-align: center; margin: 30px 0; border: none;'>
                        <div class='code' style='font-size: 48px; font-weight: 900; color: #ffffff !important; letter-spacing: 8px; font-family: Courier New, monospace; text-decoration: none; line-height: 1.5; mso-line-height-rule: exactly;'>
                            <span style='color: #ffffff !important; text-decoration: none; font-weight: 900;'>" . $code . "</span>
                        </div>
                    </div>
                    
                    <div class='info'>
                        <p style='margin: 0; color: #666;'>
                            <strong>⏱️ Este código expira en 15 minutos.</strong><br>
                            Si no solicitaste esta acción, puedes ignorar este correo de forma segura.
                        </p>
                    </div>
                    
                    <p style='font-size: 14px; color: #777; margin-top: 30px;'>
                        Si tienes alguna pregunta o necesitas ayuda, no dudes en contactarnos.
                    </p>
                </div>
                <div class='footer'>
                    <p style='margin: 5px 0;'>
                        <strong>Instituto Tecnológico Superior de Ciudad Constitución</strong>
                    </p>
                    <p style='margin: 5px 0;'>
                        Buzón de Quejas, Sugerencias y Felicitaciones
                    </p>
                    <p style='margin: 15px 0 5px 0; color: #999;'>
                        © " . date('Y') . " ITSCC. Todos los derechos reservados.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Cuerpo alternativo en texto plano
        $test_notice_plain = '';
        if ($test_mode && $actual_recipient !== $original_email) {
            $test_notice_plain = "
*** MODO DE PRUEBA ACTIVADO ***
Este correo estaba destinado a: {$original_email}
***********************************

";
        }
        
        $mail->AltBody = "{$test_notice_plain}Hola, " . $name . "!

" . strip_tags($intro_text) . "

" . strip_tags($action_text) . "

Tu código es: " . $code . "

Este código expira en 15 minutos.

Si no solicitaste esta acción, puedes ignorar este correo.

---
Instituto Tecnológico Superior de Ciudad Constitución
Buzón de Quejas, Sugerencias y Felicitaciones
© " . date('Y') . " ITSCC
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        $error_info = $mail->ErrorInfo;
        error_log("Error al enviar correo de verificación: {$error_info}");
        
        // Traducir error SMTP si la función existe
        if (function_exists('translateSmtpError')) {
            $friendly_error = translateSmtpError($error_info);
        } else {
            // Detección básica del límite diario
            if (stripos($error_info, 'daily user sending limit exceeded') !== false || stripos($error_info, 'daily sending quota exceeded') !== false) {
                $friendly_error = 'Se alcanzó el límite diario de envío de correos de Gmail (500/día). Intenta de nuevo más tarde.';
            } else {
                $friendly_error = $error_info;
            }
        }
        
        // Registrar el fallo en la cola de emails para que aparezca en el dashboard
        if (isset($conn)) {
            $type_label = ($type === 'password_reset') ? 'Verificación (recuperar contraseña)' : 'Verificación (registro)';
            $full_error = $type_label . ': ' . $friendly_error;
            $stmt_fail = $conn->prepare("INSERT INTO email_queue (complaint_id, department_id, status, attempts, max_attempts, error_message) VALUES (0, 0, 'failed', 1, 1, ?)");
            if ($stmt_fail) {
                $stmt_fail->bind_param("s", $full_error);
                $stmt_fail->execute();
            }
        }
        
        // Guardar el error amigable en sesión para mostrarlo en la página
        if (isset($_SESSION)) {
            $_SESSION['email_error_detail'] = $friendly_error;
        }
        
        return false;
    }
}
