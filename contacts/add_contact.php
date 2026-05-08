<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $designation = $_POST['designation'] ?? '';
    $number = $_POST['number'] ?? '';
    $property_id = $_POST['property_id'] ?? null;

    if ($name === '' || $number === '') {
        echo json_encode([
            "success" => false,
            "message" => "Name and number are required."
        ]);
        exit;
    }

    // Prepare the SQL with property_id
    $stmt = $conn->prepare("INSERT INTO CONTACTS (name, designation, number, property_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $name, $designation, $number, $property_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Only POST method is allowed."]);
}
?>
