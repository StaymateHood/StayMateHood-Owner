<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

include '../dbconn.php';
require '../vendor/autoload.php';

// use Kreait\Firebase\Factory;

// // Initialize Firebase
// $factory = (new Factory)->withServiceAccount('../firebase-notifications/sand111-firebase-adminsdk-8cfhs-5a1ae6c94d.json');
// $database = $factory->createDatabase();

$data = json_decode(file_get_contents('php://input'), true);

$chat_id = $data['chat_id'] ?? null;
$user_id = $data['user_id'] ?? null;
$message_ids = $data['message_ids'] ?? []; // Array of message IDs to mark as read

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Mark messages as read in MySQL
    if ($chat_id) {
        // One-to-one chat
        $stmt = $conn->prepare("
            UPDATE private_chat_messages 
            SET is_read = 1, read_at = NOW() 
            WHERE chat_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $stmt->bind_param("ii", $chat_id, $user_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        // Update Firebase read status
        // $messagesRef = $database->getReference("chats/{$chat_id}/messages");
        // $messages = $messagesRef->getValue();
        
        // if ($messages) {
        //     foreach ($messages as $msgId => $msgData) {
        //         if (isset($msgData['receiver_id']) && $msgData['receiver_id'] == $user_id) {
        //             $database->getReference("chats/{$chat_id}/messages/{$msgId}")->update([
        //                 'is_read' => true,
        //                 'read_at' => time() * 1000
        //             ]);
        //         }
        //     }
        // }
        
        // Update unread count
        // $database->getReference("unread_counts/{$user_id}/chat_{$chat_id}")->set(0);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Chat ID required']);
        exit;
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Messages marked as read',
        'affected_rows' => $affected
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
