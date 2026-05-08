<?php
header('Content-Type: application/json');
include '../dbconn.php';

$sql = "SELECT 
    cg.group_id,
    cg.name,
    cg.description,
    cg.group_type,
    cg.created_by,
    cg.last_message,
    cg.last_message_time,
    cg.created_at,
    cg.is_active,
    u.name as creator_name,
    COUNT(DISTINCT cm.user_id) as member_count
FROM community_groups cg
LEFT JOIN USERS u ON cg.created_by = u.user_id
LEFT JOIN community_members cm ON cg.group_id = cm.group_id
WHERE cg.is_active = 1
GROUP BY cg.group_id, cg.name, cg.description, cg.group_type, cg.created_by, cg.last_message, cg.last_message_time, cg.created_at, cg.is_active, u.name
ORDER BY cg.created_at DESC";

$result = $conn->query($sql);

$groups = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }
}

echo json_encode([
    "success" => true,
    "total" => count($groups),
    "groups" => $groups
]);
?>
