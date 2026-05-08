<?php
header('Content-Type: application/json');
include '../dbconn.php';
include 'firebase_config.php';

$data = json_decode(file_get_contents('php://input'), true);
$community_id = $data['community_id'] ?? '';
$sender_id = $data['sender_id'] ?? '';
$message = $data['message'] ?? '';
$message_type = $data['message_type'] ?? 'text';
$file_url = $data['file_url'] ?? '';

if (empty($community_id) || empty($sender_id) || empty($message)) {
    echo json_encode(["success" => false, "message" => "Community ID, sender ID, and message are required."]);
    exit;
}

// Verify sender is a member of the community
$member_check = "SELECT role FROM owner_community_members WHERE community_id = ? AND owner_id = ? AND is_active = 1";
$member_stmt = $conn->prepare($member_check);
$member_stmt->bind_param("ii", $community_id, $sender_id);
$member_stmt->execute();
$member_result = $member_stmt->get_result();

if ($member_result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "You are not a member of this community."]);
    exit;
}

// Get sender details
$sender_sql = "SELECT name, profile_image FROM USERS WHERE user_id = ?";
$sender_stmt = $conn->prepare($sender_sql);
$sender_stmt->bind_param("i", $sender_id);
$sender_stmt->execute();
$sender_result = $sender_stmt->get_result();
$sender_data = $sender_result->fetch_assoc();

// Prepare message data for Firebase
$timestamp = time() * 1000;
$messageData = [
    'community_id' => (int)$community_id,
    'sender_id' => (int)$sender_id,
    'sender_name' => $sender_data['name'] ?? 'Unknown',
    'sender_image' => $sender_data['profile_image'] ?? '',
    'message' => $message,
    'message_type' => $message_type,
    'file_url' => $file_url,
    'timestamp' => $timestamp,
    'sent_at' => date('Y-m-d H:i:s'),
    'is_deleted' => false
];

// Send message to Firebase
$firebase = new FirebaseOwnerCommunity();
$firebaseResult = $firebase->sendMessage($community_id, $messageData);

if ($firebaseResult['success']) {
    // Update community last message in MySQL
    $update_sql = "UPDATE owner_communities SET last_message = ?, last_message_time = NOW() WHERE community_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $message, $community_id);
    $update_stmt->execute();
    
    // Also update last message in Firebase
    $firebase->updateCommunityLastMessage($community_id, $message, $timestamp, $sender_data['name'] ?? 'Unknown');

    echo json_encode([
        "success" => true, 
        "message_id" => $firebaseResult['response']['name'] ?? null,
        "message" => "Message sent successfully.",
        "timestamp" => $timestamp
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to send message to Firebase.", "error" => $firebaseResult['error'] ?? '']);
}
?>