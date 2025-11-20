<?php
/**
 * Función para enviar correo de verificación con código de 6 dígitos
 */

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/config/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail($email, $name, $code) {
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
        $mail->addAddress($email, $name);
        
        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = 'Código de Verificación - ITSCC Buzón Digital';
        
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
                .code { font-size: 48px; font-weight: 900; color: #ffffff; letter-spacing: 8px; font-family: 'Courier New', monospace; }
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
                    <h1>🔐 Verificación de Correo</h1>
                </div>
                <div class='content'>
                    <h2 style='color: #333; margin-top: 0;'>¡Hola, " . htmlspecialchars($name) . "!</h2>
                    <p style='font-size: 16px; color: #555;'>
                        Gracias por registrarte en el <strong>Buzón Digital del ITSCC</strong>. Para completar tu registro, necesitamos verificar tu correo electrónico.
                    </p>
                    
                    <p style='font-size: 16px; color: #555;'>
                        Ingresa el siguiente código de verificación en la página de registro:
                    </p>
                    
                    <div class='code-box'>
                        <div class='code'>" . $code . "</div>
                    </div>
                    
                    <div class='info'>
                        <p style='margin: 0; color: #666;'>
                            <strong>⏱️ Este código expira en 15 minutos.</strong><br>
                            Si no solicitaste este registro, puedes ignorar este correo de forma segura.
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
        $mail->AltBody = "
Hola, " . $name . "!

Gracias por registrarte en el Buzón Digital del ITSCC.

Tu código de verificación es: " . $code . "

Este código expira en 15 minutos.

Si no solicitaste este registro, puedes ignorar este correo.

---
Instituto Tecnológico Superior de Ciudad Constitución
Buzón de Quejas, Sugerencias y Felicitaciones
© " . date('Y') . " ITSCC
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar correo de verificación: {$mail->ErrorInfo}");
        return false;
    }
}
