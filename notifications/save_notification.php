<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include '../dbconn.php';
include '../token.php';   // JWT verification logic
require_once '../notifications/notify.php';  // notification helpers

    function pushAndSaveNotification($conn, $user_id, $user_type, $title, $msg, $data = []) {
        $notif_sql = "INSERT INTO NOTIFICATIONS 
            (user_id, title, message, created_at, is_read, notification_type)
            VALUES (?, ?, ?, NOW(), 0, ?)";
        $stmt = $conn->prepare($notif_sql);
        $stmt->bind_param("isss", $user_id, $title, $msg, $user_type);
        $stmt->execute();
        $notif_id = $conn->insert_id;
    
        // 2. Fetch device tokens for this user
        $tok_stmt = $conn->prepare("SELECT token FROM PUSH_TOKENS WHERE userid=?");
        $tok_stmt->bind_param("i", $user_id);
        $tok_stmt->execute();
        $res = $tok_stmt->get_result();
    
        $tokens = [];
        while ($row = $res->fetch_assoc()) {
            $tokens[] = $row['token'];
        }
    
        // 3. Send push notification for each token
        $responses = [];
        foreach ($tokens as $deviceToken) {
            $responses[] = sendPushNotification(
                $deviceToken,
                $title,
                $msg,
                array_merge($data, ["notif_id" => $notif_id])
            );
        }
    
        // 4. Return response
        return [
            "success" => true,
            "notification_id" => $notif_id,
            "sent_to" => count($tokens),
            "responses" => $responses
        ];
    }
        
?>
