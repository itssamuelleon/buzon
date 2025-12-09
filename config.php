<?php
session_start();

require_once __DIR__ . '/env_loader.php';
loadEnv(__DIR__ . '/.env');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '1234567890');
define('DB_NAME', getenv('DB_NAME') ?: 'buzon_quejas');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function canAccessDashboard() {
    global $conn;
    
    // Admins always have access
    if (isAdmin()) {
        return true;
    }
    
    // Managers always have access (they'll see filtered results)
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager') {
        return true;
    }
    
    // Check if dashboard access is restricted
    $stmt = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'restrict_dashboard_access'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $is_restricted = $row['setting_value'] == '1';
        // If restricted, only admins can access (already returned true above)
        // If not restricted, anyone logged in can access
        return !$is_restricted && isLoggedIn();
    }
    
    // Default: if setting doesn't exist, allow access to logged in users
    return isLoggedIn();
}
?>
