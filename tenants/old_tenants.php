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
    $current_date = date('Y-m-d');

    /* ---------------------------------------------------------
       FETCH PAST TENANTS (Booking ended already)
       booking_status = Pending Clearance OR Completed
       end_date < today
    --------------------------------------------------------- */
    $query = "
        SELECT 
            B.booking_id,
            U.user_id,
            U.name,
            U.email,
            U.phone,
            U.user_type,
            U.relationship,
            U.food_preference,
            U.occupation,
            U.profile_image,
            U.aadhaar_card,
            U.pan_card,
            U.created_at AS joined_date,
            U.emergency_contact,
            U.emergency_contact_name,
            U.emergency_contact_relation,

            R.room_number,
            R.room_type,
            R.attached_bath,
            R.rent_per_bed,
            R.rent_per_room,
            R.capacity,
            R.occupied,

            CASE 
                WHEN R.capacity > R.occupied THEN 'Available'
                WHEN R.capacity = R.occupied THEN 'Occupied'
                ELSE 'Maintenance'
            END AS availability_status,

            0 AS active_ticket,
            0 AS under_notice,

            B.start_date,
            B.end_date,
            B.rent_per_month,

            0 AS rent_due
        FROM BOOKINGS B
        LEFT JOIN USERS U ON B.user_id = U.user_id
        LEFT JOIN ROOM R ON B.room_id = R.room_id

        WHERE 
            B.property_id = ?
            AND U.user_type = 'tenant'
            AND B.booking_status IN ('Pending Clearance', 'Completed')
            AND B.end_date IS NOT NULL
            AND B.end_date < ?
        
        ORDER BY B.end_date DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $property_id, $current_date);
    $stmt->execute();
    $result = $stmt->get_result();

    $tenants = [];

    while ($row = $result->fetch_assoc()) {

        /* ---------------------------------------------------------
           CALCULATE RENT DUE FROM BOOKING_RENT_CYCLE (NEW SYSTEM)
        --------------------------------------------------------- */
        $due_sql = "
            SELECT SUM(amount_due - paid_amount) AS total_due
            FROM BOOKING_RENT_CYCLE
            WHERE booking_id = ? AND rent_status IN ('Pending', 'Partially Paid')
        ";
        $due_stmt = $conn->prepare($due_sql);
        $due_stmt->bind_param("i", $row['booking_id']);
        $due_stmt->execute();
        $due_stmt->bind_result($total_due);
        $due_stmt->fetch();
        $due_stmt->close();

        $row['rent_due'] = floatval($total_due ?? 0);

        $tenants[] = $row;
    }

    echo json_encode([
        'success' => true,
        'tenants' => $tenants
    ]);
    $stmt->close();
    exit;
}


// Invalid request
echo json_encode([
    'success' => false,
    'message' => 'Missing or invalid property_id'
]);

?>
