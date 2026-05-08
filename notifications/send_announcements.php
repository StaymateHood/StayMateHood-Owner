<?php
header('Content-Type: application/json');
include '../dbconn.php';
require_once '../vendor/autoload.php';
require '../env.php'; // must define $secret_key

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
    $admin_id = $decoded->sub ?? null;

    if (!$admin_id) {
        echo json_encode(["success" => false, "message" => "Invalid token (missing user_id)"]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Token error: " . $e->getMessage()]);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method. Use POST."]);
    exit;
}

// Validate form fields
$title = $_POST['title'] ?? '';
$message = $_POST['message'] ?? '';
$notification_type = $_POST['notification_type'] ?? 'Announcement';
$property_name = $_POST['property_name'] ??'';


if (empty($title) || empty($message)) {
    echo json_encode(["success" => false, "message" => "Title and message are required"]);
    exit;
}

// Insert global announcement
$insert_sql = "INSERT INTO NOTIFICATIONS (user_id, title, message, created_at, is_read, notification_type, is_global, property_name)
               VALUES (NULL, ?, ?, NOW(), 0, ?, 1 , ?)";
$stmt = $conn->prepare($insert_sql);

if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Database error: " . $conn->error]);
    exit;
}

$stmt->bind_param("ssss", $title, $message, $notification_type , $property_name);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = "Global announcement sent successfully.";
} else {
    $response['message'] = "Failed to send announcement.";
}

echo json_encode($response);
?>
