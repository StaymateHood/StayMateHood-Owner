<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");
include '../notifications/save_notification.php';

$booking_id = $_POST['booking_id'] ?? null;
$tenant_id = $_POST['tenant_user_id'] ?? null;
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
$payment_date = $_POST['payment_date'] ?? date('Y-m-d H:i:s');
$transaction_id = $_POST['transaction_id'] ?? ('CASH_' . uniqid());
$payment_method = $_POST['payment_method'] ?? 'Cash';
$rent_cycle_ids = $_POST['rent_cycle_ids'] ?? []; // multiple cycles can be paid

if (!$booking_id || !$tenant_id || empty($rent_cycle_ids) || $amount <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "booking_id, user_id, valid amount and rent_cycle_ids[] are required"
    ]);
    exit;
}

try {
    $conn->begin_transaction();

    // Calculate total due for selected rent cycles
    $placeholders = implode(',', array_fill(0, count($rent_cycle_ids), '?'));
    $types = str_repeat('i', count($rent_cycle_ids));

    $sql = "SELECT cycle_id, amount_due, paid_amount, rent_status  , total_amount
            FROM BOOKING_RENT_CYCLE
            WHERE cycle_id IN ($placeholders) AND booking_id = ?";
    $stmt = $conn->prepare($sql);
    $params = array_merge($rent_cycle_ids, [$booking_id]);
    $stmt->bind_param($types . 'i', ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rent_cycles = [];
    while ($row = $result->fetch_assoc()) {
        $rent_cycles[] = $row;
    }

    if (empty($rent_cycles)) {
        echo json_encode(["success" => false, "message" => "No rent cycles found."]);
        exit;
    }

    $remaining_amount = $amount;
    $created_payment_ids = [];

    foreach ($rent_cycles as $cycle) {
        $cycle_id = $cycle['cycle_id'];
        $rent_due = $cycle['total_amount'] - $cycle['paid_amount'];

        if ($remaining_amount <= 0) break;

        $pay_now = min($remaining_amount, $rent_due);

        // Insert into PAYMENTS
        $insert = $conn->prepare("INSERT INTO PAYMENTS 
            (user_id, booking_id, amount, payment_status, transaction_id, payment_method, currency, payment_date, created_at, updated_at, payment_type, cycle_id)
            VALUES (?, ?, ?, 'Completed', ?, ?, 'INR', ?, NOW(), NOW(), 'rent_cycle', ?)");
        $insert->bind_param("iidsssi", $tenant_id, $booking_id, $pay_now, $transaction_id, $payment_method, $payment_date, $cycle_id);
        $insert->execute();
        
        $created_payment_ids[] = $insert->insert_id;
        $payment_ID = $insert->insert_id;

        // Update RENT_CYCLES (paid_amount + status)
        $new_paid = $cycle['paid_amount'] + $pay_now;
        $new_status = ($new_paid >= $cycle['total_amount']) ? 'Paid' : 'Partially Paid';
        $due_amount = $cycle['total_amount'] - $new_paid;
        $update = $conn->prepare("UPDATE BOOKING_RENT_CYCLE SET paid_amount = ?, amount_due = ? , rent_status = ?, payment_id = ?, updated_at = NOW() WHERE cycle_id = ?");
        $update->bind_param("ddsii", $new_paid, $due_amount, $new_status, $payment_ID, $cycle_id);
        $update->execute();

        $remaining_amount -= $pay_now;
    }

    // Commit transaction
    $conn->commit();

    // Send notification to tenant
    $title = "✅ Cash Payment Collected";
    $msg = "Your cash payment of ₹{$amount} has been recorded successfully.";
    pushAndSaveNotification($conn, $tenant_id, 'Other', $title, $msg, ["booking_id" => $booking_id]);

    // Get owner info
    $owner_stmt = $conn->prepare("SELECT p.owner_id, p.name AS property_name, u.name AS tenant_name
                                  FROM PROPERTY p
                                  JOIN BOOKINGS b ON b.property_id = p.property_id
                                  JOIN USERS u ON u.user_id = b.user_id
                                  WHERE b.booking_id = ?");
    $owner_stmt->bind_param("i", $booking_id);
    $owner_stmt->execute();
    $owner_info = $owner_stmt->get_result()->fetch_assoc();
    $owner_id = $owner_info['owner_id'] ?? null;
    $tenant_name = $owner_info['tenant_name'] ?? 'Tenant';
    $property_name = $owner_info['property_name'] ?? '';

    if ($owner_id) {
        $title2 = "💰 Rent Collected";
        $msg2 = "₹{$amount} has been collected from {$tenant_name} for {$property_name}.";
        pushAndSaveNotification($conn, $owner_id, 'Other', $title2, $msg2, ["booking_id" => $booking_id]);
    }

    echo json_encode([
        "success" => true,
        "message" => "Cash payment recorded successfully",
        "total_paid" => $amount,
        "payment_ids" => $created_payment_ids
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "message" => "Transaction failed: " . $e->getMessage()
    ]);
}
?>
