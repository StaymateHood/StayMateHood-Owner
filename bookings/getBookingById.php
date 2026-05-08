<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
include '../dbconn.php';
include '../notifications/save_notification.php';
require_once '../vendor/autoload.php';
require '../env.php';

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

// Handle GET Request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $booking_id = $_GET['booking_id'] ?? null;
    if (!$booking_id) {
        echo json_encode(["success" => false, "message" => "Booking ID is required"]);
        exit;
    }
    

    // 1. Fetch main booking details
    $sql = "SELECT 
                B.booking_id,
                B.user_id,
                B.property_id,
                B.room_id,
                B.start_date,
                B.end_date,
                B.booking_type,
                B.booking_status,
                B.total_amount,
                B.security_deposit,
                P.name AS property_name,
                P.address,
                P.property_type,
                P.Maintenance_amount,
                P.notice_period,
                P.day,
                P.meal_type,
                P.meal_name,
                R.room_number
            FROM BOOKINGS B
            JOIN PROPERTY P ON B.property_id = P.property_id
            LEFT JOIN ROOM R ON B.room_id = R.room_id
            WHERE B.user_id = ? AND B.booking_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result->num_rows) {
        echo json_encode(["success" => false, "message" => "Booking not found"]);
        exit;
    }

    $booking = $result->fetch_assoc();

    // Auto-complete expired booking
    $today = date('Y-m-d');
    if ($booking['booking_status'] === 'Active' && !empty($booking['end_date']) && $booking['end_date'] < $today) {
        $upd = $conn->prepare("UPDATE BOOKINGS SET booking_status = 'Completed' WHERE booking_id = ?");
        $upd->bind_param("i", $booking_id);
        $upd->execute();
        $upd->close();
        $booking['booking_status'] = 'Completed';
    }

    // 2. Fetch Rent Cycles from BOOKING_RENT_CYCLE
    $cycle_sql = "SELECT 
                    cycle_id,
                    start_date,
                    end_date,
                    room_rent,
                    security_amount,
                    charges,
                    amount_due,
                    total_amount,
                    rent_status,
                    created_at,
                    updated_at
                  FROM BOOKING_RENT_CYCLE
                  WHERE booking_id = ?
                  ORDER BY start_date ASC";
    $cycle_stmt = $conn->prepare($cycle_sql);
    $cycle_stmt->bind_param("i", $booking_id);
    $cycle_stmt->execute();
    $cycle_result = $cycle_stmt->get_result();

    $rent_cycles = [];
    $total_due = 0.0;
    $total_paid = 0.0;

    while ($cycle = $cycle_result->fetch_assoc()) {
        $cycle_id = (int)$cycle['cycle_id'];
        $total_amount = (float)$cycle['total_amount'];

        $pay_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS paid FROM PAYMENTS WHERE cycle_id = ? AND payment_status = 'Completed'");
        $pay_stmt->bind_param("i", $cycle_id);
        $pay_stmt->execute();
        $pay_row = $pay_stmt->get_result()->fetch_assoc();
        $amount_paid = (float)($pay_row['paid'] ?? 0.0);

        $remaining = round(max(0, $total_amount - $amount_paid), 2);

        if ($remaining > 0 && $amount_paid == 0) {
            $cycle['rent_status'] = 'Pending';
        } elseif ($remaining > 0 && $amount_paid > 0) {
            $cycle['rent_status'] = 'Partially Paid';
        } elseif ($remaining == 0) {
            $cycle['rent_status'] = 'Paid';
        }

        $total_paid += $amount_paid;
        $total_due += $remaining;
        $cycle['amount_paid'] = $amount_paid;
        $cycle['remaining_due'] = $remaining;
        $rent_cycles[] = $cycle;
    }

    // 3. Fetch payment history
    $pay_stmt = $conn->prepare("SELECT payment_id, cycle_id, amount, payment_date, payment_method, payment_status FROM PAYMENTS WHERE booking_id = ? AND payment_status = 'Completed' ORDER BY payment_date DESC");
    $pay_stmt->bind_param("i", $booking_id);
    $pay_stmt->execute();
    $pay_res = $pay_stmt->get_result();

    $payment_history = [];
    while ($pay = $pay_res->fetch_assoc()) {
        $payment_history[] = $pay;
    }

    // 4. Summary
    $booking['total_due_amount'] = round($total_due, 2);
    $booking['total_paid_amount'] = round($total_paid, 2);
    $booking['can_pay'] = ($total_due > 0 && in_array($booking['booking_status'], ['Active', 'Notice Given', 'Approved']));
    $booking['rent_cycles'] = $rent_cycles;
    $booking['payment_history'] = $payment_history;

    // 5. Roommates (other active users in same room)
    $roommates = [];
    if (!empty($booking['room_id'])) {
        $rm_stmt = $conn->prepare("SELECT U.user_id, U.name, U.email, U.phone, U.profile_image FROM BOOKINGS B JOIN USERS U ON B.user_id = U.user_id WHERE B.room_id = ? AND B.booking_status = 'Active' AND B.user_id != ?");
        $rm_stmt->bind_param("ii", $booking['room_id'], $user_id);
        $rm_stmt->execute();
        $rm_res = $rm_stmt->get_result();
        while ($rm = $rm_res->fetch_assoc()) $roommates[] = $rm;
        $rm_stmt->close();
    }
    $booking['roommates'] = $roommates;

    // 6. Food menu
    $food_menu = [];
    if (!empty($booking['day']) && !empty($booking['meal_type']) && !empty($booking['meal_name'])) {
        $days = explode(",", $booking['day']);
        $meal_types = explode(",", $booking['meal_type']);
        $meal_names = explode(",", $booking['meal_name']);
        $index = 0;
        foreach ($days as $day) {
            foreach ($meal_types as $meal_type) {
                if (isset($meal_names[$index])) {
                    $food_menu[] = [
                        'day'       => trim($day),
                        'meal_type' => trim($meal_type),
                        'meal_name' => trim($meal_names[$index])
                    ];
                }
                $index++;
            }
        }
    }
    unset($booking['day'], $booking['meal_type'], $booking['meal_name']);
    $booking['food_menu'] = $food_menu;

    // 7. Service requests
    $sr_stmt = $conn->prepare("SELECT * FROM SERVICE_REQUEST WHERE user_id = ?  ORDER BY created_at DESC");
    $sr_stmt->bind_param("i", $user_id);
    $sr_stmt->execute();
    $sr_res = $sr_stmt->get_result();
    $service_requests = [];
    while ($sr = $sr_res->fetch_assoc()) $service_requests[] = $sr;
    $sr_stmt->close();
    $booking['service_requests'] = $service_requests;

    // Final response
    $response = [
        "success" => true,
        "data" => $booking
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
