<?php
require_once 'config/microsoft_auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generar estado aleatorio para seguridad CSRF
$_SESSION['oauth_state'] = bin2hex(random_bytes(16));

// Guardar URL de redirección si existe (para volver después del login)
if (isset($_GET['redirect']) && preg_match('/^[a-zA-Z0-9_\-\/\.\?\=\&\%]+$/', $_GET['redirect'])) {
    $_SESSION['login_redirect'] = $_GET['redirect'];
}

// Parámetros de autorización
$params = [
    'client_id' => MS_CLIENT_ID,
    'response_type' => 'code',
    'redirect_uri' => MS_REDIRECT_URI,
    'scope' => 'User.Read openid profile email',
    'response_mode' => 'query',
    'state' => $_SESSION['oauth_state'],
    'prompt' => 'select_account',
    'domain_hint' => 'cdconstitucion.tecnm.mx'
];

// Construir URL
$login_url = 'https://login.microsoftonline.com/' . MS_TENANT_ID . '/oauth2/v2.0/authorize?' . http_build_query($params);

// Redireccionar
header('Location: ' . $login_url);
exit;
?>
