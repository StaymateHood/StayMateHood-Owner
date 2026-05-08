<?php
header("Content-Type: application/json");
include '../dbconn.php';

$property_id = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
if ($property_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid property_id"]);
    exit;
}

// Join BOOKINGS, PAYMENTS, USERS, ROOM to get rent_per_month, user name, payment_date, transaction_id, and room_number
$sql = "SELECT B.rent_per_month, U.name, P.payment_date, P.transaction_id, R.room_number, P.amount
        FROM BOOKINGS B
        JOIN PAYMENTS P ON B.booking_id = P.booking_id
        JOIN USERS U ON P.user_id = U.user_id
        JOIN ROOM R ON B.room_id = R.room_id
        WHERE B.property_id = ? AND P.payment_status = 'Completed'
        ORDER BY P.payment_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();

$details = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $details[] = [
            'amount' => (float)$row['amount'],
            'rent_per_month' => (float)$row['rent_per_month'],
            'user_name' => $row['name'],
            'payment_date' => $row['payment_date'],
            'transaction_id' => $row['transaction_id'],
            'room_number' => $row['room_number']
        ];
    }
}

echo json_encode([
    "success" => true,
    "details" => $details,
]);
?>