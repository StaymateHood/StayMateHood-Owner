<?php
header('Content-Type: application/json');
include '../dbconn.php';
require '../vendor/autoload.php';
require '../env.php'; // Contains $secret_key
use Firebase\JWT\JWT;

// Get form data
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$type = $_POST['type'] ?? '';

// Basic validation
if (empty($email) || empty($password) || empty($type)) {
    echo json_encode([
        "success" => false,
        "message" => "Email, password, and type are required."
    ]);
    exit;
}

// Check user by email
$sql = "SELECT * FROM USERS WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "User not found."
    ]);
    exit;
}

$user = $result->fetch_assoc();

// Check user type
if ($user['user_type'] !== $type) {
    echo json_encode([
        "success" => false,
        "message" => "User not registered under this type."
    ]);
    exit;
}

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid password."
    ]);
    exit;
}

// Check if account is active
if (!$user['is_active']) {
    echo json_encode([
        "success" => false,
        "message" => "Account is inactive. Please contact admin."
    ]);
    exit;
}

// Fetch promocode for the user (if any)
$promocode = null;
$promo_sql = "SELECT code FROM PROMOCODES WHERE user_id = ? LIMIT 1";
$promo_stmt = $conn->prepare($promo_sql);
$promo_stmt->bind_param("i", $user['user_id']);
$promo_stmt->execute();
$promo_result = $promo_stmt->get_result();

if ($promo_result->num_rows > 0) {
    $promo_row = $promo_result->fetch_assoc();
    $promocode = $promo_row['code'];
}

// JWT token generation
$issuedAt = time();
$expirationTime = $issuedAt + (48 * 60 * 60); // 48 hours token validity

$payload = [
    'iss' => 'yourdomain.com',
    'iat' => $issuedAt,
    'sub' => $user['user_id'],
    'email' => $user['email'],
    'user_type' => $user['user_type']
];

$jwt = JWT::encode($payload, $secret_key, 'HS256');

// Successful login response with JWT token
echo json_encode([
    "success" => true,
    "message" => "Login successful.",
    "token" => $jwt,
    "user" => [
        // "user_id" => $user['user_id'],
        "name" => $user['name'],
        "email" => $user['email'],
        "phone" => $user['phone'],
        "user_type" => $user['user_type'],
        "is_verified" => (bool)$user['is_verified'],
        "is_property_added" => $user['is_property_added'],
        "profile_image" => $user['profile_image'],
        "promocode" => $promocode // could be null if not found
    ]
]);

$conn->close();
?>
