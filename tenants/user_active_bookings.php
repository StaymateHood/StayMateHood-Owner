<?php
include '../dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    
    $user_id = intval($_POST['user_id']);
    $current_datetime = new DateTime();
    
$sql = "
    SELECT 
        B.booking_id,
        B.start_date,
        B.end_date,
        B.rent_per_month as amount,
        B.property_id,
        R.room_number,

        -- Total remaining hours
        GREATEST(TIMESTAMPDIFF(HOUR, NOW(), B.end_date), 0) as hours_left,

        -- Total remaining days (round down)
        GREATEST(FLOOR(TIMESTAMPDIFF(HOUR, NOW(), B.end_date) / 24), 0) as days_left,

        -- Extra hours after full days
        GREATEST(MOD(TIMESTAMPDIFF(HOUR, NOW(), B.end_date), 24), 0) as remaining_hours

    FROM BOOKINGS B
    LEFT JOIN ROOM R ON B.room_id = R.room_id
    WHERE B.user_id = ? 
      AND B.booking_status = 'Active'
            AND B.end_date > NOW()
    ORDER BY B.end_date ASC
";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    function getPropertyName($conn, $property_id) {
        $stmt = $conn->prepare("SELECT name FROM PROPERTY WHERE property_id = ? LIMIT 1");
        $stmt->bind_param('i', $property_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ? $row['name'] : 'Unknown Property';
    }
    
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
  $bookings[] = [
    "booking_id" => (int)$row['booking_id'],
    "room_number" => $row['room_number'],
    "amount" => (float)$row['amount'],
    "start_date" => $row['start_date'],
    "end_date" => $row['end_date'],
    "days_left" => (int)$row['days_left'],
    "remaining_hours" => (int)$row['remaining_hours'],
    "total_hours_left" => (int)$row['hours_left'],
    "property_name" => getPropertyName($conn, $row['property_id'])
];
    }
    
    echo json_encode([
        "success" => true,
        "active_bookings" => $bookings,
        // "total_count" => count($bookings)
    ]);
    
} else {
    echo json_encode([
        "success" => false,
        "message" => "user_id required"
    ]);
}

$conn->close();
?>
