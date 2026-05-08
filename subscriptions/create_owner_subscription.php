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

$response = ["success" => false];

// ======================================================================
// JWT AUTHENTICATION
// ======================================================================
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
        echo json_encode(["success" => false, "message" => "Invalid token"]);
        exit;
    }

    // Validate user
    $stmt = $conn->prepare("SELECT user_id, user_type FROM USERS WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        echo json_encode(["success" => false, "message" => "User not found"]);
        exit;
    }
    if (strtolower($user['user_type']) !== "owner") {
        echo json_encode(["success" => false, "message" => "User is not owner"]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Token error: ".$e->getMessage()]);
    exit;
}

$owner_id = $user_id;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) $input = $_POST;

$plan_id = intval($input['plan_id'] ?? 0);

if (!$plan_id) {
    echo json_encode(["success" => false, "message" => "plan_id required"]);
    exit;
}

// ======================================================================
// FETCH PLAN DETAILS
// ======================================================================
$stmt = $conn->prepare("SELECT plan_id, total_amount, razorpay_plan_id, property_limit FROM SUBSCRIPTION_PLANS WHERE plan_id = ? AND is_active = 1");
$stmt->bind_param("i", $plan_id);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();

if (!$plan) {
    echo json_encode(["success" => false, "message" => "Invalid or inactive plan"]);
    exit;
}

if (!$plan['razorpay_plan_id']) {
    echo json_encode(["success" => false, "message" => "Razorpay plan not linked for this plan"]);
    exit;
}

$rz_plan_id = $plan['razorpay_plan_id'];

// ======================================================================
// CHECK IF OWNER ALREADY HAS ACTIVE SUBSCRIPTION
// ======================================================================
$stmt = $conn->prepare("SELECT subscription_id FROM OWNER_SUBSCRIPTIONS WHERE owner_id = ? AND is_active = 1");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "You already have an active subscription"]);
    exit;
}

// ======================================================================
// CREATE LOCAL SUBSCRIPTION ENTRY (inactive until payment success)
// ======================================================================
$start_date = date('Y-m-d');
$end_date   = date('Y-m-d', strtotime($start_date . ' + 30 days'));

$is_active = 0;
$is_paid = 0;

$stmt = $conn->prepare("
    INSERT INTO OWNER_SUBSCRIPTIONS 
    (owner_id, plan_id, start_date, end_date, is_active, is_paid, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
");
$stmt->bind_param("iissii", $owner_id, $plan_id, $start_date, $end_date, $is_active, $is_paid);
$stmt->execute();

$subscription_id = $stmt->insert_id;

// ======================================================================
// FETCH USER DETAILS
// ======================================================================
$stmt = $conn->prepare("SELECT name, email, phone FROM USERS WHERE user_id = ?");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$user_details = $stmt->get_result()->fetch_assoc();

// ======================================================================
// CREATE RAZORPAY SUBSCRIPTION
// ======================================================================
try {
    $api = new Api($razorpay_key, $razorpay_secret);

    $rz_subscription = $api->subscription->create([
        "plan_id" => $rz_plan_id,
        "customer_notify" => 1,
        "total_count" => 12,
        "quantity" => 1,
        "notes" => [
            "subscription_id" => $subscription_id,
            "owner_id" => $owner_id
        ]
    ]);

    $rz_subscription_id = $rz_subscription['id'];
    $auth_link = $rz_subscription['short_url']; // user completes mandate here

    // ==================================================================
    // UPDATE LOCAL SUBSCRIPTION WITH RAZORPAY IDs
    // ==================================================================
    $stmt = $conn->prepare("
        UPDATE OWNER_SUBSCRIPTIONS
        SET razorpay_subscription_id = ?, razorpay_plan_id = ?
        WHERE subscription_id = ?
    ");
    $stmt->bind_param("ssi", $rz_subscription_id, $rz_plan_id, $subscription_id);
    $stmt->execute();

    // ==================================================================
    // SEND RESPONSE TO APP
    // ==================================================================
    echo json_encode([
        "success" => true,
        "message" => "Subscription created. Complete authorization.",
        "gateway" => "razorpay",
        "data" => [
            "subscription_id" => $subscription_id,
            "razorpay_subscription_id" => $rz_subscription_id,
            "auth_link" => $auth_link,
            "amount" => $plan['total_amount'],
            "currency" => "INR",
            "payment_status" => "Pending"
        ]
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Razorpay error: ".$e->getMessage()]);
    exit;
}

?>
