<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");
include '../dbconn.php';

require_once '../notifications/notify.php';  // notification helpers

    function pushAndSaveNotification($conn, $user_id, $user_type, $title, $msg, $data = []) {
        $notif_sql = "INSERT INTO NOTIFICATIONS 
            (user_id, title, message, created_at, is_read, notification_type)
            VALUES (?, ?, ?, NOW(), 0, ?)";
        $stmt = $conn->prepare($notif_sql);
        $stmt->bind_param("isss", $user_id, $title, $msg, $user_type);
        $stmt->execute();
        $notif_id = $conn->insert_id;
    
        // 2. Fetch device tokens for this user
        $tok_stmt = $conn->prepare("SELECT token FROM PUSH_TOKENS WHERE userid=?");
        $tok_stmt->bind_param("i", $user_id);
        $tok_stmt->execute();
        $res = $tok_stmt->get_result();
    
        $tokens = [];
        while ($row = $res->fetch_assoc()) {
            $tokens[] = $row['token'];
        }
    
        // 3. Send push notification for each token
        $responses = [];
        foreach ($tokens as $deviceToken) {
            $responses[] = sendPushNotification(
                $deviceToken,
                $title,
                $msg,
                array_merge($data, ["notif_id" => $notif_id])
            );
        }
    
        // 4. Return response
        return [
            "success" => true,
            "notification_id" => $notif_id,
            "sent_to" => count($tokens),
            "responses" => $responses
        ];
    }
    
$response = ["success" => false, "sent_count" => 0, "errors" => []];

try {
    $conn->begin_transaction();

    $reminder_date = date('Y-m-d', strtotime('+5 days'));

    $stmt = $conn->prepare("
        SELECT 
            b.booking_id,
            b.user_id,
            b.property_id,
            b.end_date,
            p.name AS property_name,
            u.name AS tenant_name
        FROM BOOKINGS b
        JOIN USERS u ON u.user_id = b.user_id
        LEFT JOIN PROPERTY p ON p.property_id = b.property_id
        WHERE b.booking_status != 'Pending'
          AND b.booking_type = 'month'
          AND DATE(b.end_date) = ?
    ");
    $stmt->bind_param("s", $reminder_date);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "success" => true,
            "message" => "No bookings found ending in 5 days.",
            "sent_count" => 0
        ]);
        exit;
    }

    $sent_count = 0;
    $errors = [];

    while ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
        $property_name = $row['property_name'];
        $booking_id = $row['booking_id'];
        $property_id = $row['property_id'];
        $due_date = $row['end_date'];
        $tenant_name = $row['tenant_name'];
        
        $getDetails = $conn->prepare("
        SELECT owner_id
        FROM PROPERTY 
        WHERE property_id = ?
        ");

        $getDetails->bind_param("i", $property_id);
        $getDetails->execute();
        $result = $getDetails->get_result();
        $data = $result->fetch_assoc();
        $owner_id = $data['owner_id'];
        

        $title = "⚠️ Rent Due Soon!";
        $msg = "Your rent for {{$property_name}} is due on {{$due_date}}.Please complete the payment to avoid late fees.";
        $title1 = "📢 Upcoming Rent Due";
        $msg1 = "Rent for {{$tenant_name}} at {{$property_name}} is due on {{$due_date}}.";
        try {
            $pushResponse = pushAndSaveNotification(
                $conn,
                $user_id,
                'Other',
                $title,
                $msg,
                ["booking_id" => $booking_id, "property_id" => $property_id]
            );
            
             $pushResponse = pushAndSaveNotification(
                $conn,
                $owner_id,
                'Other',
                $title1,
                $msg1,
                ["booking_id" => $booking_id, "property_id" => $property_id]
            );

            $sent_count++;
        } catch (Exception $e) {
            $errors[] = [
                "booking_id" => $booking_id,
                "error" => $e->getMessage()
            ];
        }
    }

    $conn->commit();

    $response = [
        "success" => true,
        "message" => "Booking reminders sent successfully.",
        "sent_count" => $sent_count,
        "errors" => $errors
    ];

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = "Failed: " . $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
