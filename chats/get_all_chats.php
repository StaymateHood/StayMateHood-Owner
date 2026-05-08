<?php
header('Content-Type: application/json');
include '../dbconn.php';

$user_id = $_GET['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(["success" => false, "message" => "User ID is required"]);
    exit;
}

// Get all one-to-one chats
$sql = "SELECT 
    c.chat_id,
    c.user1_id,
    c.user2_id,
    CASE 
        WHEN c.user1_id = ? THEN c.user2_id 
        ELSE c.user1_id 
    END as other_user_id,
    CASE 
        WHEN c.user1_id = ? THEN u2.name 
        ELSE u1.name 
    END as other_user_name,
    CASE 
        WHEN c.user1_id = ? THEN u2.profile_image 
        ELSE u1.profile_image 
    END as other_user_image,
    c.last_message,
    c.last_message_time,
    c.created_at,
    (SELECT COUNT(*) FROM private_chat_messages 
     WHERE chat_id = c.chat_id 
     AND receiver_id = ? 
     AND is_read = 0) as unread_count
FROM private_chats c
LEFT JOIN USERS u1 ON c.user1_id = u1.user_id
LEFT JOIN USERS u2 ON c.user2_id = u2.user_id
WHERE c.user1_id = ? OR c.user2_id = ?
ORDER BY c.last_message_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$chats = [];
while ($row = $result->fetch_assoc()) {
    $chat_id = $row['chat_id'];
    
    // Get messages for this chat
    $msg_sql = "SELECT 
        m.message_id,
        m.sender_id,
        m.receiver_id,
        m.message,
        m.message_type,
        m.file_url,
        m.is_read,
        m.created_at,
        u.name as sender_name
    FROM private_chat_messages m
    LEFT JOIN USERS u ON m.sender_id = u.user_id
    WHERE m.chat_id = ?
    ORDER BY m.created_at ASC";
    
    $msg_stmt = $conn->prepare($msg_sql);
    $msg_stmt->bind_param("i", $chat_id);
    $msg_stmt->execute();
    $msg_result = $msg_stmt->get_result();
    
    $messages = [];
    while ($msg = $msg_result->fetch_assoc()) {
        $messages[] = [
            'message_id' => $msg['message_id'],
            'sender_id' => $msg['sender_id'],
            'receiver_id' => $msg['receiver_id'],
            'sender_name' => $msg['sender_name'],
            'message' => $msg['message'],
            'message_type' => $msg['message_type'],
            'file_url' => $msg['file_url'],
            'is_read' => (int)$msg['is_read'],
            'created_at' => $msg['created_at']
        ];
    }
    
    $chats[] = [
        'chat_id' => $row['chat_id'],
        'chat_type' => 'private',
        'other_user_id' => $row['other_user_id'],
        'other_user_name' => $row['other_user_name'],
        'other_user_image' => $row['other_user_image'],
        'last_message' => $row['last_message'],
        'last_message_time' => $row['last_message_time'],
        'unread_count' => (int)$row['unread_count'],
        'created_at' => $row['created_at'],
        'messages' => $messages,
        'total_messages' => count($messages)
    ];
}

echo json_encode([
    "success" => true, 
    "chats" => $chats,
    "total_chats" => count($chats)
]);
?>
