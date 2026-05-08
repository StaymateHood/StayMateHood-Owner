<?php
header('Content-Type: application/json');
include '../dbconn.php';

// Get GET data
$user_id = $_GET['user_id'] ?? '';
// $user_type = $_GET['user_type'] ?? 'owner'; // Default to owner

if (empty($user_id)) {
    echo json_encode(["success" => false, "message" => "User ID is required."]);
    exit;
}

// Get all chats for user (both as user1 and user2)
$sql = "SELECT 
    c.chat_id,
    CASE 
        WHEN c.user1_id = ? THEN c.user2_id 
        ELSE c.user1_id 
    END as other_user_id,
    CASE 
        WHEN c.user1_id = ? THEN u2.name 
        ELSE u1.name 
    END as other_user_name,
    c.last_message,
    c.last_message_time,
    c.created_at,
    COUNT(CASE WHEN pcm.is_read = 0 AND pcm.receiver_id = ? THEN 1 END) as unread_count
FROM private_chats c
LEFT JOIN USERS u1 ON c.user1_id = u1.user_id
LEFT JOIN USERS u2 ON c.user2_id = u2.user_id
LEFT JOIN private_chat_messages pcm ON c.chat_id = pcm.chat_id
WHERE c.user1_id = ? OR c.user2_id = ?
GROUP BY c.chat_id, c.user1_id, c.user2_id, u1.name, u2.name, c.last_message, c.last_message_time, c.created_at
ORDER BY c.last_message_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$chats = [];
while ($row = $result->fetch_assoc()) {
    $chats[] = $row;
}

echo json_encode(["success" => true, "chats" => $chats]);
?>