<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../dbconn.php';

$property_id = $_GET['property_id'] ?? $_POST['property_id'] ?? null;

if (!$property_id || !is_numeric($property_id)) {
    echo json_encode(["status" => "error", "message" => "Invalid or missing property_id"]);
    exit;
}

$property_id = (int)$property_id;

// 1. Get total beds (sum of capacity) and occupied beds
$query = "SELECT 
            IFNULL(SUM(capacity), 0) AS total_beds,
            IFNULL(SUM(occupied), 0) AS occupied_beds
          FROM ROOM 
          WHERE property_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();
$room_data = $result->fetch_assoc();
$total_beds = (int)$room_data['total_beds'];
$occupied_beds = (int)$room_data['occupied_beds'];
$vacant_beds = $total_beds - $occupied_beds;

// 2. Get tenants with confirmed bookings
$query = "SELECT 
            COUNT(*) AS total_tenants,
            SUM(CASE WHEN notice_period = 0 THEN 1 ELSE 0 END) AS notice_period_tenants,
            SUM(CASE 
                WHEN TIMESTAMPDIFF(MONTH, start_date, CURDATE()) = count_cycles 
                THEN 1 
                ELSE 0 
            END) AS paid_tenants
          FROM BOOKINGS 
          WHERE property_id = ? AND booking_status = 'Confirmed'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();
$booking_data = $result->fetch_assoc();

$total_tenants = (int)$booking_data['total_tenants'];
$notice_period_tenants = (int)$booking_data['notice_period_tenants'];
$paid_tenants = (int)$booking_data['paid_tenants'];
$unpaid_tenants = $total_tenants - $paid_tenants;

// Final response
$response = [
    "status" => "success",
    "data" => [
        "property_id" => $property_id,
        "total_beds" => $total_beds,
        "occupied_beds" => $occupied_beds,
        "vacant_beds" => $vacant_beds,
        "total_tenants" => $total_tenants,
        "tenants_in_notice_period" => $notice_period_tenants,
        "paid_tenants" => $paid_tenants,
        "unpaid_tenants" => $unpaid_tenants
    ]
];

echo json_encode($response);
?>
