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

$tquery = $conn->prepare("SELECT COUNT(notification_id) as total 
                         FROM NOTIFICATIONS WHERE user_id = ?");
$tquery->bind_param("i", $user_id);
$tquery->execute();
$tresult = $tquery->get_result();
$row = $tresult->fetch_assoc();
$totalcount = $row['total'] ?? 0;
$tquery->close();

$rquery = $conn->prepare("SELECT COUNT(notification_id) as total 
                         FROM NOTIFICATIONS WHERE user_id = ? AND is_read = 1");
$rquery->bind_param("i", $user_id);
$rquery->execute();
$rresult = $rquery->get_result();
$rrow = $rresult->fetch_assoc();
$readcount = $rrow['total'] ?? 0;
$rquery->close();

$uquery = $conn->prepare("SELECT COUNT(notification_id) as total 
                         FROM NOTIFICATIONS WHERE user_id = ? AND is_read = 0");
$uquery->bind_param("i", $user_id);
$uquery->execute();
$uresult = $uquery->get_result();
$urow = $uresult->fetch_assoc();
$unreadcount = $urow['total'] ?? 0;
$uquery->close();

$query = $conn->prepare("SELECT notification_id, title, message, created_at, is_read, notification_type 
                         FROM NOTIFICATIONS 
                         WHERE user_id = ? 
                         ORDER BY created_at DESC");
$query->bind_param("i", $user_id);
$query->execute();

$result = $query->get_result();
$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode([
    'success' => true,
    'totalcount' => $totalcount,
    'readcount' => $readcount,
    'unreadcount' => $unreadcount,
    'notifications' => $notifications
]);
?>
