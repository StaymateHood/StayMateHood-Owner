<?php
include '../dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search = $_POST['search'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM CONTACTS WHERE name LIKE ?");
    $like = "%$search%";
    $stmt->bind_param("s", $like);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $contacts = [];

        while ($row = $result->fetch_assoc()) {
            $contacts[] = $row;
        }

        echo json_encode(["success" => true, "data" => $contacts]);
    } else {
        echo json_encode(["success" => false, "message" => $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Only POST method is allowed."]);
}
?>
