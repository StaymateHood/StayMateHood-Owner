<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

header("Content-Type: application/json");

include '../dbconn.php';
require_once '../vendor/autoload.php';
require '../env.php';

use Razorpay\Api\Api;

// =====================================================================
// READ WEBHOOK BODY & SIGNATURE
// =====================================================================
$webhookBody = file_get_contents("php://input");
$headers = getallheaders();
$razorpaySignature =
    $headers["X-Razorpay-Signature"]
    ?? $headers["x-razorpay-signature"]
    ?? "";

if (!$razorpaySignature) {
    echo json_encode(["success" => false, "message" => "Missing signature"]);
    exit;
}

// Verify signature
$expectedSignature = hash_hmac('sha256', $webhookBody, $razorpay_webhook_secret);

if (!hash_equals($expectedSignature, $razorpaySignature)) {
    echo json_encode(["success" => false, "message" => "Invalid webhook signature"]);
    exit;
}

$data = json_decode($webhookBody, true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid webhook JSON"]);
    exit;
}

file_put_contents("check_webhook.txt", "Event Hit: ".$data['event']." - ".date("Y-m-d H:i:s")."\n", FILE_APPEND);

// =====================================================================
// EXTRACT MAIN EVENT
// =====================================================================
$event = $data['event'] ?? '';


// =====================================================================
// EVENT: subscription.activated (First Ever Payment Success)
// =====================================================================
if ($event === "subscription.activated") {

    $rz_sub_id = $data['payload']['subscription']['entity']['id'];
    $current_start = date("Y-m-d");
    $current_end   = date("Y-m-d", strtotime("+30 days"));

    // Fetch local subscription entry
    $stmt = $conn->prepare("SELECT * FROM OWNER_SUBSCRIPTIONS WHERE razorpay_subscription_id = ?");
    $stmt->bind_param("s", $rz_sub_id);
    $stmt->execute();
    $subscription = $stmt->get_result()->fetch_assoc();

    if (!$subscription) {
        echo json_encode(["success" => false, "message" => "Subscription not found"]);
        exit;
    }

    // Activate subscription
    $stmt = $conn->prepare("
        UPDATE OWNER_SUBSCRIPTIONS 
        SET is_active = 1, is_paid = 1, start_date = ?, end_date = ?, updated_at = NOW()
        WHERE razorpay_subscription_id = ?");
    $stmt->bind_param("sss", $current_start, $current_end, $rz_sub_id);
    $stmt->execute();

    // Insert first payment into SUBSCRIPTION_PAYMENTS
    $stmt = $conn->prepare("
    INSERT INTO SUBSCRIPTION_PAYMENTS 
    (subscription_id, owner_id, amount, payment_status, payment_type,
        transaction_id, razorpay_invoice_id, payment_method, payment_date,
        gateway_response, created_at, updated_at)
    VALUES (?, ?, ?, 'Completed', 'activation', ?, ?, ?, NOW(), ?, NOW(), NOW())
    ");

    $owner_id = $subscription['owner_id'];
    $subscription_id = $subscription['subscription_id'];
    $amount = 0; // Razorpay doesn't send amount in subscription.activated
    $transaction_id = $data['payload']['subscription']['entity']['id']; 
    $invoice_id = ""; 
    $payment_method = $data['payload']['subscription']['entity']['payment_method'] ?? "UPI";

    $stmt->bind_param(
    "iisssss",
    $subscription_id,
    $owner_id,
    $amount,
    $transaction_id,
    $invoice_id,
    $payment_method,
    $webhookBody
    );
    $stmt->execute();
    echo json_encode(["success" => true, "message" => "Subscription activated"]);
    exit;
}


// =====================================================================
// EVENT: invoice.paid (Renewal or Upgrade Payment)
// =====================================================================
if ($event === "invoice.paid") {

    $invoice = $data['payload']['invoice']['entity'];

    $rz_invoice_id  = $invoice['id'];
    $rz_sub_id      = $invoice['subscription_id'];
    $amount         = $invoice['amount'] / 100;
    $payment_id     = $invoice['payment_id'];
    $payment_method = $invoice['method'] ?? "UPI";
    $payment_date   = date("Y-m-d H:i:s", $invoice['created_at']);

    // Fetch subscription
    $stmt = $conn->prepare("SELECT * FROM OWNER_SUBSCRIPTIONS WHERE razorpay_subscription_id = ?");
    $stmt->bind_param("s", $rz_sub_id);
    $stmt->execute();
    $subscription = $stmt->get_result()->fetch_assoc();

    if (!$subscription) {
        echo json_encode(["success" => false, "message" => "Subscription not found"]);
        exit;
    }

    $owner_id = $subscription['owner_id'];
    $subscription_id = $subscription['subscription_id'];

    // Detect upgrade
    $payment_type = "renewal";
    $isUpgrade = false;

    if (isset($invoice['description']) && str_contains(strtolower($invoice['description']), "upgrade")) {
        $payment_type = "upgrade";
        $isUpgrade = true;
    }

    // Insert payment log
    $stmt = $conn->prepare("
        INSERT INTO SUBSCRIPTION_PAYMENTS 
        (subscription_id, owner_id, amount, payment_status, payment_type,
         transaction_id, razorpay_invoice_id, payment_method, payment_date,
         gateway_response, created_at, updated_at)
        VALUES (?, ?, ?, 'Completed', ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->bind_param(
        "iidsssss",
        $subscription_id,
        $owner_id,
        $amount,
        $payment_type,
        $payment_id,
        $rz_invoice_id,
        $payment_method,
        $payment_date,
        $webhookBody
    );
    $stmt->execute();


    // =====================================
    // RENEWAL LOGIC
    // =====================================
    if (!$isUpgrade) {

        $new_start = $subscription['end_date'];
        $new_end   = date("Y-m-d", strtotime($new_start . " +30 days"));

        $stmt = $conn->prepare("
            UPDATE OWNER_SUBSCRIPTIONS
            SET start_date = ?, end_date = ?, is_active = 1, is_paid = 1, updated_at = NOW()
            WHERE razorpay_subscription_id = ?");
        $stmt->bind_param("sss", $new_start, $new_end, $rz_sub_id);
        $stmt->execute();

        echo json_encode(["success" => true, "message" => "Renewal processed"]);
        exit;
    }


    // =====================================
    // UPGRADE LOGIC
    // =====================================
    // Razorpay sends new plan_id inside invoice payload
    $new_plan_id = $invoice['plan_id'] ?? null;

    if ($new_plan_id) {

        $stmt = $conn->prepare("
            UPDATE OWNER_SUBSCRIPTIONS 
            SET plan_id = ?, updated_at = NOW()
            WHERE subscription_id = ?
        ");
        $stmt->bind_param("ii", $new_plan_id, $subscription_id);
        $stmt->execute();
    }

    echo json_encode(["success" => true, "message" => "Subscription upgraded successfully"]);
    exit;
}



// =====================================================================
// EVENT: subscription.charged (Auto-debit Success)
// =====================================================================
if ($event === "subscription.charged") {

    $rz_sub_id = $data['payload']['subscription']['entity']['id'];

    // Log only; nothing changes in database
    file_put_contents("autorenew_log.txt", "Renewal Success: $rz_sub_id \n", FILE_APPEND);

    echo json_encode(["success" => true, "message" => "Autopay charged successfully"]);
    exit;
}


// =====================================================================
// EVENT: invoice.payment_failed (Auto-renew Fail)
// =====================================================================
if ($event === "invoice.payment_failed") {

    $invoice = $data['payload']['invoice']['entity'];
    $rz_sub_id = $invoice['subscription_id'];

    $stmt = $conn->prepare("
        UPDATE OWNER_SUBSCRIPTIONS
        SET is_paid = 0, is_active = 0, updated_at = NOW()
        WHERE razorpay_subscription_id = ?");
    $stmt->bind_param("s", $rz_sub_id);
    $stmt->execute();

    echo json_encode(["success" => true, "message" => "Payment failed, subscription inactive"]);
    exit;
}


// =====================================================================
// UNKNOWN EVENT
// =====================================================================
echo json_encode(["success" => true, "message" => "Event received (no handler): $event"]);
exit;

?>
