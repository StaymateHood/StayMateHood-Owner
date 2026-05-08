<?php
header('Content-Type: application/json');
require '../dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket_id = $_POST['ticket_id'] ?? null;
    $status    = $_POST['status'] ?? null;

    if (!$ticket_id || !$status) {
        echo json_encode(['success' => false, 'message' => 'Missing ticket_id or status']);
        exit;
    }

    $updated_at = date('Y-m-d H:i:s');
    $resolved_at = null;
    $closed_at = null;

    if ($status === 'Resolved') {
        $resolved_at = $updated_at;
    } elseif ($status === 'Closed') {
        $closed_at = $updated_at;
    }

    $stmt = $conn->prepare("UPDATE TICKETS 
        SET status = ?, updated_at = ?, resolved_at = ?, closed_at = ?
        WHERE ticket_id = ?");
    
    $stmt->bind_param("ssssi", $status, $updated_at, $resolved_at, $closed_at, $ticket_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Ticket status updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
