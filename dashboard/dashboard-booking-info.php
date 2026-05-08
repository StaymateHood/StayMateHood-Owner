<?php
header("Content-Type: application/json");
include '../notifications/save_notification.php'; // JWT decode + $conn + $user_id

$response = ["success" => false];

try {
    if (empty($user_id)) {
        throw new Exception("Unauthorized user.");
    }

    $sql = "SELECT 
                B.booking_id,
                B.start_date,
                B.end_date,
                B.total_amount,
                R.room_number
            FROM BOOKINGS B
            LEFT JOIN ROOM R ON B.room_id = R.room_id
            WHERE B.user_id = ?
              AND B.booking_status = 'Approved'
            ORDER BY B.start_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $end_date = new DateTime($row['end_date']);
        $today = new DateTime();
        $days_left = $today->diff($end_date)->days;
        if ($today > $end_date) $days_left = 0;

        $bookings[] = [
            'booking_number' => $row['booking_id'],
            'room_number' => $row['room_number'],
            'days_left' => $days_left,
            'amount' => $row['total_amount'],
            'date' => $row['start_date']
        ];
    }

    if (!empty($bookings)) {
        $response["success"] = true;
        $response["message"] = "Approved dashboard bookings fetched successfully.";
        $response["data"] = $bookings;
    } else {
        $response["success"] = true;
        $response["message"] = "No approved bookings found.";
        $response["data"] = [];
    }

} catch (Exception $e) {
    $response["success"] = false;
    $response["message"] = $e->getMessage();
}

echo json_encode($response);
$conn->close();
