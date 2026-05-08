<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require '../dbconn.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['property_id'])) {
    $property_id = intval($_GET['property_id']);

    $query = "
        SELECT 
            COUNT(B.booking_id) AS total_tenants,
            SUM(CASE 
                    WHEN (P.payment_id IS NULL OR P.payment_status = 'Pending') 
                    THEN 1 ELSE 0 
                END) AS pending_dues,
            SUM(CASE 
                    WHEN B.booking_status = 'Pending' 
                    THEN 1 ELSE 0 
                END) AS pending_approvals    
        FROM BOOKINGS B
        INNER JOIN USERS U ON B.user_id = U.user_id
        LEFT JOIN PAYMENTS P ON P.booking_id = B.booking_id
        WHERE U.user_type = 'tenant' AND B.booking_status != 'Cancelled' AND B.property_id = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $property_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'total_tenants' => intval($row['total_tenants']),
        'pending_dues' => intval($row['pending_dues']),
        'pending_approvals' => intval($row['pending_approvals'])
    ]);

} else {
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid property_id'
    ]);
}
?>
