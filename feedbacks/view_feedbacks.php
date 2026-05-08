<?php
header("Content-Type: application/json");
require_once '../dbconn.php';

$response = ["success" => false];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['property_id'])) {
        $property_id = $_GET['property_id'];

        try {
            $sql = "SELECT F.feedback_id, F.user_id, U.name AS user_name, F.rating, F.comment, F.created_at
                    FROM FEEDBACKS F
                    JOIN USERS U ON F.user_id = U.user_id
                    WHERE F.property_id = ?
                    ORDER BY F.created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $property_id);
            $stmt->execute();
            $result = $stmt->get_result();

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
        $response['message'] = "Missing property_id.";
    }
} else {
    $response['message'] = "Invalid request method.";
}

echo json_encode($response);
?>
