<?php
// Send Chat Notification
// Integrates with existing FCM setup

require '../notifications/notify.php';
include '../dbconn.php';

function sendChatNotification($receiverId, $senderId, $message, $chatType = 'private', $chatId = null, $groupId = null) {
    global $conn;
    
    try {
        // Get receiver's FCM token
        $stmt = $conn->prepare("SELECT fcm_token, name FROM USERS WHERE user_id = ?");
        $stmt->bind_param("i", $receiverId);
        $stmt->execute();
        $result = $stmt->get_result();
        $receiver = $result->fetch_assoc();
        $stmt->close();
        
        if (!$receiver || !$receiver['fcm_token']) {
            return ['success' => false, 'message' => 'No FCM token found'];
        }
        
        // Get sender info
        $stmt = $conn->prepare("SELECT name, profile_image FROM USERS WHERE user_id = ?");
        $stmt->bind_param("i", $senderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $sender = $result->fetch_assoc();
        $stmt->close();
        
        // Check if chat is muted
        $stmt = $conn->prepare("
            SELECT is_muted, mute_until 
            FROM chat_notification_preferences 
            WHERE user_id = ? AND (chat_id = ? OR group_id = ?)
        ");
        $stmt->bind_param("iii", $receiverId, $chatId, $groupId);
        $stmt->execute();
        $result = $stmt->get_result();
        $pref = $result->fetch_assoc();
        $stmt->close();
        
        if ($pref && $pref['is_muted']) {
            if ($pref['mute_until'] && strtotime($pref['mute_until']) > time()) {
                return ['success' => false, 'message' => 'Chat is muted'];
            }
        }
        
        // Prepare notification
        $title = $sender['name'] ?? 'New Message';
        $body = substr($message, 0, 100); // Limit message length
        
        if ($chatType === 'group') {
            // Get group name
            $stmt = $conn->prepare("SELECT name FROM community_groups WHERE group_id = ?");
            $stmt->bind_param("i", $groupId);
            $stmt->execute();
            $result = $stmt->get_result();
            $group = $result->fetch_assoc();
            $stmt->close();
            
            $title = $group['name'] ?? 'Group Chat';
            $body = ($sender['name'] ?? 'Someone') . ': ' . $body;
        }
        
        // Notification data
        $data = [
            'type' => 'chat_message',
            'chat_type' => $chatType,
            'chat_id' => (string)$chatId,
            'group_id' => (string)$groupId,
            'sender_id' => (string)$senderId,
            'sender_name' => $sender['name'] ?? '',
            'message' => $message,
            'timestamp' => (string)time()
        ];
        
        // Send FCM notification
        $response = sendPushNotification(
            $receiver['fcm_token'],
            $title,
            $body,
            $data
        );
        
        // Log notification
        $stmt = $conn->prepare("
            INSERT INTO chat_notifications_log 
            (user_id, chat_id, group_id, notification_type, title, body, sent_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $notifType = $chatType === 'group' ? 'group_message' : 'message';
        $stmt->bind_param("iiisss", $receiverId, $chatId, $groupId, $notifType, $title, $body);
        $stmt->execute();
        $stmt->close();
        
        return [
            'success' => true,
            'message' => 'Notification sent',
            'response' => $response
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

// Send notification to all group members
function sendGroupNotification($groupId, $senderId, $message) {
    global $conn;
    
    try {
        // Get all group members except sender
        $stmt = $conn->prepare("
            SELECT user_id 
            FROM community_members 
            WHERE group_id = ? AND user_id != ?
        ");
        $stmt->bind_param("ii", $groupId, $senderId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sent = 0;
        $failed = 0;
        
        while ($row = $result->fetch_assoc()) {
            $response = sendChatNotification(
                $row['user_id'],
                $senderId,
                $message,
                'group',
                null,
                $groupId
            );
            
            if ($response['success']) {
                $sent++;
            } else {
                $failed++;
            }
        }
        $stmt->close();
        
        return [
            'success' => true,
            'sent' => $sent,
            'failed' => $failed
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

// If called directly (for testing)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $receiverId = $data['receiver_id'] ?? null;
    $senderId = $data['sender_id'] ?? null;
    $message = $data['message'] ?? '';
    $chatType = $data['chat_type'] ?? 'private';
    $chatId = $data['chat_id'] ?? null;
    $groupId = $data['group_id'] ?? null;
    
    if (!$receiverId || !$senderId || !$message) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    $result = sendChatNotification($receiverId, $senderId, $message, $chatType, $chatId, $groupId);
    echo json_encode($result);
}
?>
