<?php
header('Content-Type: application/json');
include '../dbconn.php';

// Get GET data
$chat_id = $_GET['chat_id'] ?? '';
$limit = $_GET['limit'] ?? 50;
$offset = $_GET['offset'] ?? 0;

if (empty($chat_id)) {
    echo json_encode(["success" => false, "message" => "Chat ID is required."]);
    exit;
}

// Get messages
$sql = "SELECT cm.id, cm.sender_id, u.name as sender_name, cm.message, cm.message_type, cm.timestamp
        FROM private_chat_messages cm
        JOIN users u ON cm.sender_id = u.user_id
        WHERE cm.chat_id = ?
        ORDER BY cm.timestamp DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $chat_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode(["success" => true, "messages" => array_reverse($messages)]);
?>