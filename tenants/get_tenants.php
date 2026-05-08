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

// -----------------------------------------
// JWT Authentication
// -----------------------------------------
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
        echo json_encode(["success" => false, "message" => "Invalid token"]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Token error: " . $e->getMessage()]);
    exit;
}


// -----------------------------------------
// API: GET TENANTS BY PROPERTY
// -----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['property_id'])) {

    $property_id = intval($_GET['property_id']);
    $current_date = date('Y-m-d');

    // Main query with updated booking model
    $query = "
        SELECT 
            B.booking_id,
            B.notice_given_date,
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
            R.capacity,
            R.occupied,

            CASE 
                WHEN R.capacity > R.occupied THEN 'Available'
                WHEN R.capacity = R.occupied THEN 'Occupied'
                ELSE 'Maintenance'
            END AS availability_status,

            B.start_date,
            B.end_date,
            B.rent_per_month,
            B.security_deposit,
            /* --------------- UNDER NOTICE --------------- */
            CASE 
                WHEN B.booking_status = 'Notice Given' THEN 1
                ELSE 0
            END AS under_notice,

            /* --------------- CURRENT RENT DUE --------------- */
            COALESCE((
                SELECT SUM(amount_due)
                FROM BOOKING_RENT_CYCLE 
                WHERE booking_id = B.booking_id 
                AND rent_status IN ('Pending' , 'Partially Paid')
            ), 0) AS rent_due,

            /* --------------- ACTIVE TICKET COUNT --------------- */
            (SELECT COUNT(*) 
             FROM TICKETS T 
             WHERE T.user_id = U.user_id 
             AND T.status != 'Closed'
            ) AS active_ticket

            /* --------------- HAS DUES --------------- */
            ,CASE 
                WHEN EXISTS (
                    SELECT 1 
                    FROM BOOKING_RENT_CYCLE BRC
                    WHERE BRC.booking_id = B.booking_id 
                    AND BRC.rent_status IN ('Pending' , 'Partially Paid')
                ) THEN 1
                ELSE 0
            END AS has_dues
        FROM BOOKINGS B
        INNER JOIN USERS U ON U.user_id = B.user_id
        INNER JOIN ROOM R ON R.room_id = B.room_id

        WHERE B.property_id = ?
          AND B.booking_status IN ('Active', 'Notice Given')
        
        GROUP BY B.booking_id
        ORDER BY B.start_date DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $property_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $tenants = [];
    while ($row = $result->fetch_assoc()) {
        
        
        
            // FIX: If start_date and end_date are the same, set end_date to next day
            if ($row['start_date'] === $row['end_date']) {
                $row['end_date'] = date('Y-m-d', strtotime($row['start_date'] . ' +1 day'));
            }



        // NOTICE END DATE CALCULATION
        $notice_end_date = null;

        if ($row['under_notice'] == 1 && !empty($row['start_date'])) {
            $start = $row['start_date'];
            $days = $row['notice_period_days'] ?? 0;

            if ($days > 0) {
                $notice_end_date = date('Y-m-d', strtotime("$start + $days days"));
            }
        }

        // For frontend compatibility
        $row['notice_end_date'] = $notice_end_date;

        $tenants[] = $row;
    }

    echo json_encode([
        'success' => true,
        'tenants' => $tenants
    ]);

    exit;
}


// -----------------------------------------
// INVALID REQUEST
// -----------------------------------------
echo json_encode([
    'success' => false,
    'message' => 'Missing or invalid property_id'
]);
exit;

?>
