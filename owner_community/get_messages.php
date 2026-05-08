<?php
header('Content-Type: application/json');
include '../dbconn.php';
include 'firebase_config.php';

$community_id = $_GET['community_id'] ?? '';
$owner_id = $_GET['owner_id'] ?? '';
$limit = $_GET['limit'] ?? 50;

if (empty($community_id) || empty($owner_id)) {
    echo json_encode(["success" => false, "message" => "Community ID and Owner ID are required."]);
    exit;
}

// Verify owner is a member of the community
$member_check = "SELECT role FROM owner_community_members WHERE community_id = ? AND owner_id = ? AND is_active = 1";
$member_stmt = $conn->prepare($member_check);
$member_stmt->bind_param("ii", $community_id, $owner_id);
$member_stmt->execute();
$member_result = $member_stmt->get_result();

if ($member_result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "You are not a member of this community."]);
    exit;
}

// Get messages from Firebase
$firebase = new FirebaseOwnerCommunity();
$messages = $firebase->getMessages($community_id, $limit);

// Sort messages by timestamp (oldest first for chat display)
usort($messages, function($a, $b) {
    return ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0);
});

echo json_encode([
    "success" => true,
    "total" => count($messages),
    "messages" => $messages
]);
?>