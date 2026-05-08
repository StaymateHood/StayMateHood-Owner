<?php
include '../dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mate_id = $_POST['id'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];

    $stmt = $conn->prepare("UPDATE split_mates SET name = ?, phone = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("ssi", $name, $phone, $mate_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Mate updated successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}
?>
