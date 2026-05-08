<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");
include '../notifications/save_notification.php';

$response = ["success" => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit;
}

// ✅ Required fields validation
$required_fields = ['property_id', 'room_type', 'start_date', 'total_amount', 'security_deposit'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(["success" => false, "message" => "Missing field: $field"]);
        exit;
    }
}

$property_id      = intval($_POST['property_id']);
$room_type        = trim($_POST['room_type']);
$start_date       = trim($_POST['start_date']);
$end_date         = isset($_POST['end_date']) && $_POST['end_date'] !== '' ? trim($_POST['end_date']) : null;
$total_amount     = floatval($_POST['total_amount']);
$booking_type     = isset($_POST['booking_type']) ? trim($_POST['booking_type']) : 'month';
//$end_date = $booking_type === 'month' ? null : $end_date;
$security_deposit = ($booking_type === 'day') ? 0.00 : floatval($_POST['security_deposit']);
$rent_per_month   = max(0, $total_amount - $security_deposit);
$payment_status   = 'Pending';

// ✅ Date format check
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !strtotime($start_date)) {
    echo json_encode(["success" => false, "message" => "Invalid start_date format. Use YYYY-MM-DD."]);
    exit;
}

// ✅ DB & user validation
if (!isset($conn) || !$conn instanceof mysqli) {
    echo json_encode(["success" => false, "message" => "Database connection not found."]);
    exit;
}

if (!isset($user_id) || empty($user_id)) {
    echo json_encode(["success" => false, "message" => "User not authenticated."]);
    exit;
}

$conn->begin_transaction();

try {

    $checkExistingBookingSql = "SELECT COUNT(*) AS count FROM BOOKINGS 
        WHERE user_id = ? AND property_id = ? AND booking_status IN ('Pending Approval', 'Approved' , 'Active' , 'Pending Clearance')
        and (end_date IS NULL OR end_date >= CURDATE())";
    $stmt = $conn->prepare($checkExistingBookingSql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param("ii", $user_id, $property_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row['count'] > 0) {
        throw new Exception("You already have an active or pending booking for this property.");
    }

    // 🔹 1. Insert booking request (room not assigned yet)
    $insertBookingSql = "INSERT INTO BOOKINGS 
        (user_id, property_id, booking_type, start_date, end_date, booking_status, total_amount, rent_per_month, security_deposit, created_at, updated_at, room_type)
        VALUES (?, ?, ?, ?, ?, 'Pending Approval', ?, ?, ?, NOW(), NOW(), ?)";
    $stmt = $conn->prepare($insertBookingSql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    $stmt->bind_param(
        "iisssddds",
        $user_id,
        $property_id,
        $booking_type,
        $start_date,
        $end_date,
        $total_amount,
        $rent_per_month,
        $security_deposit,
        $room_type
    );
    $stmt->execute();
    if ($stmt->errno) throw new Exception("Booking insert failed: " . $stmt->error);

    $booking_id = $conn->insert_id;

    // 🔹 2. Fetch tenant & property info for notifications
    $tenant_name = 'Tenant';
    $userStmt = $conn->prepare("SELECT name FROM USERS WHERE user_id = ?");
    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $tenantRow = $userStmt->get_result()->fetch_assoc();
    if ($tenantRow) $tenant_name = $tenantRow['name'];

    $owner_id = null;
    $property_name = 'Property';
    $ownerStmt = $conn->prepare("SELECT owner_id, name FROM PROPERTY WHERE property_id = ?");
    $ownerStmt->bind_param("i", $property_id);
    $ownerStmt->execute();
    $ownerRow = $ownerStmt->get_result()->fetch_assoc();
    if ($ownerRow) {
        $owner_id = $ownerRow['owner_id'];
        $property_name = $ownerRow['name'];
    }

    // 🔹 3. Push notifications
    if ($owner_id) {
        pushAndSaveNotification(
            $conn,
            $owner_id,
            'Other',
            "🎉 New Booking Request!",
            "{$tenant_name} has requested a booking at {$property_name} on {$start_date}. Please review and approve.",
            ["booking_id" => $booking_id, "property_id" => $property_id]
        );
    }

    pushAndSaveNotification(
        $conn,
        $user_id,
        'Other',
        "✅ Booking Request Submitted!",
        "Your booking request at {$property_name} has been submitted successfully. Await owner approval.",
        ["booking_id" => $booking_id, "property_id" => $property_id]
    );

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Booking request submitted successfully.",
        "booking_id" => $booking_id
    ]);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Book Room Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Booking failed: " . $e->getMessage()]);
    exit;
}
?>
