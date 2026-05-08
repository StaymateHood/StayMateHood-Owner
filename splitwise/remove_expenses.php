<?php
include '../dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_id = $_POST['id'];

    $stmt = $conn->prepare("DELETE FROM split_expenses WHERE id = ?");
    $stmt->bind_param("i", $expense_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Expense removed successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>
