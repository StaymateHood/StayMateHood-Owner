<?php
header('Content-Type: application/json');
require_once '../dbconn.php';

$response = ['success' => false];

if (!isset($_GET['user_type'])) {
    $response['message'] = 'Missing user_type';
    echo json_encode($response);
    exit;
}

$user_type = $_GET['user_type'];
$allowed_types = ['owner', 'tenant', 'both'];

if (!in_array($user_type, $allowed_types)) {
    $response['message'] = 'Invalid user_type';
    echo json_encode($response);
    exit;
}

$sql = "SELECT * FROM TUTORIALS WHERE user_type = ? OR user_type = 'both' ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    $response['message'] = 'Prepare failed: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param("s", $user_type);
$stmt->execute();
$result = $stmt->get_result();

$tutorials = [];

while ($row = $result->fetch_assoc()) {
    $tutorials[] = $row;
}

$response['success'] = true;
$response['tutorials'] = $tutorials;

echo json_encode($response);
?>
