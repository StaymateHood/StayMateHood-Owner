<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once '../dbconn.php';

$response = ['success' => false];

$sql = "SELECT id, contact_us, chat_with_us, support_email, question, answer FROM SUPPORT";

$result = $conn->query($sql);

if ($result) {
    $supportData = [];

    while ($row = $result->fetch_assoc()) {
        $supportData[] = $row;
    }

    $response['success'] = true;
    $response['data'] = $supportData;
} else {
    $response['message'] = 'Error fetching support data: ' . $conn->error;
}

echo json_encode($response);
?>
