<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


include '../dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $admin_id = $_POST['admin_id'];

    $stmt = $conn->prepare("INSERT INTO split_mates (name, phone, admin_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $name, $phone, $admin_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Mate added successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>
