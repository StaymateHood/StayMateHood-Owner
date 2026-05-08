<?php
header('Content-Type: application/json');
require_once '../dbconn.php';

$property_id = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

if ($property_id <= 0 || empty($query)) {
    echo json_encode(['error' => 'Missing or invalid property_id or query']);
    exit;
}

$query = "%{$query}%";
$response = [];

// Search in ROOM
$sql = "SELECT * FROM ROOM 
        WHERE property_id = ? AND (
            room_number LIKE ? OR
            room_type LIKE ? OR
            availability_status LIKE ?
        )";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isss", $property_id, $query, $query, $query);
$stmt->execute();
$response['rooms'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Search in BOOKINGS
$sql = "SELECT * FROM BOOKINGS 
        WHERE property_id = ? AND (
            booking_status LIKE ?
        )";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $property_id, $query);
$stmt->execute();
$response['bookings'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Search in TICKETS
$sql = "SELECT * FROM TICKETS 
        WHERE property_id = ? AND (
            subject LIKE ? OR
            description LIKE ? OR
            ticket_type LIKE ? OR
            priority LIKE ? OR
            status LIKE ?
        )";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isssss", $property_id, $query, $query, $query, $query, $query);
$stmt->execute();
$response['tickets'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Search in CONTACTS
$sql = "SELECT * FROM CONTACTS 
        WHERE property_id = ? AND (
            name LIKE ? OR
            designation LIKE ? OR
            number LIKE ?
        )";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isss", $property_id, $query, $query, $query);
$stmt->execute();
$response['contacts'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Search in USERS (linked via bookings)
$sql = "SELECT DISTINCT u.* FROM USERS u
        JOIN BOOKINGS b ON u.user_id = b.user_id
        WHERE b.property_id = ? AND (
            u.name LIKE ? OR
            u.email LIKE ? OR
            u.phone LIKE ? OR
            u.user_type LIKE ? OR
            u.relationship LIKE ? OR
            u.food_preference LIKE ? OR
            u.occupation LIKE ?
        )";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isssssss", $property_id, $query, $query, $query, $query, $query, $query, $query);
$stmt->execute();
$response['users'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

echo json_encode($response, JSON_PRETTY_PRINT);
?>
