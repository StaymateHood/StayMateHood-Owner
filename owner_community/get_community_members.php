<?php
header('Content-Type: application/json');
include '../dbconn.php';

$community_id = $_GET['community_id'] ?? '';

if (empty($community_id)) {
    echo json_encode(["success" => false, "message" => "Community ID is required."]);
    exit;
}

// Verify community exists
$community_check = "SELECT community_id, name, description, created_by FROM owner_communities WHERE community_id = ? AND is_active = 1";
$community_stmt = $conn->prepare($community_check);
$community_stmt->bind_param("i", $community_id);
$community_stmt->execute();
$community_result = $community_stmt->get_result();

if ($community_result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Community not found."]);
    exit;
}

$community_data = $community_result->fetch_assoc();

// Get all members of the community
$sql = "SELECT 
    ocm.member_id,
    ocm.community_id,
    ocm.owner_id,
    ocm.role,
    ocm.joined_at,
    u.name,
    u.email,
    u.phone,
    u.profile_image,
    u.user_type,
    COUNT(DISTINCT p.property_id) as total_properties
FROM owner_community_members ocm
LEFT JOIN USERS u ON ocm.owner_id = u.user_id
LEFT JOIN PROPERTY p ON u.user_id = p.owner_id AND p.property_status = 'Active'
WHERE ocm.community_id = ? AND ocm.is_active = 1
GROUP BY ocm.member_id, ocm.community_id, ocm.owner_id, ocm.role, ocm.joined_at, u.name, u.email, u.phone, u.profile_image, u.user_type
ORDER BY ocm.role DESC, u.name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $community_id);
$stmt->execute();
$result = $stmt->get_result();

$members = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
}

echo json_encode([
    "success" => true,
    "community" => [
        "community_id" => $community_data['community_id'],
        "name" => $community_data['name'],
        "description" => $community_data['description'],
        "created_by" => $community_data['created_by']
    ],
    "total_members" => count($members),
    "members" => $members
]);
?>