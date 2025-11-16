<?php
// Configuración de correo electrónico con PHPMailer

// Credenciales de Gmail
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'itssamuelleon@gmail.com');
define('SMTP_PASSWORD', 'kkco yeai eedd vctb');
define('SMTP_FROM_EMAIL', 'itssamuelleon@gmail.com');
define('SMTP_FROM_NAME', 'ITSCC Buzón de Quejas');

// Función para obtener configuración de modo de prueba
function isTestMode() {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'test_mode'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'] == '1';
    }
    return false; // Por defecto desactivado
}

// Función para obtener el email de destino según el modo
function getEmailRecipient($department_email) {
    if (isTestMode()) {
        return SMTP_USERNAME; // En modo prueba, enviar al correo de PHPMailer
    }
    return $department_email; // En modo normal, enviar al departamento
}
?>
