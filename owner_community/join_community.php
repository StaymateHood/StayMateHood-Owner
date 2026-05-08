<?php
header('Content-Type: application/json');
include '../dbconn.php';

$data = json_decode(file_get_contents('php://input'), true);
$community_id = $data['community_id'] ?? '';
$owner_id = $data['owner_id'] ?? '';

if (empty($community_id) || empty($owner_id)) {
    echo json_encode(["success" => false, "message" => "Community ID and Owner ID are required."]);
    exit;
}

// Verify owner exists and is an owner
$owner_check = "SELECT user_type FROM USERS WHERE user_id = ?";
$owner_stmt = $conn->prepare($owner_check);
$owner_stmt->bind_param("i", $owner_id);
$owner_stmt->execute();
$owner_result = $owner_stmt->get_result();

if ($owner_result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "User not found."]);
    exit;
}

$user_data = $owner_result->fetch_assoc();
if ($user_data['user_type'] !== 'owner') {
    echo json_encode(["success" => false, "message" => "Only owners can join owner communities."]);
    exit;
}

// Check if community exists and is active
$community_check = "SELECT community_type FROM owner_communities WHERE community_id = ? AND is_active = 1";
$community_stmt = $conn->prepare($community_check);
$community_stmt->bind_param("i", $community_id);
$community_stmt->execute();
$community_result = $community_stmt->get_result();

if ($community_result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Community not found or inactive."]);
    exit;
}

// Check if already a member
$member_check = "SELECT member_id FROM owner_community_members WHERE community_id = ? AND owner_id = ?";
$member_stmt = $conn->prepare($member_check);
$member_stmt->bind_param("ii", $community_id, $owner_id);
$member_stmt->execute();
$member_result = $member_stmt->get_result();

if ($member_result->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Already a member of this community."]);
    exit;
}

// Join community
$join_sql = "INSERT INTO owner_community_members (community_id, owner_id, role) VALUES (?, ?, 'member')";
$join_stmt = $conn->prepare($join_sql);
$join_stmt->bind_param("ii", $community_id, $owner_id);

if ($join_stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Successfully joined the community."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to join community."]);
}
?>