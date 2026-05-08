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

// ======================================================================
// JWT VALIDATION
// ======================================================================
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

// ======================================================================
// VALIDATE OWNER
// ======================================================================
$stmt = $conn->prepare("SELECT user_id, user_type FROM USERS WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || strtolower($user['user_type']) !== "owner") {
    echo json_encode(["success" => false, "message" => "User is not owner"]);
    exit;
}

// ======================================================================
// METHOD CHECK
// ======================================================================
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) $input = $_POST;

$new_plan_id = intval($input['plan_id'] ?? 0);

if (!$new_plan_id) {
    echo json_encode(["success" => false, "message" => "plan_id is required"]);
    exit;
}

// ======================================================================
// FETCH CURRENT SUBSCRIPTION
// ======================================================================
$stmt = $conn->prepare("
    SELECT subscription_id, razorpay_subscription_id, plan_id 
    FROM OWNER_SUBSCRIPTIONS 
    WHERE owner_id = ? AND is_active = 1
    LIMIT 1
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();

if (!$current) {
    echo json_encode(["success" => false, "message" => "No active subscription found"]);
    exit;
}

$current_plan_id = $current['plan_id'];
$current_rz_sub_id = $current['razorpay_subscription_id'];

// ======================================================================
// FETCH NEW PLAN DETAILS
// ======================================================================
$stmt = $conn->prepare("
    SELECT plan_id, razorpay_plan_id, total_amount, property_limit 
    FROM SUBSCRIPTION_PLANS 
    WHERE plan_id = ? AND is_active = 1
");
$stmt->bind_param("i", $new_plan_id);
$stmt->execute();
$new_plan = $stmt->get_result()->fetch_assoc();

if (!$new_plan) {
    echo json_encode(["success" => false, "message" => "Invalid plan"]);
    exit;
}

$rz_new_plan_id = $new_plan['razorpay_plan_id'];

if (!$rz_new_plan_id) {
    echo json_encode(["success" => false, "message" => "Plan is not linked with Razorpay"]);
    exit;
}

// ======================================================================
// VALIDATE UPGRADE (new plan must have more PG limit)
// ======================================================================
$stmt = $conn->prepare("SELECT property_limit FROM SUBSCRIPTION_PLANS WHERE plan_id = ?");
$stmt->bind_param("i", $current_plan_id);
$stmt->execute();
$old_plan = $stmt->get_result()->fetch_assoc();

if ($new_plan['property_limit'] <= $old_plan['property_limit']) {
    echo json_encode(["success" => false, "message" => "Cannot downgrade or choose same plan"]);
    exit;
}

// ======================================================================
// START RAZORPAY UPGRADE
// ======================================================================
try {
    $api = new Api($razorpay_key, $razorpay_secret);

    $upgrade = $api->subscription->fetch($current_rz_sub_id)->update([
        "plan_id" => $rz_new_plan_id,
        "quantity" => 1,
        "schedule_at" => "now",
        "customer_notify" => 1
    ]);

    $invoice_id = $upgrade['invoice_id'] ?? null;

    if (!$invoice_id) {
        echo json_encode(["success" => false, "message" => "Invoice not created for upgrade"]);
        exit;
    }

    // ==================================================================
    // FETCH INVOICE TO GET PAYMENT LINK
    // ==================================================================
    $invoice = $api->invoice->fetch($invoice_id);

    $auth_link = $invoice['short_url'] ?? $invoice['invoice_url'] ?? null;

    if (!$auth_link) {
        echo json_encode(["success" => false, "message" => "No payment link returned by Razorpay"]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "message" => "Upgrade initiated. Complete payment to activate new plan.",
        "data" => [
            "invoice_id" => $invoice_id,
            "auth_link"  => $auth_link,
            "razorpay_subscription_id" => $current_rz_sub_id
        ]
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Razorpay error: ".$e->getMessage()]);
    exit;
}

?>
