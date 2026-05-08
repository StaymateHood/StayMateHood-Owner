<?php
header('Content-Type: application/json');
require_once '../dbconn.php';

$response = ['success' => false];

if (!isset($_POST['id'])) {
    $response['message'] = 'Missing id';
    echo json_encode($response);
    exit;
}

$id = intval($_POST['id']);

$sql = "DELETE FROM SUPPORT WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    $response['message'] = 'Prepare failed: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Support details deleted';
    } else {
        $response['message'] = 'No record found';
    }
} else {
    $response['message'] = 'Delete failed: ' . $stmt->error;
}

echo json_encode($response);
?>
