<?php
// Configuración de correo electrónico con PHPMailer

// Credenciales de Microsoft 365 (Institucional)
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'buzon@cdconstitucion.tecnm.mx');
define('SMTP_PASSWORD', 'Calidad2021');
define('SMTP_FROM_EMAIL', 'buzon@cdconstitucion.tecnm.mx');
define('SMTP_FROM_NAME', 'Buzón de Quejas ITSCC');

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
