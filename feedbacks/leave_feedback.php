<?php
header("Content-Type: application/json");
include '../dbconn.php';
require_once '../vendor/autoload.php';
require '../env.php'; // Must define $secret_key

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$response = ["success" => false];

// JWT Authentication
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    echo json_encode(["success" => false, "message" => "Authorization token missing"]);
    exit;
}

$token = str_replace('Bearer ', '', $authHeader);
try {
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
     $user_id = $decoded->sub ?? null;

    if (!$user_id) {
        echo json_encode(["success" => false, "message" => "Invalid token (missing user_id)"]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Token error: " . $e->getMessage()]);
    exit;
}

$response = ["success" => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ( isset($_POST['property_id']) && isset($_POST['rating'])) {
        //$user_id = $_POST['user_id'];
        $property_id = $_POST['property_id'];
        $rating = intval($_POST['rating']);
        $comment = isset($_POST['comment']) ? $_POST['comment'] : '';
        
        $check = $conn->prepare("SELECT property_id FROM PROPERTY WHERE property_id = ? LIMIT 1");
        $check->bind_param("i", $property_id);
        $check->execute();
        $res = $check->get_result();
        if ($res->num_rows === 0) {
            echo json_encode([
                "success" => false,
                "message" => "property does not exist."
            ]);
            exit;
        }
        $check->close();

        try {
            $sql = "INSERT INTO FEEDBACKS (user_id, property_id, rating, comment, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiis", $user_id, $property_id, $rating, $comment);
            $stmt->execute();

            $response['success'] = true;
            $response['message'] = "Feedback submitted successfully.";
        } catch (Exception $e) {
            $response['message'] = "Error: " . $e->getMessage();
        }
    } else {
        $response['message'] = "Missing required fields.";
    }
} else {
    $response['message'] = "Invalid request method.";
}

echo json_encode($response);
?>
