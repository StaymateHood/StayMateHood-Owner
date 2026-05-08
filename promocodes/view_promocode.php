<?php
header("Content-Type: application/json");
require_once '../dbconn.php';

$response = ["success" => false];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['code'])) {
            // View a single promocode by code
            $code = $_GET['code'];
            $sql = "SELECT * FROM PROMOCODES WHERE code = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $result = $stmt->get_result();
            $promocode = $result->fetch_assoc();

            if ($promocode) {
                $response['success'] = true;
                $response['promocode'] = $promocode;
            } else {
                $response['message'] = 'Promocode not found.';
            }
        } else {
            // View all active promocodes
            $sql = "SELECT * FROM PROMOCODES WHERE is_active = 1 ";
            $result = $conn->query($sql);
            //AND valid_from <= CURDATE() AND valid_to >= CURDATE()

            $promocodes = [];
            while ($row = $result->fetch_assoc()) {
                $promocodes[] = $row;
            }

            $response['success'] = true;
            $response['promocodes'] = $promocodes;
        }
    } else {
        $response['message'] = 'Invalid request method.';
    }
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>
