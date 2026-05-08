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

$response = array('success' => false);

// Check if user_id is provided
// if (!isset($_GET['user_id'])) {
//     $response['message'] = 'Missing user_id parameter';
//     echo json_encode($response);
//     exit;
// }

// $user_id = intval($_GET['user_id']);



// Prepare SQL query
$sql = "SELECT  name, email, phone, profile_image, user_type, relationship, 
               food_preference, occupation, ID_images, created_at, updated_at, 
               is_verified, is_active, is_property_added,emergency_contact,emergency_contact_name,emergency_contact_relation, aadhaar_card, pan_card
        FROM USERS 
        WHERE user_id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $response['message'] = 'Database error: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch promocode for the user (if any)
$promocode = null;
$promo_sql = "SELECT code FROM PROMOCODES WHERE user_id = ? LIMIT 1";
$promo_stmt = $conn->prepare($promo_sql);

if ($promo_stmt) {
    $promo_stmt->bind_param("i", $user_id);
    $promo_stmt->execute();
    $promo_result = $promo_stmt->get_result();
    if ($promo_result->num_rows > 0) {
        $promo_row = $promo_result->fetch_assoc();
        $promocode = $promo_row['code'];
    }
}

// Check if user exists
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $response['success'] = true;
    $response['data'] = array_merge($user, [
        "promocode" => $promocode // null if not found
    ]);
} else {
    $response['message'] = 'User not found';
}

echo json_encode($response);
?>