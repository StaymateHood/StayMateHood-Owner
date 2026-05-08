<?php
include '../dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $type = $_POST['type'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $date = $_POST['date'];

    $stmt = $conn->prepare("UPDATE split_expenses SET type = ?, description = ?, amount = ?, date = ? WHERE id = ?");
    $stmt->bind_param("ssdsi", $type, $description, $amount, $date, $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Expense updated successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>
