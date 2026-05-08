<?php
header('Content-Type: application/json');
include '../dbconn.php';
require '../vendor/autoload.php';
require '../env.php';
require 'send_chat_notification.php'; // Include notification sender

use Kreait\Firebase\Factory;

// Initialize Firebase
$factory = (new Factory)->withServiceAccount('../firebase-notifications/sand111-firebase-adminsdk-8cfhs-5a1ae6c94d.json');
$database = $factory->createDatabase();

// Get POST data
$group_id = $_POST['group_id'] ?? '';
$sender_id = $_POST['sender_id'] ?? '';
$message = $_POST['message'] ?? '';
$message_type = $_POST['message_type'] ?? 'text';

if (empty($group_id) || empty($sender_id) || empty($message)) {
    echo json_encode(["success" => false, "message" => "Group ID, Sender ID, and Message are required."]);
    exit;
}

// Check if sender is member
$check_sql = "SELECT id FROM community_members WHERE group_id = ? AND user_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $group_id, $sender_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "User is not a member of this group."]);
    exit;
}

// Insert message
$sql = "INSERT INTO community_messages (group_id, sender_id, message, message_type) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiss", $group_id, $sender_id, $message, $message_type);

if ($stmt->execute()) {
    $message_id = $conn->insert_id;

    // Push to Firebase (use groups path for consistency)
    $firebase_path = 'groups/' . $group_id . '/messages/' . $message_id;
    $data = [
        'sender_id' => $sender_id,
        'message' => $message,
        'message_type' => $message_type,
        'timestamp' => time() * 1000,
        'read_by' => []
    ];
    $database->getReference($firebase_path)->set($data);
    
    // Update group last activity
    $database->getReference('groups/' . $group_id)->update([
        'last_message' => $message,
        'last_message_time' => time() * 1000,
        'last_sender_id' => $sender_id
    ]);
    
    // Send push notification to all group members
    sendGroupNotification($group_id, $sender_id, $message);

    echo json_encode(["success" => true, "message_id" => $message_id, "message" => "Message sent successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to send message."]);
}
?>