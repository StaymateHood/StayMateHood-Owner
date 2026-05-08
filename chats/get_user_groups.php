<?php
header('Content-Type: application/json');
include '../dbconn.php';

// Get GET data
$user_id = $_GET['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(["success" => false, "message" => "User ID is required."]);
    exit;
}

// Get groups with member count and last message
$sql = "SELECT 
    cg.group_id,
    cg.name,
    cg.description,
    cg.group_type,
    cg.created_by,
    cg.last_message,
    cg.last_message_time,
    cg.created_at,
    cm.role,
    COUNT(DISTINCT cm2.user_id) as member_count
FROM community_groups cg
INNER JOIN community_members cm ON cg.group_id = cm.group_id AND cm.user_id = ?
LEFT JOIN community_members cm2 ON cg.group_id = cm2.group_id
WHERE cg.is_active = 1
GROUP BY cg.group_id, cg.name, cg.description, cg.group_type, cg.created_by, cg.last_message, cg.last_message_time, cg.created_at, cm.role
ORDER BY cg.last_message_time DESC, cg.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$groups = [];
while ($row = $result->fetch_assoc()) {
    $groups[] = $row;
}

echo json_encode(["success" => true, "groups" => $groups]);
?>