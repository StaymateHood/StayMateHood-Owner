<?php
header('Content-Type: application/json');
include '../dbconn.php';

$group_id = $_GET['group_id'] ?? '';

if (empty($group_id)) {
    echo json_encode(["success" => false, "message" => "Group ID is required."]);
    exit;
}

// Verify group exists
$group_check = "SELECT group_id, name, description FROM community_groups WHERE group_id = ? AND is_active = 1";
$group_stmt = $conn->prepare($group_check);
$group_stmt->bind_param("i", $group_id);
$group_stmt->execute();
$group_result = $group_stmt->get_result();

if ($group_result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Group not found."]);
    exit;
}

$group_data = $group_result->fetch_assoc();

// Get all members of the group
$sql = "SELECT 
    cm.id,
    cm.group_id,
    cm.user_id,
    cm.role,
    cm.joined_at,
    u.name,
    u.email,
    u.phone,
    u.profile_image,
    u.user_type,
    COUNT(DISTINCT b.booking_id) as total_bookings
FROM community_members cm
LEFT JOIN USERS u ON cm.user_id = u.user_id
LEFT JOIN BOOKINGS b ON u.user_id = b.user_id AND b.booking_status = 'Active'
WHERE cm.group_id = ?
GROUP BY cm.id, cm.group_id, cm.user_id, cm.role, cm.joined_at, u.name, u.email, u.phone, u.profile_image, u.user_type
ORDER BY cm.role DESC, u.name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $group_id);
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
    "group" => [
        "group_id" => $group_data['group_id'],
        "name" => $group_data['name'],
        "description" => $group_data['description']
    ],
    "total_members" => count($members),
    "members" => $members
]);
?>