<?php
header('Content-Type: application/json');
require_once '../dbconn.php';
include '../notifications/save_notification.php';

date_default_timezone_set('Asia/Kolkata');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
$notice_days = isset($_POST['notice_days']) ? intval($_POST['notice_days']) : 0;

if ($user_id <= 0 || $booking_id <= 0 || $notice_days <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid parameters']);
    exit;
}

try {
    // 🔹 Verify booking exists and belongs to user
    $sqlCheck = $conn->prepare("
        SELECT booking_id, booking_status 
        FROM BOOKINGS 
        WHERE booking_id = ? AND user_id = ? AND booking_status IN ('Active')
    ");
    $sqlCheck->bind_param("ii", $booking_id, $user_id);
    $sqlCheck->execute();
    $result = $sqlCheck->get_result();
    $booking = $result->fetch_assoc();
    $sqlCheck->close();

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'No active booking found for this user']);
        exit;
    }

    // 🔹 Check if already under notice
    if ($booking['booking_status'] === 'Notice Given') {
        echo json_encode(['success' => false, 'message' => 'Notice period already given for this booking']);
        exit;
    }

    // 🔹 Calculate vacating date
    $notice_given_date = date('Y-m-d');
    $vacating_date = date('Y-m-d', strtotime("+{$notice_days} days"));

    // 🔹 Update booking with notice info
    $update = $conn->prepare("
        UPDATE BOOKINGS 
        SET notice_period_days = ?, 
            notice_given_date = ?, 
            end_date = ?, 
            booking_status = 'Notice Given', 
            updated_at = NOW()
        WHERE booking_id = ? AND user_id = ?
    ");
    $update->bind_param("issii", $notice_days, $notice_given_date, $vacating_date, $booking_id, $user_id);
    $update->execute();

    if ($update->affected_rows > 0) {
        // 🔹 Push notification to tenant
        $title = "📢 Notice Period Submitted";
        $msg = "Your notice period of {$notice_days} days has been submitted successfully. 
                Your vacating date is set to " . date('d M, Y', strtotime($vacating_date)) . ".";

        pushAndSaveNotification(
            $conn,
            $user_id,
            'Notice',
            $title,
            $msg,
            ["booking_id" => $booking_id]
        );

        echo json_encode([
            'success' => true,
            'message' => 'Notice period submitted successfully',
            'data' => [
                'booking_id' => $booking_id,
                'user_id' => $user_id,
                'notice_days' => $notice_days,
                'notice_given_date' => $notice_given_date,
                'vacating_date' => $vacating_date,
                'status' => 'Notice Given'
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update notice period']);
    }

    $update->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
