<?php
header('Content-Type: application/json');
require_once '../dbconn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Only POST requests are allowed']);
    exit;
}

$request_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$new_status = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($request_id <= 0 || empty($new_status)) {
    echo json_encode(['error' => 'Missing or invalid request ID or status']);
    exit;
}

$allowed_statuses = ['pending', 'in-progress', 'resolved', 'closed', 'rejected'];

if (!in_array(strtolower($new_status), $allowed_statuses)) {
    echo json_encode(['error' => 'Invalid status value']);
    exit;
}

$sql = "UPDATE SERVICE_REQUEST SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $new_status, $request_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
} else {
    echo json_encode(['error' => 'Database update failed']);
}

$stmt->close();
$conn->close();
?>
