<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
include '../notifications/save_notification.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$property_id = intval($_POST['property_id'] ?? 0);
$scheduled_time = $_POST['scheduled_time'] ?? '';
$notes = $_POST['notes'] ?? '';

if (!$property_id || !$scheduled_time) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$check = $conn->prepare("SELECT 1 FROM VISITS v
    JOIN PROPERTY p ON v.property_id = p.property_id 
    WHERE v.scheduled_time = ? AND p.property_id = ?");
$check->bind_param("si", $scheduled_time, $property_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Owner already has a visit scheduled at this time']);
    exit;
}

$visitStmt = $conn->prepare("INSERT INTO VISITS (user_id, property_id, scheduled_time, visit_status, notes, created_at, updated_at)
    VALUES (?, ?, ?, 'Scheduled', ?, NOW(), NOW())");
$visitStmt->bind_param("iiss", $user_id, $property_id, $scheduled_time, $notes);
if (!$visitStmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Could not schedule visit']);
    exit;
}

$ownerStmt = $conn->prepare("SELECT p.owner_id, p.name AS property_name, r.room_number 
    FROM PROPERTY p 
    LEFT JOIN ROOM r ON p.property_id = r.property_id 
    WHERE p.property_id = ? 
    LIMIT 1");
$ownerStmt->bind_param("i", $property_id);
$ownerStmt->execute();
$ownerResult = $ownerStmt->get_result();
$owner = $ownerResult->fetch_assoc();

$owner_id = $owner['owner_id'] ?? null;
$property_name = $owner['property_name'] ?? 'Unknown Property';
$room_number = $owner['room_number'] ?? 'N/A';

// --- Send Notification ---
if ($owner_id) {
    //To Owner
    $title = "🧍‍♂️ New Visit Request!";
    $msg = "A tenant has shown interest in your property *{$property_name}*.
    📅 Visit Date: {$scheduled_time}
    ";
    
    $pushData = [
        "user_id" => $user_id,
        "property_id" => $property_id,
        "property_name" => $property_name,
        "room_number" => $room_number,
        "scheduled_time" => $scheduled_time
    ];
    
    $pushResponse = pushAndSaveNotification(
        $conn,
        $owner_id,
        'Visits',
        $title,
        $msg
        // $pushData
    );
    
    // To Tenant
    
    $title = "✅ Visit Request Sent!";
    $msg = "Your visit request for *{$property_name}* has been sent to the owner.You’ll get a confirmation soon.";
    
    $pushData = [
        "user_id" => $user_id,
        "property_id" => $property_id,
        "property_name" => $property_name,
        "room_number" => $room_number,
        "scheduled_time" => $scheduled_time
    ];
    
    $pushResponse = pushAndSaveNotification(
        $conn,
        $user_id,
        'Visits',
        $title,
        $msg
        // $pushData
    );

    echo json_encode(['success' => true, 'message' => 'Visit scheduled and owner notified']);
} else {
    echo json_encode(['success' => true, 'message' => 'Visit scheduled (owner not found for notification)']);
}
?>
