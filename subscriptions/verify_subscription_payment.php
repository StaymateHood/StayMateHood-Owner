<?php
header("Content-Type: application/json");
include '../dbconn.php';
require_once '../vendor/autoload.php';
require '../env.php';

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method. Use POST."]);
    exit;
}

// Get subscription_id and order_id from request
$input = json_decode(file_get_contents('php://input'), true);
$subscription_id = $input['subscription_id'] ?? null;
$order_id = $input['order_id'] ?? null; // Cashfree order_id

if (!$subscription_id && !$order_id) {
    echo json_encode(["success" => false, "message" => "Subscription ID or Order ID is required"]);
    exit;
}

// Get subscription record from database
if ($subscription_id) {
    $stmt = $conn->prepare("SELECT * FROM OWNER_SUBSCRIPTIONS WHERE subscription_id = ? AND owner_id = ?");
    $stmt->bind_param("ii", $subscription_id, $user_id);
} else {
    // If only order_id is provided, we need to extract subscription_id from it
    // Format: sub_order_{subscription_id}_{timestamp}_{random}
    if (preg_match('/^sub_order_(\d+)_/', $order_id, $matches)) {
        $subscription_id = intval($matches[1]);
        $stmt = $conn->prepare("SELECT * FROM OWNER_SUBSCRIPTIONS WHERE subscription_id = ? AND owner_id = ?");
        $stmt->bind_param("ii", $subscription_id, $user_id);
    } else {
        echo json_encode(["success" => false, "message" => "Invalid order ID format"]);
        exit;
    }
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Subscription record not found"]);
    exit;
}

$subscription_data = $result->fetch_assoc();

// Check if payment is already completed
if ($subscription_data['is_paid'] == 1) {
    echo json_encode(["success" => false, "message" => "Payment already completed for this subscription"]);
    exit;
}

// Get plan details for amount
$stmt = $conn->prepare("SELECT total_amount FROM SUBSCRIPTION_PLANS WHERE plan_id = ?");
$stmt->bind_param("i", $subscription_data['plan_id']);
$stmt->execute();
$plan_result = $stmt->get_result();
$plan_data = $plan_result->fetch_assoc();

if (!$plan_data) {
    echo json_encode(["success" => false, "message" => "Plan not found"]);
    exit;
}

// Construct order_id if not provided
if (!$order_id) {
    $order_id = "sub_order_" . $subscription_id . "_" . strtotime($subscription_data['created_at']) . "_0000";
}

// Verify payment status with Cashfree API
$cashfree_url = $development_mode ? 
    "https://sandbox.cashfree.com/pg/orders/$order_id" : 
    "https://api.cashfree.com/pg/orders/$order_id";

$headers_array = [
    'Content-Type: application/json',
    'x-api-version: 2025-01-01',
    'x-client-id: ' . $cashfree_app_id,
    'x-client-secret: ' . $cashfree_secret_key,
    'x-request-id: ' . uniqid('req_')
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $cashfree_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_array);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$cashfree_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error || $http_code !== 200) {
    echo json_encode([
        "success" => false, 
        "message" => "Failed to verify payment with gateway",
        "error" => $curl_error ?: "HTTP $http_code"
    ]);
    exit;
}

$cf_data = json_decode($cashfree_response, true);
error_log('SUBSCRIPTION_PAYMENT_CASHFREE_RESPONSE: ' . $cashfree_response);

if (!$cf_data) {
    echo json_encode(["success" => false, "message" => "Invalid response from payment gateway"]);
    exit;
}

$cf_order_status = $cf_data['order_status'] ?? '';

// Map Cashfree status to payment status
$status_map = [
    'PAID' => 'Completed',
    'ACTIVE' => 'Pending',
    'EXPIRED' => 'Failed',
    'CANCELLED' => 'Failed',
    'FAILED' => 'Failed'
];

$payment_status = $status_map[$cf_order_status] ?? 'Pending';

if ($payment_status !== 'Completed') {
    echo json_encode([
        "success" => false, 
        "message" => "Payment not completed yet",
        "payment_status" => $payment_status,
        "cf_order_status" => $cf_order_status
    ]);
    exit;
}

// Payment is completed, extract payment details
$cf_payment_method = null;
$cf_transaction_id = $order_id; // Default to order_id
$cf_payment_date = date('Y-m-d H:i:s');

// Extract payment method from multiple sources
if (isset($cf_data['order_meta']['payment_methods']) && $cf_data['order_meta']['payment_methods'] !== null) {
    $cf_payment_method = $cf_data['order_meta']['payment_methods'];
} elseif (isset($cf_data['payments']) && !empty($cf_data['payments'])) {
    $payment_info = $cf_data['payments'][0];
    $cf_payment_method = $payment_info['payment_method'] ?? null;
    $cf_transaction_id = $payment_info['cf_payment_id'] ?? $order_id;
}

// Begin transaction
$conn->autocommit(false);

try {
    // Insert into SUBSCRIPTION_PAYMENTS table
    $stmt = $conn->prepare("INSERT INTO SUBSCRIPTION_PAYMENTS (subscription_id, owner_id, amount, payment_status, transaction_id, payment_method, payment_date, gateway_response, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("iidsssss", 
        $subscription_id, 
        $user_id, 
        $plan_data['total_amount'], 
        $payment_status, 
        $cf_transaction_id, 
        $cf_payment_method, 
        $cf_payment_date,
        $cashfree_response
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert payment record");
    }
    
    $payment_id = $conn->insert_id;
    
    // Update OWNER_SUBSCRIPTIONS table - set is_paid = 1 and is_active = 1
    $stmt = $conn->prepare("UPDATE OWNER_SUBSCRIPTIONS SET is_paid = 1, is_active = 1, updated_at = NOW() WHERE subscription_id = ?");
    $stmt->bind_param("i", $subscription_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update subscription status");
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        "success" => true,
        "message" => "Payment verified and subscription activated successfully",
        "data" => [
            "subscription_id" => $subscription_id,
            "payment_id" => $payment_id,
            "payment_status" => $payment_status,
            "transaction_id" => $cf_transaction_id,
            "payment_method" => $cf_payment_method,
            "payment_date" => $cf_payment_date,
            "amount" => $plan_data['total_amount'],
            "currency" => "INR",
            "is_paid" => 1,
            "is_active" => 1
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    echo json_encode([
        "success" => false,
        "message" => "Failed to process payment verification: " . $e->getMessage()
    ]);
}

$conn->autocommit(true);
?>
