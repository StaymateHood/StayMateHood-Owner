<?php
header('Content-Type: application/json');
require_once '../dbconn.php';

if (!isset($_GET['booking_id'])) {
    echo json_encode(["success" => false, "error" => "Missing booking_id parameter"]);
    exit;
}

$booking_id = intval($_GET['booking_id']);

// Fetch booking details
$sql = "SELECT start_date, count_cycles, maintainance_cycle FROM BOOKINGS WHERE booking_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "error" => "Booking not found"]);
    exit;
}

$row = $result->fetch_assoc();
$start_date = $row['start_date'];
$count_cycles = intval($row['count_cycles']);
$maintainance_cycle = intval($row['maintainance_cycle']);

if (!$start_date || $count_cycles === null || $maintainance_cycle === null) {
    echo json_encode(["success" => false, "error" => "Invalid booking data"]);
    exit;
}

// Calculate months passed since start date
$start = new DateTime($start_date);
$now = new DateTime();
$interval = $start->diff($now);
$months_passed = ($interval->y * 12) + $interval->m;

$response = [
    "success" => true,
    "booking_id" => $booking_id,
    "start_date" => $start_date,
    "months_passed" => $months_passed,
    "count_cycles" => $count_cycles,
    "maintainance_cycle" => $maintainance_cycle
];

// Rent due check
if ($months_passed > $count_cycles) {
    $response["rent_payment_status"] = "Due";
    $response["pending_rent_cycles"] = $months_passed - $count_cycles;
} else {
    $response["rent_payment_status"] = "No Due";
    $response["pending_rent_cycles"] = 0;
}

// Maintenance due check
if ($months_passed > $maintainance_cycle) {
    $response["maintainance_payment_status"] = "Due";
    $response["pending_maintainance_cycles"] = $months_passed - $maintainance_cycle;
} else {
    $response["maintainance_payment_status"] = "No Due";
    $response["pending_maintainance_cycles"] = 0;
}

echo json_encode($response);
?>
