<?php
header("Content-Type: application/json");
include '../dbconn.php';
include '../token.php';

$response = ["success" => false];

try {
    if (empty($user_id)) {
        throw new Exception("Unauthorized user.");
    }

    $room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : null;
    
    if (!$room_id) {
        throw new Exception("room_id is required.");
    }

    // Fetch room details
    $stmt = $conn->prepare("
        SELECT 
            r.room_id,
            r.room_number,
            r.property_id,
            r.room_type,
            r.rent_per_room,
            p.name AS property_name,
            p.address,
            p.city
        FROM ROOM r
        JOIN PROPERTY p ON r.property_id = p.property_id
        WHERE r.room_id = ?
    ");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Room not found.");
    }
    
    $room = $result->fetch_assoc();
    
    // Generate QR data
    $qr_data = json_encode([
        'room_id' => $room['room_id'],
        'room_number' => $room['room_number'],
        'property_name' => $room['property_name'],
        'address' => $room['address'],
        'city' => $room['city'],
        'timestamp' => time()
    ]);
    
    // Generate QR code URL
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_data);
    
    $response["success"] = true;
    $response["message"] = "Room QR code generated successfully.";
    $response["data"] = [
        'qr_code_url' => $qr_url,
        // 'qr_data' => $qr_data,
        'room_id' => $room['room_id'],
        'room_number' => $room['room_number']
    ];

} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>
