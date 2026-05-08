<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

header('Content-Type: application/json');

//$user_id = $_POST['user_id'] ?? '';
$user_type = $_POST['user_type'] ?? '';
$comment = $_POST['comment'] ?? '';
$profile = $_POST['profile'] ?? '';
$name = $_POST['name'] ?? '';

if ($user_id && $user_type && $comment && $name) {
    $stmt = $conn->prepare("INSERT INTO COMMUNITY_CHAT (user_id, user_type, comment, profile, name) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $user_type, $comment, $profile, $name);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Comment added successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to add comment"]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
}
?>
