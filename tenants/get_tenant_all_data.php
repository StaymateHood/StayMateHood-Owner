<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require '../dbconn.php';
require_once '../vendor/autoload.php';
require '../env.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// JWT Authentication
// $headers = getallheaders();
// $authHeader = $headers['Authorization'] ?? '';

// if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
//     echo json_encode(["success" => false, "message" => "Authorization token missing"]);
//     exit;
// }

// $token = str_replace('Bearer ', '', $authHeader);

// try {
//     $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
//     $user_id = $decoded->sub ?? null;

//     if (!$user_id) {
//         echo json_encode(["success" => false, "message" => "Invalid token"]);
//         exit;
//     }
// } catch (Exception $e) {
//     echo json_encode(["success" => false, "message" => "Token error: " . $e->getMessage()]);
//     exit;
// }

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['tenant_id'])) {
    
    $tenant_id = intval($_GET['tenant_id']);
    
    // Get tenant profile
    $tenant_query = "
        SELECT * FROM USERS 
        WHERE user_id = ?";
    
    $stmt = $conn->prepare($tenant_query);
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $tenant_result = $stmt->get_result();
    
    if ($tenant_result->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Tenant not found"]);
        exit;
    }
    
    $tenant_data = $tenant_result->fetch_assoc();
    
    // Get all bookings for this tenant
    $bookings_query = "
        SELECT 
            B.booking_id, B.property_id, B.room_id, B.start_date, B.end_date,
            B.booking_status, B.rent_per_month, B.security_deposit, B.notice_given_date,
            P.name AS property_name, P.address, P.property_type,
            R.room_number, R.room_type, R.attached_bath,
            
            COALESCE((
                SELECT SUM(amount_due)
                FROM BOOKING_RENT_CYCLE 
                WHERE booking_id = B.booking_id 
                AND rent_status IN ('Pending', 'Partially Paid')
            ), 0) AS rent_due,
            
            CASE 
                WHEN B.booking_status = 'Notice Given' THEN 1
                ELSE 0
            END AS under_notice
            
        FROM BOOKINGS B
        LEFT JOIN PROPERTY P ON B.property_id = P.property_id
        LEFT JOIN ROOM R ON B.room_id = R.room_id
        WHERE B.user_id = ?
        ORDER BY B.start_date DESC";
    
    $stmt = $conn->prepare($bookings_query);
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $bookings_result = $stmt->get_result();
    
    $bookings = [];
    while ($row = $bookings_result->fetch_assoc()) {
        $bookings[] = $row;
    }
    $tenant_data['bookings'] = $bookings;
$tenant_data['total_bookings'] = count($bookings);
    
    echo json_encode([
        'success' => true,
        'tenant' => $tenant_data,
        // 'bookings' => $bookings,
        // 'total_bookings' => count($bookings)
    ]);
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Missing tenant_id parameter'
    ]);
}
?>
