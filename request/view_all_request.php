<?php
header('Content-Type: application/json');
require_once '../dbconn.php';

$sql = "SELECT * FROM SERVICE_REQUEST ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result) {
    $requests = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['requests' => $requests], JSON_PRETTY_PRINT);
} else {
    echo json_encode(['error' => 'Unable to fetch requests']);
}

$conn->close();
?>
