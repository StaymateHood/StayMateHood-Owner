<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


include '../dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    $admin_id = $_POST['admin_id'];

    $stmt = $conn->prepare("INSERT INTO split_expenses (type, description, amount, date, admin_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdsi", $type, $description, $amount, $date, $admin_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Expense added successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>
