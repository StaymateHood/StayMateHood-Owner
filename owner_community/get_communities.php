<?php
header('Content-Type: application/json');
include '../dbconn.php';

// Get all active communities
$sql = "SELECT 
    oc.community_id,
    oc.name,
    oc.description,
    oc.created_by,
    oc.community_type,
    oc.last_message,
    oc.last_message_time,
    oc.created_at,
    u.name as creator_name,
    u.profile_image as creator_image,
    u.email as creator_email,
    COUNT(DISTINCT ocm.owner_id) as member_count
FROM owner_communities oc
LEFT JOIN USERS u ON oc.created_by = u.user_id
LEFT JOIN owner_community_members ocm ON oc.community_id = ocm.community_id AND ocm.is_active = 1
WHERE oc.is_active = 1
GROUP BY oc.community_id, oc.name, oc.description, oc.created_by, oc.community_type, oc.last_message, oc.last_message_time, oc.created_at, u.name, u.profile_image, u.email
ORDER BY oc.last_message_time DESC, oc.created_at DESC";

$result = $conn->query($sql);

$communities = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $communities[] = $row;
    }
}

echo json_encode([
    "success" => true,
    "total" => count($communities),
    "communities" => $communities
]);
?>