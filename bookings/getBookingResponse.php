<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");
include '../notifications/save_notification.php';

$booking_id = $_POST['booking_id'] ?? null;
$is_accepted = isset($_POST['is_accepted']) ? filter_var($_POST['is_accepted'], FILTER_VALIDATE_BOOLEAN) : null;
$room_id = $_POST['room_id'] ?? null;

if (!$booking_id || !isset($is_accepted)) {
    echo json_encode(["success" => false, "message" => "booking_id and is_accepted are required"]);
    exit;
}

if ($is_accepted) {
    // ✅ Approve booking
    $sql = "UPDATE BOOKINGS 
            SET booking_status = 'Active', room_id = ? 
            WHERE booking_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $room_id, $booking_id);

    if (!$stmt->execute()) {
        echo json_encode(["success" => false, "message" => "Failed to approve booking: " . $stmt->error]);
        exit;
    }

    if ($stmt->affected_rows > 0) {
        // ✅ Update room occupancy
        $rstmt = $conn->prepare("UPDATE ROOM SET occupied = occupied + 1 WHERE room_id = ?");
        $rstmt->bind_param("i", $room_id);
        if (!$rstmt->execute()) {
            echo json_encode(["success" => false, "message" => "Failed to update room occupancy: " . $rstmt->error]);
            exit;
        }

        // ✅ Fetch booking details for rent generation
        $stmt = $conn->prepare("
            SELECT 
                B.user_id, B.start_date, B.end_date, B.property_id, B.booking_type,
                R.room_number, P.name AS property_name, B.security_deposit , rent_per_month ,
                U.name AS tenant_name
            FROM BOOKINGS B
            JOIN ROOM R ON B.room_id = R.room_id
            JOIN PROPERTY P ON R.property_id = P.property_id
            JOIN USERS U ON B.user_id = U.user_id
            WHERE B.booking_id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        if ($data) {
            $user_id = $data['user_id'];
            $tenant_name = $data['tenant_name'];
            $property_name = $data['property_name'];
            $room_number = $data['room_number'];
            $start_date = $data['start_date'];
            $end_date = $data['end_date'];
            $property_id = $data['property_id'];
            $booking_type = $data['booking_type'];
            $security_deposit = $data['security_deposit'];
            $rent_per_month = $data['rent_per_month'];
            $total_due = $rent_per_month + $security_deposit;

            $startDate = new DateTime($start_date);
            $endDate = null;
            $due_date =null;

            // ✅ Decide cycle end date based on booking type
            if (strtolower($booking_type) === 'day') {
                $due_date = clone $startDate;
                $due_date->modify('+3 days');
                // Calculate difference between start_date and end_date
                if (!empty($end_date)) {
                $endDate = new DateTime($end_date);
                } else {
                    echo json_encode(["success" => false, "message" => "End date required for day-wise booking."]);
                    exit;
                }
            } else {
                // Default Monthly Booking – 30-day cycle
                // $endDate = clone $startDate;
                // $endDate->modify('+30 days');
                // $due_date = clone $startDate;
                // $due_date->modify('+3 days');
                $endDate = clone $startDate;
                $endDate->modify('+1 days');
                $due_date = clone $startDate;
                $due_date->modify('+1 days');
            }

            // ✅ Insert rent cycle
            $insertRent = $conn->prepare("
                INSERT INTO BOOKING_RENT_CYCLE 
                (booking_id, user_id, room_id, start_date, end_date, next_due_date, rent_status , room_rent , security_amount , amount_due , total_amount)
                VALUES (?, ?, ?, ?, ? , ?, 'Pending' , ?, ?, ? , ?)
            ");
            $sDate = $startDate->format('Y-m-d');
            $eDate = $endDate->format('Y-m-d');
            $due_date = $due_date->format('Y-m-d');
            $insertRent->bind_param("iiisssiiii", $booking_id, $user_id, $room_id, $sDate, $eDate , $due_date, $rent_per_month , $security_deposit , $total_due , $total_due);
            $insertRent->execute();

            // ✅ Notification
            $title = "🏠 Booking Confirmed!";
            $msg = "You confirmed {$tenant_name}’s booking at {$property_name}, Room {$room_number}. Reserved from {$sDate} to {$eDate}.";

            $pushData = [
                "booking_id" => $booking_id,
                "property_id" => $property_id,
                "property_name" => $property_name,
                "room_number" => $room_number
            ];

            pushAndSaveNotification(
                $conn,
                $user_id,
                'Other',
                $title,
                $msg,
                $pushData
            );

            echo json_encode([
                "success" => true,
                "message" => "Booking approved, rent cycle generated ({$booking_type}), and user notified."
            ]);
        } else {
            echo json_encode(["success" => true, "message" => "Booking approved, but no booking details found."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Booking approval failed or already approved."]);
    }

} else {
    // ❌ Cancel Booking
    $sql = "UPDATE BOOKINGS SET booking_status = 'Cancelled' WHERE booking_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $booking_id);

    if (!$stmt->execute()) {
        echo json_encode(["success" => false, "message" => "Failed to cancel booking: " . $stmt->error]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT P.name, B.user_id, B.property_id 
        FROM BOOKINGS B
        LEFT JOIN PROPERTY P ON B.property_id = P.property_id
        WHERE B.booking_id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data) {
        $property_name = $data['name'];
        $property_id = $data['property_id'];
        $user_id = $data['user_id'];

        $title = "❌ Booking Cancelled";
        $msg = "Your booking at {$property_name}, has been cancelled.";

        $pushData = [
            "booking_id" => $booking_id,
            "property_id" => $property_id,
            "property_name" => $property_name,
        ];

        pushAndSaveNotification(
            $conn,
            $user_id,
            'Other',
            $title,
            $msg,
            $pushData
        );
    }

    echo json_encode(["success" => true, "message" => "Booking cancelled and user notified."]);
}
?>
