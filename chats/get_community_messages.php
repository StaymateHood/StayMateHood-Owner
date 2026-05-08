<?php
header('Content-Type: application/json');
include '../dbconn.php';

// Get GET data
$group_id = $_GET['group_id'] ?? '';
$limit = $_GET['limit'] ?? 50;
$offset = $_GET['offset'] ?? 0;

if (empty($group_id)) {
    echo json_encode(["success" => false, "message" => "Group ID is required."]);
    exit;
}

// Get messages
$sql = "SELECT cm.id, cm.sender_id, u.name as sender_name, cm.message, cm.message_type, cm.timestamp
        FROM community_messages cm
        JOIN users u ON cm.sender_id = u.user_id
        WHERE cm.group_id = ?
        ORDER BY cm.timestamp DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $group_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode(["success" => true, "messages" => array_reverse($messages)]);
?>