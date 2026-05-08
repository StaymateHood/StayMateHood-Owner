<?php
header('Content-Type: application/json');
include '../dbconn.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$name = $data['name'] ?? $_POST['name'] ?? '';
$description = $data['description'] ?? $_POST['description'] ?? '';
$created_by = $data['created_by'] ?? $_POST['created_by'] ?? '';
$group_type = $data['group_type'] ?? $_POST['group_type'] ?? 'mixed';

if (empty($name) || empty($created_by)) {
    echo json_encode(["success" => false, "message" => "Group name and Created By are required."]);
    exit;
}

// Insert group
$sql = "INSERT INTO community_groups (name, description, created_by, group_type) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssis", $name, $description, $created_by, $group_type);

if ($stmt->execute()) {
    $group_id = $conn->insert_id;

    // Add creator as admin member
    $member_sql = "INSERT INTO community_members (group_id, user_id, role) VALUES (?, ?, 'admin')";
    $member_stmt = $conn->prepare($member_sql);
    $member_stmt->bind_param("ii", $group_id, $created_by);
    $member_stmt->execute();

    echo json_encode(["success" => true, "group_id" => $group_id, "message" => "Group created successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to create group."]);
}
?>