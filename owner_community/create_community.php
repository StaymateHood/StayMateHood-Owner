<?php
header('Content-Type: application/json');
include '../dbconn.php';

$data = json_decode(file_get_contents('php://input'), true);
$name = $data['name'] ?? '';
$description = $data['description'] ?? '';
$created_by = $data['created_by'] ?? '';
$community_type = $data['community_type'] ?? 'public';

if (empty($name) || empty($created_by)) {
    echo json_encode(["success" => false, "message" => "Community name and creator ID are required."]);
    exit;
}

// Verify creator is an owner
$owner_check = "SELECT user_type FROM USERS WHERE user_id = ?";
$owner_stmt = $conn->prepare($owner_check);
$owner_stmt->bind_param("i", $created_by);
$owner_stmt->execute();
$owner_result = $owner_stmt->get_result();

if ($owner_result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "User not found."]);
    exit;
}

$user_data = $owner_result->fetch_assoc();
if ($user_data['user_type'] !== 'owner') {
    echo json_encode(["success" => false, "message" => "Only owners can create communities."]);
    exit;
}

// Create community
$sql = "INSERT INTO owner_communities (name, description, created_by, community_type) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssis", $name, $description, $created_by, $community_type);

if ($stmt->execute()) {
    $community_id = $conn->insert_id;

    // Add creator as admin member
    $member_sql = "INSERT INTO owner_community_members (community_id, owner_id, role) VALUES (?, ?, 'admin')";
    $member_stmt = $conn->prepare($member_sql);
    $member_stmt->bind_param("ii", $community_id, $created_by);
    $member_stmt->execute();

    echo json_encode([
        "success" => true, 
        "community_id" => $community_id, 
        "message" => "Owner community created successfully."
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to create community."]);
}
?>