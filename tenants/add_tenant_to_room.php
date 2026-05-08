<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../notifications/save_notification.php';  // notification helpers

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = $_POST['booking_id'] ?? '';
    $room_id = $_POST['room_id'] ?? '';
    $created_at = date('Y-m-d H:i:s');

    // Validate required fields
    if (empty($booking_id) || empty($room_id)) {
        echo json_encode(["status" => "error", "message" => "Missing booking_id or room_id"]);
        exit;
    }

    // 1. Increment occupied in ROOM
    $stmt = $conn->prepare("UPDATE ROOM SET occupied = occupied + 1, updated_at = ? WHERE room_id = ?");
    $stmt->bind_param("si", $created_at, $room_id);
    if (!$stmt->execute()) {
        echo json_encode(["status" => "error", "message" => "Failed to update room occupancy: " . $stmt->error]);
        exit;
    }
    if ($stmt->affected_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Room occupancy not updated. Check if room_id exists and is correct."]);
        exit;
    }

    // 2. Assign room_id and approve booking in BOOKINGS
    $stmt = $conn->prepare("UPDATE BOOKINGS SET room_id = ?, booking_status = 'Approved', updated_at = ? WHERE booking_id = ?");
    $stmt->bind_param("isi", $room_id, $created_at, $booking_id);
    if (!$stmt->execute()) {
        echo json_encode(["status" => "error", "message" => "Failed to update booking: " . $stmt->error]);
        exit;
    }
    if ($stmt->affected_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Booking not updated. Check if booking_id exists and is correct."]);
        exit;
    }
    
    // Push notification to tenant
        $title = "Booking Request Approved!!";
        $msg = "Your Booking Request has been Approved by PG Owner";
        
        $pushResponse = pushAndSaveNotification(
            $conn,
            $user_id,
            'Other',   
            $title,
            $msg,
            ["booking_id"=>$booking_id]
        );

    echo json_encode([
        "status" => "success",
        "message" => "Room assigned and booking approved",
        "booking_id" => $booking_id
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}
?>