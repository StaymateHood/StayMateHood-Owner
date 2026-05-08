<?php
include '../dbconn.php';

$property_id = $_GET['property_id'] ?? null;

if (!$property_id) {
    echo json_encode([
        "success" => false,
        "message" => "property_id is required in the query string."
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM CONTACTS WHERE property_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $property_id);
$stmt->execute();

$result = $stmt->get_result();
$contacts = [];

while ($row = $result->fetch_assoc()) {
    $contacts[] = $row;
}

echo json_encode(["success" => true, "data" => $contacts]);
?>
