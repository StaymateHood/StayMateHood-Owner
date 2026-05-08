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

$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';


if ($user_id <= 0 || $amount <= 0 || !$payment_method) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit();
}

// Get latest balance
$sql = "SELECT balance FROM wallet WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($last_balance);
$stmt->fetch();
$stmt->close();

$new_balance = $last_balance + $amount;

// Insert new wallet transaction
$sql = "INSERT INTO wallet (user_id, balance, payment_method, payment_type, amount, created_at, updated_at)
        VALUES (?, ?, ?, 'Credit', ?, NOW(), NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("idss", $user_id, $new_balance, $payment_method, $amount);
$stmt->execute();
$stmt->close();

echo json_encode([
    'status' => 'success',
    'message' => 'Amount added successfully',
    'new_balance' => floatval($new_balance)
]);
$conn->close();
?>
