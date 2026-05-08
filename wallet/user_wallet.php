<?php
header('Content-Type: application/json');
require_once '../dbconn.php';
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
    echo json_encode(['status' => 'error', 'message' => 'Invalid user_id']);
    exit();
}

// Get latest balance
$sql = "SELECT balance FROM wallet WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();

// Get transaction history
$transactions = [];
$sql = "SELECT payment_method, payment_type, amount, created_at FROM wallet WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

echo json_encode([
    'status' => 'success',
    'user_id' => $user_id,
    'balance' => floatval($balance),
    'transactions' => $transactions
]);
$conn->close();
?>
