<?php
header("Content-Type: application/json");
include '../notifications/save_notification.php'; // includes JWT + $conn + $user_id
require '../env.php';
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$response = ["success" => false];

try {
    // Parse request
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(["success" => false, "message" => "Invalid JSON input"]);
        exit;
    }

    // Required fields
    $required = ['booking_id', 'total_amount', 'phone', 'email', 'name'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $booking_id = intval($input['booking_id']);
    $total_amount = floatval($input['total_amount']);
    $phone = trim($input['phone']);
    $email = trim($input['email']);
    $name = trim($input['name']);
    $platform = $input['platform'] ?? 'android';

    if ($total_amount <= 0) {
        throw new Exception("Invalid total amount");
    }

    // ✅ Fetch unpaid rent cycles for this booking automatically
    $sql = "SELECT cycle_id, room_rent, security_amount , amount_due
            FROM BOOKING_RENT_CYCLE 
            WHERE booking_id = ? 
              AND rent_status IN ('Pending' , 'Partially Paid' , 'Overdue')
            ORDER BY cycle_id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 0) {
        throw new Exception("No unpaid rent cycles found for the given booking");
    }

    $sum = 0;
    $valid_cycle_ids = [];
    while ($row = $res->fetch_assoc()) {
        $sum += floatval($row['amount_due']);
        $valid_cycle_ids[] = $row['cycle_id'];
    }

    // ✅ Validate total amount
    if (round($sum, 2) != round($total_amount, 2)) {
        throw new Exception("Amount mismatch! Expected ₹$sum but got ₹$total_amount");
    }

    // ✅ Generate unique order ID
    $order_id = "order_" . $booking_id . "_" . time() . "_" . rand(1000, 9999);

    // ✅ Prepare Cashfree order data
    $cashfree_data = [
        "order_id" => $order_id,
        "order_amount" => $total_amount,
        "order_currency" => "INR",
        "customer_details" => [
            "customer_id" => (string)$user_id,
            "customer_name" => $name,
            "customer_email" => $email,
            "customer_phone" => $phone
        ],
        "order_note" => "Rent payment for booking $booking_id",
        "webhook_url" => "https://staymate.pmpframe.com/NEWAPI/payments/checkPaymentWebhook.php"
    ];

    $cashfree_url = $development_mode 
        ? "https://sandbox.cashfree.com/pg/orders"
        : "https://api.cashfree.com/pg/orders";

    $headers = [
        "Content-Type: application/json",
        "x-api-version: 2023-08-01",
        "x-client-id: $cashfree_app_id",
        "x-client-secret: $cashfree_secret_key",
        "x-request-id: " . uniqid("req_"),
        "x-idempotency-key: $order_id"
    ];

    // ✅ Send to Cashfree
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $cashfree_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($cashfree_data),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    $cashfree_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception("Payment gateway connection failed: $curl_error");
    }

    $cashfree_json = json_decode($cashfree_response, true);
    if ($http_code !== 200 || !isset($cashfree_json['payment_session_id'])) {
        throw new Exception("Payment gateway error: " . ($cashfree_json['message'] ?? 'Unknown'));
    }

    $payment_session_id = $cashfree_json['payment_session_id'];
    $cf_order_id = $cashfree_json['cf_order_id'] ?? '';
    $order_status = $cashfree_json['order_status'] ?? 'ACTIVE';

    // ✅ Insert or Update payment record(s)
    $conn->begin_transaction();
    try {
        foreach ($valid_cycle_ids as $cycle_id) {
            // Check if record already exists for this booking_id & cycle_id
            $check_stmt = $conn->prepare("
                SELECT payment_id FROM PAYMENTS
                WHERE booking_id = ? AND cycle_id = ? AND payment_type IN ('rent' , 'booking')
            ");
            $check_stmt->bind_param("ii", $booking_id, $cycle_id);
            $check_stmt->execute();
            $existing = $check_stmt->get_result();

            if ($existing->num_rows > 0) {
                // ✅ Update existing record
                $stmt = $conn->prepare("
                    UPDATE PAYMENTS
                    SET user_id = ?, amount = ?, payment_status = 'Pending', 
                        payment_session_id = ?, transaction_id = ?, 
                        updated_at = NOW()
                    WHERE booking_id = ? AND cycle_id = ? AND payment_type = 'rent'
                ");
                $stmt->bind_param("idssii", 
                    $user_id, 
                    $total_amount, 
                    $payment_session_id, 
                    $order_id, 
                    $booking_id, 
                    $cycle_id
                );
            } else {
                // ✅ Insert new record
                $stmt = $conn->prepare("
                    INSERT INTO PAYMENTS 
                    (user_id, booking_id, cycle_id, amount, payment_type, payment_status, 
                     payment_session_id, transaction_id, currency, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 'rent', 'Pending', ?, ?, 'INR', NOW(), NOW())
                ");
                $stmt->bind_param("iiidss", 
                    $user_id, 
                    $booking_id, 
                    $cycle_id, 
                    $total_amount, 
                    $payment_session_id, 
                    $order_id
                );
            }
            $stmt->execute();
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        throw new Exception("Database error: " . $e->getMessage());
    }

    // ✅ Final response
    echo json_encode([
        "success" => true,
        "message" => "Rent payment initiated successfully",
        "data" => [
            "order_id" => $order_id,
            "cf_order_id" => $cf_order_id,
            "payment_session_id" => $payment_session_id,
            "amount" => $total_amount,
            "currency" => "INR",
            "order_status" => $order_status,
            "payment_status" => "Pending",
            "environment" => $development_mode ? "SANDBOX" : "PRODUCTION",
            "booking_id" => $booking_id,
            "cycle_ids" => $valid_cycle_ids
        ]
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit;
}
?>
