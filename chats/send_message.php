<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
include '../dbconn.php';
require '../vendor/autoload.php';
require '../env.php';
// require 'send_chat_notification.php'; // Include notification sender

// use Kreait\Firebase\Factory;

// // Initialize Firebase
// $factory = (new Factory)->withServiceAccount('../firebase-notifications/sand111-firebase-adminsdk-8cfhs-5a1ae6c94d.json');
// $database = $factory->createDatabase();

// Get POST data
$chat_id = $_POST['chat_id'] ?? '';
$sender_id = $_POST['sender_id'] ?? '';
$message = $_POST['message'] ?? '';
$message_type = $_POST['message_type'] ?? 'text';

if (empty($chat_id) || empty($sender_id) || empty($message)) {
    echo json_encode(["success" => false, "message" => "Chat ID, Sender ID, and Message are required."]);
    exit;
}

// Get receiver_id first
$stmt2 = $conn->prepare("SELECT user1_id, user2_id FROM private_chats WHERE chat_id = ?");
$stmt2->bind_param("i", $chat_id);
$stmt2->execute();
$result = $stmt2->get_result();
$chat = $result->fetch_assoc();
$stmt2->close();

if (!$chat) {
    echo json_encode(["success" => false, "message" => "Chat not found."]);
    exit;
}

$receiver_id = ($chat['user1_id'] == $sender_id) ? $chat['user2_id'] : $chat['user1_id'];

// Insert message into database
$sql = "INSERT INTO private_chat_messages (chat_id, sender_id, receiver_id, message, message_type) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiss", $chat_id, $sender_id, $receiver_id, $message, $message_type);

if ($stmt->execute()) {
    $message_id = $conn->insert_id;

    // Push to Firebase for real-time
    $firebase_path = 'chats/' . $chat_id . '/messages/' . $message_id;
    $data = [
        'sender_id' => $sender_id,
        'message' => $message,
        'message_type' => $message_type,
        'timestamp' => time() * 1000,
        'is_read' => false
    ];
    // $database->getReference($firebase_path)->set($data);
    
    // Send push notification to receiver
    // sendChatNotification($receiver_id, $sender_id, $message, 'private', $chat_id, null);

    echo json_encode(["success" => true, "message_id" => $message_id, "message" => "Message sent successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to send message."]);
}
?>