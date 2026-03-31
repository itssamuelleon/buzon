<?php
/**
 * Utilidad interna de validación de metadata
 */
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$complaint_id = isset($input['complaint_id']) ? intval($input['complaint_id']) : 0;

if ($complaint_id === 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

$stmt = $conn->prepare("
    SELECT c.is_anonymous, c.user_id, u.name, u.email, u.profile_photo
    FROM complaints c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.id = ?
");
$stmt->bind_param("i", $complaint_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    echo json_encode(['success' => false, 'error' => 'No encontrado']);
    exit;
}

if (!$result['is_anonymous']) {
    echo json_encode(['success' => false, 'error' => 'N/A']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => [
        'name' => $result['name'],
        'email' => $result['email'],
        'has_photo' => !empty($result['profile_photo']),
        'photo' => !empty($result['profile_photo']) ? $result['profile_photo'] : null,
        'initial' => strtoupper(substr($result['name'], 0, 1))
    ]
]);
