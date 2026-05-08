<?php
header("Content-Type: application/json");
include '../dbconn.php';
require '../env.php';
require_once '../notifications/notify.php';  // notification helpers

/**
 * Send and log push notifications
 */
function pushAndSaveNotification($conn, $user_id, $user_type, $title, $msg, $data = [])
{
    $notif_sql = "INSERT INTO NOTIFICATIONS (user_id, title, message, created_at, is_read, notification_type)
                  VALUES (?, ?, ?, NOW(), 0, ?)";
    $stmt = $conn->prepare($notif_sql);
    $stmt->bind_param("isss", $user_id, $title, $msg, $user_type);
    $stmt->execute();
    $notif_id = $conn->insert_id;

    $tok_stmt = $conn->prepare("SELECT token FROM PUSH_TOKENS WHERE userid = ?");
    $tok_stmt->bind_param("i", $user_id);
    $tok_stmt->execute();
    $res = $tok_stmt->get_result();

    $tokens = [];
    while ($row = $res->fetch_assoc()) {
        $tokens[] = $row['token'];
    }

    foreach ($tokens as $deviceToken) {
        sendPushNotification(
            $deviceToken,
            $title,
            $msg,
            array_merge($data, ["notif_id" => $notif_id])
        );
    }

    return [
        "success" => true,
        "notification_id" => $notif_id,
        "sent_to" => count($tokens)
    ];
}

try {
    // ✅ Read and validate JSON payload
    $input = json_decode(file_get_contents('php://input'), true);
//     $input = [
//     "data" => [
//         "order" => [
//             "order_id" => "order_92_1763379791_9400"
//         ],
//         "payment" => [
//             "payment_status" => "SUCCESS",
//             "payment_amount" => 2000.00,
//             "payment_group" => "upi",
//             "payment_time" => "2025-11-12 14:22:10"
//         ]
//     ]
// ];

    if (!$input || !isset($input['data']['order']['order_id'])) {
        throw new Exception("Invalid JSON payload or missing order_id");
    }

    $order_id = $input['data']['order']['order_id'];
    $payment_status_gateway = strtoupper($input['data']['payment']['payment_status'] ?? '');
    
    
    $payment_amount = floatval($input['data']['payment']['payment_amount'] ?? 0);
    $payment_group = $input['data']['payment']['payment_group'] ?? null;
    $payment_time = $input['data']['payment']['payment_time'] ?? null;


    // ✅ Fetch payment record from DB
    $stmt = $conn->prepare("SELECT * FROM PAYMENTS WHERE transaction_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Payment record not found for order_id: $order_id");
    }

    $payment = $result->fetch_assoc();
    $booking_id = $payment['booking_id'];
    $payment_type = $payment['payment_type'] ?? 'rent';
    $payment_status_db = $payment['payment_status'];
    $amount = $payment['amount'];
    $cycle_id = $payment['cycle_id'] ?? null;
    $payment_id = $payment['payment_id'];

    // ✅ Map gateway status → internal status
    $status_map = [
        'SUCCESS' => 'Completed',
        'PAID' => 'Completed',
        'ACTIVE' => 'Pending',
        'FAILED' => 'Failed',
        'CANCELLED' => 'Failed',
        'EXPIRED' => 'Failed'
    ];
    $latest_status = $status_map[$payment_status_gateway] ?? 'Pending';

    // ✅ Update payment & rent cycle if status changed
    if ($latest_status !== $payment_status_db) {
        $conn->begin_transaction();
        try {
            // Update PAYMENTS table
            $update_fields = ["payment_status = ?", "updated_at = NOW()"];
            $params = [$latest_status];
            $types = "s";

            if ($payment_group) {
                $update_fields[] = "payment_method = ?";
                $params[] = $payment_group;
                $types .= "s";
            }

            if ($latest_status === 'Completed') {
                $update_fields[] = "payment_date = NOW()";
            }

            $update_query = "UPDATE PAYMENTS SET " . implode(", ", $update_fields) . " WHERE payment_id = ?";
            $params[] = $payment['payment_id'];
            $types .= "i";

            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param($types, ...$params);
            $update_stmt->execute();

            // ✅ If rent payment → mark all unpaid rent cycles as paid
            if ($latest_status === 'Completed') {
                $update_cycle = $conn->prepare("
                    UPDATE BOOKING_RENT_CYCLE 
                    SET rent_status = 'Paid', paid_amount = total_amount , amount_due = 0 , updated_at = NOW() , payment_id = ?
                    WHERE cycle_id = ? AND rent_status IN ('Pending' , 'Partially Paid', 'Overdue')
                ");
                $update_cycle->bind_param("ii" , $payment_id,  $cycle_id);
                $update_cycle->execute();
            }

            $checkStatusOfBooking = $conn->prepare("SELECT booking_status FROM BOOKINGS WHERE booking_id = ?");
            $checkStatusOfBooking->bind_param("i", $booking_id);    
            $checkStatusOfBooking->execute();
            $statusResult = $checkStatusOfBooking->get_result();
            $current_booking_status = '';
            if ($statusResult->num_rows > 0) {
                $row = $statusResult->fetch_assoc();
                $current_booking_status = $row['booking_status'];
            }
            if ($current_booking_status == 'Approved') {
                      // Update Booking status if needed
                $update_booking = $conn->prepare("
                    UPDATE BOOKINGS 
                    SET booking_status = 'Active', updated_at = NOW()
                    WHERE booking_id = ?
                ");
                $update_booking->bind_param("i", $booking_id);
                $update_booking->execute();
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            throw new Exception("Database update failed: " . $e->getMessage());
        }

        // ✅ Send Notifications (Tenant + Owner)
        if ($latest_status === 'Completed') {
            $getBooking = $conn->prepare("SELECT property_id, user_id FROM BOOKINGS WHERE booking_id = ?");
            $getBooking->bind_param("i", $booking_id);
            $getBooking->execute();
            $booking = $getBooking->get_result()->fetch_assoc();

            $tenant_id = $booking['user_id'];
            $property_id = $booking['property_id'];

            $getTenant = $conn->prepare("SELECT name FROM USERS WHERE user_id = ?");
            $getTenant->bind_param("i", $tenant_id);
            $getTenant->execute();
            $tenant_name = $getTenant->get_result()->fetch_assoc()['name'];

            $getProperty = $conn->prepare("SELECT name, owner_id FROM PROPERTY WHERE property_id = ?");
            $getProperty->bind_param("i", $property_id);
            $getProperty->execute();
            $property = $getProperty->get_result()->fetch_assoc();
            $property_name = $property['name'];
            $owner_id = $property['owner_id'];

            // Tenant Notification
            $title = "✅ Rent Payment Successful!";
            $msg = "You’ve paid ₹{$amount} for {$property_name}. Transaction ID: {$order_id}";
            pushAndSaveNotification($conn, $tenant_id, 'Other', $title, $msg, ["booking_id" => $booking_id]);

            // Owner Notification
            $title1 = "💰 Rent Received!";
            $msg1 = "₹{$amount} received from {$tenant_name} for {$property_name}. Transaction ID: {$order_id}";
            pushAndSaveNotification($conn, $owner_id, 'Other', $title1, $msg1, ["booking_id" => $booking_id]);
        }
    }

    // ✅ Response
    echo json_encode([
        "success" => true,
        "message" => "Payment verified successfully",
        "data" => [
            "payment_id" => $payment['payment_id'],
            "booking_id" => $booking_id,
            "amount" => $amount,
            "payment_type" => $payment_type,
            "status" => $latest_status,
            "transaction_id" => $order_id,
            "updated_at" => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
    exit;
}
?>
