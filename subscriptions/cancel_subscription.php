<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");

include '../dbconn.php';
require_once '../vendor/autoload.php';
require '../env.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Razorpay\Api\Api;

// =====================================================
// JWT VALIDATION
// =====================================================
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    echo json_encode(["success" => false, "message" => "Authorization token missing"]);
    exit;
}

try {
    $token = str_replace('Bearer ', '', $authHeader);
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    $owner_id = $decoded->sub ?? null;

    if (!$owner_id) {
        echo json_encode(["success" => false, "message" => "Invalid token"]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Token error: ".$e->getMessage()]);
    exit;
}

// =====================================================
// VALIDATE OWNER
// =====================================================
$stmt = $conn->prepare("SELECT user_type FROM USERS WHERE user_id = ?");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || strtolower($user['user_type']) !== "owner") {
    echo json_encode(["success" => false, "message" => "User is not owner"]);
    exit;
}

// =====================================================
// ONLY POST METHOD
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

// =====================================================
// FETCH ACTIVE SUBSCRIPTION
// =====================================================
$stmt = $conn->prepare("
    SELECT subscription_id, razorpay_subscription_id 
    FROM OWNER_SUBSCRIPTIONS 
    WHERE owner_id = ? AND is_active = 1 
    LIMIT 1
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();

if (!$sub) {
    echo json_encode(["success" => false, "message" => "No active subscription found"]);
    exit;
}

$subscription_id = $sub['subscription_id'];
$rz_sub_id = $sub['razorpay_subscription_id'];

// =====================================================
// CHECK PAYMENT METHOD FROM LAST PAYMENT
// =====================================================
$stmt = $conn->prepare("
    SELECT payment_method 
    FROM SUBSCRIPTION_PAYMENTS 
    WHERE subscription_id = ? 
    ORDER BY payment_id DESC LIMIT 1
");
$stmt->bind_param("i", $subscription_id);
$stmt->execute();
$pay = $stmt->get_result()->fetch_assoc();

$payment_method = strtolower($pay['payment_method'] ?? 'upi');

// =====================================================
// DETERMINE CANCELLATION TYPE
// =====================================================
// UPI → MUST be instant cancellation (0)
// CARD/E-MANDATE → cancel at cycle end (1)

$cancel_type = (strtolower($payment_method) === "upi") ? 0 : 1;
$cancel_message = ($cancel_type == 0) 
    ? "Subscription cancelled immediately (UPI AutoPay does not support cycle-end cancellation)."
    : "Subscription cancellation scheduled. Plan remains active until cycle end.";

// =====================================================
// CANCEL SUBSCRIPTION IN RAZORPAY
// =====================================================
try {
    $api = new Api($razorpay_key, $razorpay_secret);

    $api->subscription->fetch($rz_sub_id)->cancel([
        "cancel_at_cycle_end" => $cancel_type
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Razorpay cancel error: ".$e->getMessage()]);
    exit;
}

// =====================================================
// UPDATE LOCAL DATABASE
// =====================================================

if ($cancel_type === 0) {
    // Instant cancel → deactivate now
    $stmt = $conn->prepare("
        UPDATE OWNER_SUBSCRIPTIONS
        SET is_active = 0, is_paid = 0, cancel_requested = 1, updated_at = NOW()
        WHERE subscription_id = ?
    ");
} else {
    // Cycle-end cancel → mark as requested only
    $stmt = $conn->prepare("
        UPDATE OWNER_SUBSCRIPTIONS
        SET cancel_requested = 1, updated_at = NOW()
        WHERE subscription_id = ?
    ");
}

$stmt->bind_param("i", $subscription_id);
$stmt->execute();

// =====================================================
// LOG CANCELLATION
// =====================================================
// $stmt = $conn->prepare("
//     INSERT INTO SUBSCRIPTION_CANCELLATION_LOG
//     (subscription_id, owner_id, razorpay_subscription_id, cancelled_at, cancelled_type)
//     VALUES (?, ?, ?, NOW(), ?)
// ");
// $cType = ($cancel_type == 0) ? "instant" : "cycle_end";
// $stmt->bind_param("iiss", $subscription_id, $owner_id, $rz_sub_id, $cType);
// $stmt->execute();

echo json_encode([
    "success" => true,
    "message" => $cancel_message,
    "data" => [
        "subscription_id" => $subscription_id,
        "razorpay_subscription_id" => $rz_sub_id
    ]
]);
exit;

?>
