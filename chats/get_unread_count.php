<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include '../dbconn.php';

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

try {
    // Get unread count for one-to-one chats
    $stmt = $conn->prepare("
        SELECT 
            pc.chat_id,
            COUNT(pcm.message_id) as unread_count,
            MAX(pcm.created_at) as last_message_time
        FROM private_chats pc
        LEFT JOIN private_chat_messages pcm ON pc.chat_id = pcm.chat_id
        WHERE (pc.user1_id = ? OR pc.user2_id = ?)
        AND pcm.receiver_id = ?
        AND pcm.is_read = 0
        GROUP BY pc.chat_id
        HAVING unread_count > 0
    ");
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $chats = [];
    $total_unread = 0;
    
    while ($row = $result->fetch_assoc()) {
        $chats[] = $row;
        $total_unread += $row['unread_count'];
    }
    $stmt->close();
    
    // Get unread count for group chats
    $stmt = $conn->prepare("
        SELECT 
            cg.group_id,
            cg.name as group_name,
            COUNT(cm.message_id) as unread_count,
            MAX(cm.created_at) as last_message_time
        FROM community_groups cg
        INNER JOIN community_members cmem ON cg.group_id = cmem.group_id
        LEFT JOIN community_messages cm ON cg.group_id = cm.group_id
        WHERE cmem.user_id = ?
        AND cm.sender_id != ?
        AND (cm.read_by IS NULL OR NOT FIND_IN_SET(?, cm.read_by))
        GROUP BY cg.group_id, cg.name
        HAVING unread_count > 0
    ");
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $groups = [];
    $total_group_unread = 0;
    
    while ($row = $result->fetch_assoc()) {
        $groups[] = $row;
        $total_group_unread += $row['unread_count'];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'chats' => $chats,
        'groups' => $groups,
        'total_unread_chats' => $total_unread,
        'total_unread_groups' => $total_group_unread,
        'total_unread' => $total_unread + $total_group_unread
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
