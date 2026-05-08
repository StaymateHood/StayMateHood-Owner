<?php
header("Content-Type: application/json");
require_once '../dbconn.php';

$response = ["success" => false];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $sql = "SELECT F.feedback_id, F.user_id, U.name AS user_name, F.property_id, P.name AS property_name,
                       F.rating, F.comment, F.created_at
                FROM FEEDBACKS F
                JOIN USERS U ON F.user_id = U.user_id
                JOIN PROPERTY P ON F.property_id = P.property_id
                ORDER BY F.created_at DESC";
        $result = $conn->query($sql);

        $feedbacks = [];
        while ($row = $result->fetch_assoc()) {
            $feedbacks[] = $row;
        }

        $response['success'] = true;
        $response['feedbacks'] = $feedbacks;
    } catch (Exception $e) {
        $response['message'] = "Error: " . $e->getMessage();
    }
} else {
    $response['message'] = "Invalid request method.";
}

echo json_encode($response);
?>
