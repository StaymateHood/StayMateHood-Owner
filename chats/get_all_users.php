<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
include '../dbconn.php';

$property_id = $_GET['property_id'] ?? '';

if (empty($property_id)) {
    echo json_encode(["success" => false, "message" => "Property ID is required."]);
    exit;
}

// Get property owner
$owner_sql = "SELECT owner_id FROM PROPERTY WHERE property_id = ?";
$owner_stmt = $conn->prepare($owner_sql);
$owner_stmt->bind_param("i", $property_id);
$owner_stmt->execute();
$owner_result = $owner_stmt->get_result();

if ($owner_result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Property not found."]);
    exit;
}

$owner_row = $owner_result->fetch_assoc();
$owner_id = $owner_row['owner_id'];

// Get all tenants and owner for this property
$sql = "SELECT DISTINCT u.user_id, u.name, u.email, u.phone, u.user_type, u.profile_image, u.created_at
FROM USERS u
WHERE u.user_id IN (
    SELECT user_id FROM BOOKINGS WHERE property_id = ?
)
ORDER BY u.user_type DESC, u.name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Get groups created by the property owner
$groups_sql = "SELECT 
    cg.group_id,
    cg.name,
    cg.description,
    cg.group_type,
    cg.created_by,
    cg.last_message,
    cg.last_message_time,
    cg.created_at,
    cg.is_active
FROM community_groups cg
WHERE cg.created_by = ? AND cg.is_active = 1
ORDER BY cg.created_at DESC";

$groups_stmt = $conn->prepare($groups_sql);
$groups_stmt->bind_param("i", $owner_id);
$groups_stmt->execute();
$groups_result = $groups_stmt->get_result();

$firebase_base_url = "https://sand111.firebaseio.com/";

function getFirebaseMemberCount($firebase_base_url, $group_id) {
    $url = $firebase_base_url . "groups/" . $group_id . "/members.json";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return is_array($data) ? count($data) : 0;
}

$groups = [];
if ($groups_result->num_rows > 0) {
    while ($row = $groups_result->fetch_assoc()) {
        $row['member_count'] = getFirebaseMemberCount($firebase_base_url, $row['group_id']);
        $groups[] = $row;
    }
}

echo json_encode([
    "success" => true,
    "property_id" => $property_id,
    "total" => count($users),
    "users" => $users,
    "groups" => $groups
]);
?>
