<?php
include '../dbconn.php';

header('Content-Type: application/json');

$user_type = $_GET['user_type'] ?? '';

if ($user_type == 'owner' || $user_type == 'tenant') {
    // $stmt = $conn->prepare("SELECT * FROM COMMUNITY_CHAT WHERE user_type = ? ORDER BY date DESC");
    // $stmt->bind_param("s", $user_type);
    $stmt = $conn->prepare("SELECT * FROM COMMUNITY_CHAT ORDER BY date DESC");
    $stmt->execute();
    
    $result = $stmt->get_result();
    $comments = [];

    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }

    echo json_encode(["success" => true, "comments" => $comments]);
    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid or missing user_type"]);
}
?>
