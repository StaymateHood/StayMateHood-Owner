<?php
header('Content-Type: application/json');
include '../dbconn.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$group_id = $data['group_id'] ?? $_POST['group_id'] ?? '';
$user_id = $data['user_id'] ?? $_POST['user_id'] ?? '';
$role = $data['role'] ?? $_POST['role'] ?? 'member';

if (empty($group_id) || empty($user_id)) {
    echo json_encode(["success" => false, "message" => "Group ID and User ID are required."]);
    exit;
}

// Check if already member
$sql = "SELECT id FROM community_members WHERE group_id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $group_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "User is already a member of this group."]);
    exit;
}

// Add member
$insert_sql = "INSERT INTO community_members (group_id, user_id, role) VALUES (?, ?, ?)";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("iis", $group_id, $user_id, $role);

if ($insert_stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Joined group successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to join group."]);
}
?>