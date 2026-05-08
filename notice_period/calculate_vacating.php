<?php
header('Content-Type: application/json');
require_once '../dbconn.php';

date_default_timezone_set('Asia/Kolkata');

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;

if ($user_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing or invalid user_id'
    ]);
    exit;
}

// Get the latest confirmed booking of the user
$sql = "SELECT b.*, p.security_deposit, p.deductions, p.rent_per_day, p.rent_per_bed 
        FROM BOOKINGS b
        JOIN PROPERTY p ON b.property_id = p.property_id
        WHERE b.user_id = ? AND b.booking_status = 'Confirmed' AND b.room_id = ?
        ORDER BY b.created_at DESC LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $room_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    echo json_encode([
        'success' => false,
        'error' => 'No confirmed booking found for user'
    ]);
    exit;
}

// Core logic
$start_date = new DateTime($booking['start_date']);
$today = new DateTime(date('Y-m-d'));
$interval = $start_date->diff($today);
$completed_months = $interval->m + ($interval->y * 12);

$count_cycles = intval($booking['count_cycles']);
$security_deposit = floatval($booking['security_deposit']);
$deductions = floatval($booking['deductions']);
$rent_per_day = floatval($booking['rent_per_day']);
$rent_per_bed = floatval($booking['rent_per_bed']);

if ($completed_months >= $count_cycles) {
    $join_day = intval($start_date->format('d'));
    $vacate_date = clone $today;
    $vacate_date->modify('+30 days');

    $days_since_start = $start_date->diff($today)->days;
    $extra_days = max(0, $days_since_start - ($count_cycles * 30));
    $extra_charge = $extra_days * ($rent_per_bed / 30);

    $total_rent = $rent_per_bed + $extra_charge;
    $refund_amount = $security_deposit - $total_rent - $deductions;

    $response = [
        'success' => true,
        'status' => 'Ready for Vacating',
        'vacating_date' => $vacate_date->format('Y-m-d'),
        'extra_charge' => $extra_charge,
        'rent_per_bed' => $rent_per_bed,
        'completed_months' => $completed_months,
        'extra_days' => $extra_days,
        'total_rent' => $total_rent,
        'security_deposit' => $security_deposit,
        'deductions' => $deductions,
        'amount_type' => ($refund_amount >= 0) ? 'Refund Amount' : 'Due Amount',
        'amount' => abs(round($refund_amount, 2))
    ];
} else {
    $response = [
        'success' => true,
        'status' => 'Minimum rental period not completed',
        'completed_months' => $completed_months,
        'required_cycles' => $count_cycles
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);
$conn->close();
?>
