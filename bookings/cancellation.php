<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");
include '../notifications/save_notification.php';

$booking_id = $_POST['booking_id'] ?? '';
$user_id    = $_POST['user_id'] ?? '';
$reason     = $_POST['reason'] ?? '';

if (!$booking_id || !$user_id) {
    echo json_encode([
        "status" => false,
        "message" => "booking_id and user_id are required"
    ]);
    exit;
}

try {
    // 1. Check booking exists and belongs to user
    $sql = "SELECT booking_id, booking_status , room_id
            FROM BOOKINGS 
            WHERE booking_id = ? AND user_id = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    if (!$booking) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid booking or unauthorized user"
        ]);
        exit;
    }

    // 2. Prevent cancel if already cancelled
    if ($booking["booking_status"] === "Cancelled") {
        echo json_encode([
            "status" => false,
            "message" => "Booking is already cancelled"
        ]);
        exit;
    }

    // 3. Update booking status
    $update = "UPDATE BOOKINGS SET booking_status = 'Cancelled' WHERE booking_id = ?";
    $stmt2 = $conn->prepare($update);
    $stmt2->bind_param("i", $booking_id);
    $stmt2->execute();

    // // 4. Insert cancellation log
    // $log = "INSERT INTO booking_cancellation_logs (booking_id, user_id, reason) 
    //         VALUES (?, ?, ?)";
    
    // $stmt3 = $conn->prepare($log);
    // $stmt3->bindValue(1, $booking_id);
    // $stmt3->bindValue(2, $user_id);
    // $stmt3->bindValue(3, $reason);
    // $stmt3->execute();


    $room_release = $conn->prepare("UPDATE ROOM SET occupied = occupied - 1 WHERE room_id = ?");
    $room_release->bind_param("i", $booking["room_id"]);
    $room_release->execute();


    echo json_encode([
        "status" => true,
        "message" => "Booking cancelled successfully"
    ]);

} catch(Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
