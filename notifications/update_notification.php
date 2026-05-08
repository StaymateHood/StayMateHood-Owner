<?php
header('Content-Type: application/json');
include '../dbconn.php';
require_once '../vendor/autoload.php';
require '../env.php'; // Must define $secret_key

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$response = ["success" => false];

// JWT Authentication
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    echo json_encode(["success" => false, "message" => "Authorization token missing"]);
    exit;
}

$token = str_replace('Bearer ', '', $authHeader);
try {
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    $user_id = $decoded->sub ?? null;
    if (!$user_id) {
        echo json_encode(["success" => false, "message" => "Invalid token (missing user_id)"]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Token error: " . $e->getMessage()]);
    exit;
}

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing user_id']);
    exit;
}

$query = $conn->prepare("UPDATE NOTIFICATIONS SET is_read = 1 WHERE notification_id = ?");
$query->bind_param("i", $_POST['notification_id']);
if ($query->execute()) {
    if ($query->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No notification found with this ID Or Already Updated']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update notification']);
}


?>
