<?php
header('Content-Type: application/json');
require_once '../dbconn.php';

$response = ['success' => false];

if (!isset($_POST['tutorial_id'])) {
    $response['message'] = 'Missing tutorial_id';
    echo json_encode($response);
    exit;
}

$tutorial_id = intval($_POST['tutorial_id']);

$sql = "DELETE FROM TUTORIALS WHERE tutorial_id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    $response['message'] = 'Prepare failed: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param("i", $tutorial_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Tutorial deleted successfully';
    } else {
        $response['message'] = 'Tutorial not found';
    }
} else {
    $response['message'] = 'Delete failed: ' . $stmt->error;
}

echo json_encode($response);
?>
