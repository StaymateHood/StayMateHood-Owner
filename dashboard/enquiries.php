<?php
header('Content-Type: application/json');
include '../dbconn.php';

function api_response($success, $message, $data = null) {
    $resp = ["success" => $success, "message" => $message];
    if ($data !== null) $resp["data"] = $data;
    echo json_encode($resp);
    exit();
}

if (!$conn) {
    api_response(false, 'Database connection failed');
}

$property_id = $_POST['property_id'] ?? '';
if (!is_numeric($property_id) || intval($property_id) <= 0) {
    api_response(false, 'Invalid property_id');
}
$property_id = intval($property_id);

// Check if property exists
$sql_check = "SELECT property_id FROM PROPERTY WHERE property_id = ?";
$stmt_check = $conn->prepare($sql_check);
if (!$stmt_check) {
    api_response(false, 'Prepare failed: ' . $conn->error);
}
$stmt_check->bind_param("i", $property_id);
$stmt_check->execute();
$stmt_check->store_result();
if ($stmt_check->num_rows === 0) {
    $stmt_check->close();
    api_response(false, 'Property not found');
}
$stmt_check->close();

// Increment enquiries count
$sql = "UPDATE PROPERTY SET enquiries = enquiries + 1 WHERE property_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    api_response(false, 'Prepare failed: ' . $conn->error);
}
$stmt->bind_param("i", $property_id);
if ($stmt->execute()) {
    $stmt->close();
    api_response(true, 'Enquiry count incremented');
} else {
    $stmt->close();
    api_response(false, 'Failed to update enquiries');
}
$conn->close();
?>