<?php
require_once 'config/microsoft_auth.php';
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar estado para prevenir CSRF
if (empty($_GET['state'])) {
    die('Error de seguridad: No se recibió el estado.');
}
if (empty($_SESSION['oauth_state']) || ($_GET['state'] !== $_SESSION['oauth_state'])) {
    unset($_SESSION['oauth_state']);
    die('Error de seguridad: Estado inválido. Por favor, intenta de nuevo. <a href="login.php">Volver al login</a>');
}
unset($_SESSION['oauth_state']); // Limpiar el state después de validar

// Validar si se recibió el código
if (empty($_GET['code'])) {
    die('Error: No se recibió el código de autorización.');
}

$code = $_GET['code'];

// Intercambiar código por token de acceso
$token_url = 'https://login.microsoftonline.com/' . MS_TENANT_ID . '/oauth2/v2.0/token';
$token_data = [
    'client_id' => MS_CLIENT_ID,
    'client_secret' => MS_CLIENT_SECRET,
    'code' => $code,
    'redirect_uri' => MS_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    $curl_error = curl_error($ch);
    curl_close($ch);
    die('Error de conexión al obtener token: ' . htmlspecialchars($curl_error));
}
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$token_response = json_decode($response, true);

if ($http_code !== 200 || isset($token_response['error'])) {
    $error_msg = $token_response['error_description'] ?? 'Error desconocido al obtener el token.';
    die('Error de autenticación con Microsoft: ' . $error_msg);
}

$access_token = $token_response['access_token'];

// Obtener perfil del usuario desde Microsoft Graph
$graph_url = 'https://graph.microsoft.com/v1.0/me';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $graph_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    $curl_error = curl_error($ch);
    curl_close($ch);
    die('Error de conexión al obtener perfil: ' . htmlspecialchars($curl_error));
}
curl_close($ch);

$user_profile = json_decode($response, true);

if (isset($user_profile['error'])) {
    die('Error al obtener perfil de usuario: ' . $user_profile['error']['message']);
}

$email = $user_profile['mail'] ?? $user_profile['userPrincipalName'];
$name = $user_profile['displayName'];

// Obtener foto de perfil desde Microsoft Graph
$profile_photo_base64 = null;
$photo_url = 'https://graph.microsoft.com/v1.0/me/photo/$value';
$ch_photo = curl_init();
curl_setopt($ch_photo, CURLOPT_URL, $photo_url);
curl_setopt($ch_photo, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token
]);
curl_setopt($ch_photo, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_photo, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch_photo, CURLOPT_TIMEOUT, 15);
$photo_data = curl_exec($ch_photo);
$http_code = curl_getinfo($ch_photo, CURLINFO_HTTP_CODE);
curl_close($ch_photo);

// Si la foto existe (código 200), convertirla a Base64
if ($http_code === 200 && $photo_data) {
    $profile_photo_base64 = base64_encode($photo_data);
}


// Validar dominio institucional
// Primero verificamos si la restricción está desactivada en la configuración
$stmt_check = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'disable_institutional_email_check'");
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$disable_email_check = false;
if ($row_check = $result_check->fetch_assoc()) {
    $disable_email_check = $row_check['setting_value'] == '1';
}

// Si la restricción está activa, verificamos el dominio
if (!$disable_email_check && substr(strtolower($email), -strlen('@cdconstitucion.tecnm.mx')) !== '@cdconstitucion.tecnm.mx') {
    die('<div style="font-family: sans-serif; text-align: center; padding: 50px;">
            <h1 style="color: #e53e3e;">Acceso Denegado</h1>
            <p>Solo se permiten correos institucionales del TecNM Campus Ciudad Constitución (@cdconstitucion.tecnm.mx).</p>
            <p>Tu correo: <strong>' . htmlspecialchars($email) . '</strong></p>
            <a href="login.php">Volver al inicio de sesión</a>
         </div>');
}

// Verificar si el usuario ya existe en la base de datos
$stmt = $conn->prepare("SELECT id, role, name FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Usuario existe: Iniciar sesión
    $user = $result->fetch_assoc();
    
    // Actualizar nombre y foto de perfil si son diferentes (Sincronizar con Microsoft)
    $needs_update = false;
    $update_fields = [];
    $update_values = [];
    $update_types = '';
    
    if ($user['name'] !== $name) {
        $update_fields[] = 'name = ?';
        $update_values[] = $name;
        $update_types .= 's';
        $needs_update = true;
        $user['name'] = $name;
    }
    
    // Actualizar foto de perfil si existe una nueva
    if ($profile_photo_base64 !== null) {
        $update_fields[] = 'profile_photo = ?';
        $update_values[] = $profile_photo_base64;
        $update_types .= 's';
        $needs_update = true;
    }
    
    if ($needs_update) {
        $update_sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $update_values[] = $user['id'];
        $update_types .= 'i';
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param($update_types, ...$update_values);
        $update_stmt->execute();
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name']; 
    $_SESSION['email'] = $email;
    $_SESSION['role'] = $user['role'];
} else {
    // Usuario no existe: Registrar nuevo usuario
    
    // Determinar rol (verificar si es encargado de departamento)
    $role = 'student';
    $dept_stmt = $conn->prepare("SELECT id FROM departments WHERE email = ?");
    $dept_stmt->bind_param("s", $email);
    $dept_stmt->execute();
    if ($dept_stmt->get_result()->num_rows > 0) {
        $role = 'manager';
    }

    // Generar contraseña aleatoria segura (el usuario usará Microsoft para entrar)
    $random_password = bin2hex(random_bytes(16));
    $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, profile_photo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $email, $hashed_password, $role, $profile_photo_base64);
    
    if ($stmt->execute()) {
        $_SESSION['user_id'] = $stmt->insert_id;
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;
    } else {
        die('Error al registrar el usuario en la base de datos: ' . $conn->error);
    }
}

// Redireccionar al dashboard/inicio
header('Location: index.php');
exit;
?>
