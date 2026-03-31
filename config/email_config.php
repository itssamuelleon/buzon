<?php
// Configuración de correo electrónico con PHPMailer

// === CONFIGURACIÓN DE CORREO (Desde .env) ===
define('SMTP_HOST', getenv('SMTP_HOST'));
define('SMTP_PORT', getenv('SMTP_PORT'));
define('SMTP_USERNAME', getenv('SMTP_USERNAME'));
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD'));
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL'));
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME'));

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

// Función para obtener el correo de pruebas configurado
function getTestEmail() {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'test_email'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return SMTP_USERNAME; // Fallback al correo SMTP por defecto
}

// Función para obtener el email de destino según el modo
function getEmailRecipient($department_email) {
    if (isTestMode()) {
        return getTestEmail(); // En modo prueba, enviar al correo de pruebas configurado
    }
    return $department_email; // En modo normal, enviar al departamento
}

// Función para verificar si las notificaciones al Buzón están activadas
function shouldNotifyBuzon() {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'notify_buzon_on_new_report'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'] == '1';
    }
    return false; // Por defecto desactivado
}
?>
