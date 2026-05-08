<?php
include '../dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mate_id = $_POST['id'];

    // Optional: Check if the mate has any associated expense_splits before deletion

    $stmt = $conn->prepare("DELETE FROM split_mates WHERE id = ?");
    $stmt->bind_param("i", $mate_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Mate removed successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}
?>
