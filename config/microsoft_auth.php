<?php
require_once __DIR__ . '/../config.php';

// Configuración de Microsoft Auth
// Asegúrate de agregar estas variables a tu archivo .env
define('MS_CLIENT_ID', getenv('MS_CLIENT_ID') ?: '');
define('MS_CLIENT_SECRET', getenv('MS_CLIENT_SECRET') ?: '');
define('MS_REDIRECT_URI', getenv('MS_REDIRECT_URI') ?: 'http://localhost/buzon/callback_microsoft.php');
define('MS_TENANT_ID', getenv('MS_TENANT_ID') ?: 'common'); // 'common' para multitenant o el ID del tenant específico

if (empty(MS_CLIENT_ID) || empty(MS_CLIENT_SECRET)) {
    die('Por favor configura MS_CLIENT_ID y MS_CLIENT_SECRET en tu archivo .env');
}
?>
