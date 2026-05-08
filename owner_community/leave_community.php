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

// Check if member exists
$member_check = "SELECT role, member_id FROM owner_community_members WHERE community_id = ? AND owner_id = ? AND is_active = 1";
$member_stmt = $conn->prepare($member_check);
$member_stmt->bind_param("ii", $community_id, $owner_id);
$member_stmt->execute();
$member_result = $member_stmt->get_result();

if ($member_result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "You are not a member of this community."]);
    exit;
}

$member_data = $member_result->fetch_assoc();

// Check if user is the creator/admin - prevent leaving if they're the only admin
if ($member_data['role'] === 'admin') {
    $admin_count = "SELECT COUNT(*) as admin_count FROM owner_community_members WHERE community_id = ? AND role = 'admin' AND is_active = 1";
    $admin_stmt = $conn->prepare($admin_count);
    $admin_stmt->bind_param("i", $community_id);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    $admin_data = $admin_result->fetch_assoc();
    
    if ($admin_data['admin_count'] <= 1) {
        echo json_encode(["success" => false, "message" => "Cannot leave community. You are the only admin. Transfer admin rights first or delete the community."]);
        exit;
    }
}

// Remove member
$leave_sql = "UPDATE owner_community_members SET is_active = 0 WHERE community_id = ? AND owner_id = ?";
$leave_stmt = $conn->prepare($leave_sql);
$leave_stmt->bind_param("ii", $community_id, $owner_id);

if ($leave_stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Successfully left the community."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to leave community."]);
}
?>