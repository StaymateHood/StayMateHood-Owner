<?php
header("Content-Type: application/json");
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


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $required_fields = [
        'code', 'description', 'discount_amount',
        'minimum_booking_amount', 'valid_from',
        'valid_to', 'usage_limit'
    ];

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field])) {
            $response['message'] = "Missing field: $field";
            echo json_encode($response);
            exit;
        }
    }

    $code = $_POST['code'];
    $description = $_POST['description'];
    $discount_amount = floatval($_POST['discount_amount']);
    $minimum_booking_amount = floatval($_POST['minimum_booking_amount']);
    $valid_from = $_POST['valid_from'];
    $valid_to = $_POST['valid_to'];
    $usage_limit = intval($_POST['usage_limit']);
    //$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;

    // Check if code already exists
    $check_sql = "SELECT code FROM PROMOCODES WHERE code = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $code);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        $response['message'] = 'Promocode already exists.';
        echo json_encode($response);
        exit;
    }

    $insert_sql = "INSERT INTO PROMOCODES (
        code, user_id, description, discount_amount, minimum_booking_amount,
        valid_from, valid_to, usage_limit, current_usage, is_active,
        created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 1, NOW(), NOW())";

    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param(
    "sisddssi",
    $code,
    $user_id,
    $description,
    $discount_amount,
    $minimum_booking_amount,
    $valid_from,   // now correctly treated as string
    $valid_to,     // now correctly treated as string
    $usage_limit
     );


    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Promocode created successfully.';
    } else {
        $response['message'] = 'Failed to create promocode: ' . $stmt->error;
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>
