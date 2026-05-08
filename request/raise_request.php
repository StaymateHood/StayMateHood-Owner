<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once '../dbconn.php';
require '../env.php'; // Contains $secret_key
require_once '../vendor/autoload.php';

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
        echo json_encode(["success" => false, "message" => "Invalid token (missing user ID)"]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Token error: " . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Only POST requests are allowed']);
    exit;
}

//$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$user_type = isset($_POST['user_type']) ? trim($_POST['user_type']) : '';
$category = isset($_POST['category']) ? trim($_POST['category']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';

if ($user_id <= 0 || empty($user_type) || empty($category)) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$sql = "INSERT INTO SERVICE_REQUEST (user_id, user_type, category, description) 
        VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isss", $user_id, $user_type, $category, $description);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Request submitted successfully']);
} else {
    echo json_encode(['error' => 'Database insert failed']);
}
$stmt->close();
$conn->close();
?>
