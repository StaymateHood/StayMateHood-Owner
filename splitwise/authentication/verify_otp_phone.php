<?php
header('Content-Type: application/json');
include '../dbconn.php';
require_once '../vendor/autoload.php'; 
require '../env.php'; // Contains $jwt_secret

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$jwt_issuer = 'staymate';
$jwt_audience = 'staymate_user';
$jwt_exp = 60 * 60 * 24 * 7; // 7 days

// Get form data
$otp = $_POST['otp'] ?? '';
$phone = $_POST['phone'] ?? '';
$account_type = $_POST['account_type'] ?? '';

if (empty($phone) || empty($otp)) {
    echo json_encode([
        "success" => false,
        "message" => "Phone and OTP are required."
    ]);
    exit;
}

// Check user existence and type before OTP logic
$user_stmt = $conn->prepare("SELECT user_id, name, email, user_type FROM USERS WHERE phone = ? LIMIT 1");
$user_stmt->bind_param("s", $phone);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "User not found."
    ]);
    exit;
}

$user_row = $user_result->fetch_assoc();
$user_id = $user_row['user_id'];
$user_name = $user_row['name'];
$user_email = $user_row['email'];
$user_type = $user_row['user_type'];

if (($account_type === 'owner' && $user_type !== 'owner') || ($account_type === 'tenant' && $user_type !== 'tenant')) {
    echo json_encode([
        "success" => false,
        "message" => "Account type mismatch. Please use the correct account type."
    ]);
    exit;
}

// Allow static 2000 OTP for development
if ($otp === "2000") {
    // Mark all OTPs for phone as verified
    $otp_update_stmt = $conn->prepare("UPDATE OTP_VERIFICATION_PHONE SET is_verified = 1 WHERE phone = ?");
    if ($otp_update_stmt) {
        $otp_update_stmt->bind_param("s", $phone);
        $otp_update_stmt->execute();
        $otp_update_stmt->close();
    }

    $user_update_stmt = $conn->prepare("UPDATE USERS SET is_verified = 1 WHERE phone = ?");
    if ($user_update_stmt) {
        $user_update_stmt->bind_param("s", $phone);
        $user_update_stmt->execute();
        $user_update_stmt->close();
    }

    // JWT token generation
    $issuedAt = time();
    $expiration = $issuedAt + $jwt_exp;

    $payload = [
        'iss' => $jwt_issuer,
        'aud' => $jwt_audience,
        'iat' => $issuedAt,
        'exp' => $expiration,
        'sub' => $user_id,
        'phone' => $phone,
        'name' => $user_name,
        'email' => $user_email,
        'user_type' => $user_type
    ];

    $token = JWT::encode($payload, $secret_key, 'HS256');

    // Fetch full user details
    $details_stmt = $conn->prepare("SELECT name, email, user_type, phone, is_verified, is_property_added, profile_image, emergency_contact, is_details_updated FROM USERS WHERE user_id = ?");
    $details_stmt->bind_param("i", $user_id);
    $details_stmt->execute();
    $details_result = $details_stmt->get_result();
    $user_info = $details_result->fetch_assoc();

    // Fetch promocode if available
    $promocode = null;
    $promo_stmt = $conn->prepare("SELECT code FROM PROMOCODES WHERE user_id = ? LIMIT 1");
    $promo_stmt->bind_param("i", $user_id);
    $promo_stmt->execute();
    $promo_result = $promo_stmt->get_result();
    if ($promo_result->num_rows > 0) {
        $promo_row = $promo_result->fetch_assoc();
        $promocode = $promo_row['code'];
    }

    // Send final response
    echo json_encode([
        "success" => true,
        "message" => "OTP verified successfully.",
        "token" => $token,
        "user" => [
            "user_id" => $user_id,
            "name" => $user_info['name'] ?? null,
            "email" => $user_info['email'] ?? null,
            "phone" => $user_info['phone'] ?? null,
            "user_type" => $user_info['user_type'] ?? null,
            "is_verified" => (bool)($user_info['is_verified'] ?? 0),
            "is_property_added" => $user_info['is_property_added'] ?? 0,
            "profile_image" => $user_info['profile_image'] ?? null,
            "promocode" => $promocode,
            "emergency_contact" => $user_info['emergency_contact'] ?? null,
            "is_details_updated" => isset($user_info['is_details_updated']) ? (bool)$user_info['is_details_updated'] : false
        ]
    ]);

    $conn->close();
    exit;
}

// Verify OTP from database
$otp_stmt = $conn->prepare("SELECT * FROM OTP_VERIFICATION_PHONE 
                            WHERE phone = ? AND otp = ? AND is_verified = 0 
                            ORDER BY created_at DESC LIMIT 1");
$otp_stmt->bind_param("ss", $phone, $otp);
$otp_stmt->execute();
$otp_result = $otp_stmt->get_result();

if ($otp_result->num_rows === 0) {
    http_response_code(401); // Unauthorized
    echo json_encode([
        "success" => false,
        "message" => "Wrong OTP. Please try again."
    ]);
    exit;
}

// Mark this OTP as verified
$otp_row = $otp_result->fetch_assoc();
$otp_id = $otp_row['id'];

$update_stmt = $conn->prepare("UPDATE OTP_VERIFICATION_PHONE SET is_verified = 1 WHERE id = ?");
$update_stmt->bind_param("i", $otp_id);
$update_stmt->execute();

// Optionally mark user as verified
$vu_stmt = $conn->prepare("UPDATE USERS SET is_verified = 1 WHERE phone = ?");
$vu_stmt->bind_param("s", $phone);
$vu_stmt->execute();

// JWT token generation
$issuedAt = time();
$expiration = $issuedAt + $jwt_exp;

$payload = [
    'iss' => $jwt_issuer,
    'aud' => $jwt_audience,
    'iat' => $issuedAt,
    'exp' => $expiration,
    'sub' => $user_id,
    'phone' => $phone,
    'name' => $user_name,
    'email' => $user_email,
    'user_type' => $user_type
];

$token = JWT::encode($payload, $secret_key, 'HS256');

// Fetch full user details
$details_stmt = $conn->prepare("SELECT name, email, user_type, phone, is_verified, is_property_added, profile_image, emergency_contact, is_details_updated FROM USERS WHERE user_id = ?");
$details_stmt->bind_param("i", $user_id);
$details_stmt->execute();
$details_result = $details_stmt->get_result();
$user_info = $details_result->fetch_assoc();

// Fetch promocode if available
$promocode = null;
$promo_stmt = $conn->prepare("SELECT code FROM PROMOCODES WHERE user_id = ? LIMIT 1");
$promo_stmt->bind_param("i", $user_id);
$promo_stmt->execute();
$promo_result = $promo_stmt->get_result();
if ($promo_result->num_rows > 0) {
    $promo_row = $promo_result->fetch_assoc();
    $promocode = $promo_row['code'];
}

// Send final response
echo json_encode([
    "success" => true,
    "message" => "OTP verified successfully.",
    "token" => $token,
    "user" => [
        "user_id" => $user_id,
        "name" => $user_info['name'] ?? null,
        "email" => $user_info['email'] ?? null,
        "phone" => $user_info['phone'] ?? null,
        "user_type" => $user_info['user_type'] ?? null,
        "is_verified" => (bool)($user_info['is_verified'] ?? 0),
        "is_property_added" => $user_info['is_property_added'] ?? 0,
        "profile_image" => $user_info['profile_image'] ?? null,
        "promocode" => $promocode,
        "emergency_contact" => $user_info['emergency_contact'] ?? null,
    "is_details_updated" => isset($user_info['is_details_updated']) ? (bool)$user_info['is_details_updated'] : false
    ]
]);

$conn->close();
?>