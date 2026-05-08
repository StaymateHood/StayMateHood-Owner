<?php
/**
 * CRON JOB: Generate next month's rent cycles automatically
 * - Runs daily (recommended: 10 0 * * *)
 * - Handles notice periods, due rent, and room release logic
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kolkata');

include '../dbconn.php';
// include '../notifications/save_notification.php';

// function pushAndSaveNotification($conn, $user_id, $user_type, $title, $msg, $data = []) {
//         $notif_sql = "INSERT INTO NOTIFICATIONS 
//             (user_id, title, message, created_at, is_read, notification_type)
//             VALUES (?, ?, ?, NOW(), 0, ?)";
//         $stmt = $conn->prepare($notif_sql);
//         $stmt->bind_param("isss", $user_id, $title, $msg, $user_type);
//         $stmt->execute();
//         $notif_id = $conn->insert_id;
    
//         // 2. Fetch device tokens for this user
//         $tok_stmt = $conn->prepare("SELECT token FROM PUSH_TOKENS WHERE userid=?");
//         $tok_stmt->bind_param("i", $user_id);
//         $tok_stmt->execute();
//         $res = $tok_stmt->get_result();
    
//         $tokens = [];
//         while ($row = $res->fetch_assoc()) {
//             $tokens[] = $row['token'];
//         }
    
//         // 3. Send push notification for each token
//         $responses = [];
//         foreach ($tokens as $deviceToken) {
//             $responses[] = sendPushNotification(
//                 $deviceToken,
//                 $title,
//                 $msg,
//                 array_merge($data, ["notif_id" => $notif_id])
//             );
//         }
    
//         // 4. Return response
//         return [
//             "success" => true,
//             "notification_id" => $notif_id,
//             "sent_to" => count($tokens),
//             "responses" => $responses
//         ];
// }


$today = date('Y-m-d');

echo "==============================\n";
echo "🏠 Rent Cycle Cron Started: $today\n";
echo "==============================\n";

$conn->query("INSERT INTO CRON_LOG (cron_name, run_time) VALUES ('rent_cycle_cron', NOW())");


// $sql = "
//     SELECT 
//         b.booking_id,
//         b.user_id,
//         b.property_id,
//         b.room_id,
//         b.booking_status,
//         b.notice_period_days,
//         b.notice_given_date,
//         b.start_date,
//         b.rent_per_month,
//         r.room_number
//     FROM BOOKINGS b
//     INNER JOIN ROOM r ON b.room_id = r.room_id
//     WHERE b.booking_status IN ('Active', 'Notice Given', 'Pending Clearance') 
//       AND b.booking_type = 'month'
// ";

$sql = "
    SELECT 
        b.booking_id,
        b.user_id,
        b.property_id,
        b.room_id,
        b.booking_type,
        b.booking_status,
        b.notice_period_days,
        b.notice_given_date,
        b.start_date,
        b.rent_per_month,
        r.room_number
    FROM BOOKINGS b
    INNER JOIN ROOM r ON b.room_id = r.room_id
     WHERE b.booking_type IN ('day', 'month')
          And b.booking_status IN ('Active', 'Notice Given', 'Pending Clearance')";

$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    echo "⚠️ No active/notice bookings found.\n";
    exit;
}

$created_count = 0;
$skipped_count = 0;
$completed_count = 0;

while ($b = $result->fetch_assoc()) {
    $booking_id = $b['booking_id'];
    $user_id = $b['user_id'];
    $status = $b['booking_status'];
    $rent = (float)$b['rent_per_month'];
    $start_date = $b['start_date'];
    $notice_period_days = (int)$b['notice_period_days'];
    $notice_given_date = $b['notice_given_date'] ?? null;
    $user_id = $b['user_id'];
    $room_id = $b['room_id'];
        $booking_type = $b['booking_type'];


    // STEP 1️⃣: Get last rent cycle
    $stmt = $conn->prepare("
        SELECT start_date, end_date, rent_status 
        FROM BOOKING_RENT_CYCLE 
        WHERE booking_id = ? 
        ORDER BY end_date DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $last_cycle = $res->fetch_assoc();
        $last_end = $last_cycle['end_date'];
        $last_status = $last_cycle['rent_status'];
        
            //if booking type is day and that brc rent_status is paid and last_end is less than today then skip the cycle generation
        if($booking_type == 'day' && $last_status == 'Paid' && $last_end < $today){
            echo "⏩ Skipping Booking $booking_id: day wise booking already paid and end date passed\n";
            $skipped_count++;
            continue;
        }

        // Skip if current cycle still active
        if ($today < $last_end) {
            echo "⏩ Skipping Booking $booking_id: active till $last_end\n";
            $skipped_count++;
            continue;
        }
    } else {
        $last_end = date('Y-m-d', strtotime($start_date . ' -1 day'));
    }

    // STEP 2️⃣: Handle notice period
    $notice_end = null;
    if ($status === 'Notice Given' && $notice_period_days > 0) {
        $notice_start = $notice_given_date ?? $today;
        $notice_end = date('Y-m-d', strtotime("$notice_start +{$notice_period_days} days"));

        // If notice period has ended
        if ($today >= $notice_end) {

            // Check dues before marking completed
            $dueCheck = $conn->prepare("
                SELECT SUM(total_amount - paid_amount) AS pending_amount 
                FROM BOOKING_RENT_CYCLE 
                WHERE booking_id = ? AND rent_status IN ('Pending','Partially Paid')
            ");
            $dueCheck->bind_param("i", $booking_id);
            $dueCheck->execute();
            $pending_amount = (float)($dueCheck->get_result()->fetch_assoc()['pending_amount'] ?? 0);

            if ($pending_amount > 0) {
                // Set booking to Pending Clearance
                $status_update = $conn->prepare("
                    UPDATE BOOKINGS 
                    SET booking_status = 'Pending Clearance', updated_at = NOW() 
                    WHERE booking_id = ?
                ");
                $status_update->bind_param("i", $booking_id);
                $status_update->execute();

                $title = "⚠️ Rent Pending After Notice";
                $msg = "Your booking has reached the end of the notice period, but ₹" . number_format($pending_amount, 2) . " is still due. Please clear it soon.";
                // pushAndSaveNotification($conn, $user_id, 'Rent', $title, $msg, ["booking_id" => $booking_id]);
                echo "⚠️ Booking $booking_id: notice ended, dues pending ₹$pending_amount\n";
            } else {
                // Mark as Completed & release room
                $status_update = $conn->prepare("
                    UPDATE BOOKINGS 
                    SET booking_status = 'Completed', updated_at = NOW() 
                    WHERE booking_id = ?
                ");
                $status_update->bind_param("i", $booking_id);
                $status_update->execute();

                $room_release = $conn->prepare("UPDATE ROOM SET occupied = occupied - 1 WHERE room_id = ?");
                $room_release->bind_param("i", $b['room_id']);
                $room_release->execute();

                $title = "🏡 Booking Completed";
                $msg = "Your stay has officially ended. Thank you for staying with us!";
                // pushAndSaveNotification($conn, $user_id, 'Rent', $title, $msg, ["booking_id" => $booking_id]);
                echo "✅ Booking $booking_id completed and room released.\n";
                $completed_count++;
            }
            continue; // Skip cycle generation for completed ones
        }
    }

    // STEP 3️⃣: Create next rent cycle if still active
    // $next_start = date('Y-m-d', strtotime($last_end . ' +1 day'));
    // $next_end = date('Y-m-d', strtotime($next_start . ' +29 days'));
    // $next_due = date('Y-m-d', strtotime($next_end . ' +3 days'));
    // $next_end = date('Y-m-d', strtotime($next_start . ' +1 days'));
    // $next_end   = $next_start;
    // $next_start = $today;  
    // $next_end   = $today;
    // $next_due = date('Y-m-d', strtotime($next_end . ' +1 days'));
       $next_start = $today;
        // $next_end   = date('Y-m-d', strtotime($next_start . ' +1 day'));
        // $next_due   = date('Y-m-d', strtotime($next_end . ' +1 day'));

        $testing_mode = true; // production me false

        if ($testing_mode) {
            $next_end = date('Y-m-d', strtotime($next_start . ' +1 day'));
        } else {
            $next_end = date('Y-m-t', strtotime($next_start));
        }
        $next_due = date('Y-m-d', strtotime($next_end . ' +1 days'));

    if ($notice_end && $notice_end < $next_end) {
        // Adjust for remaining notice days
        $next_end = $notice_end;
        $days_in_month = (int)date('t', strtotime($next_start));
        $days_till_notice = (strtotime($next_end) - strtotime($next_start)) / 86400;
        $rent = round(($rent / $days_in_month) * $days_till_notice, 2);
    }

    // Prevent duplicate
    $check = $conn->prepare("
        SELECT COUNT(*) AS cnt FROM BOOKING_RENT_CYCLE 
        WHERE booking_id = ? AND start_date = ? AND end_date = ?
    ");
    $check->bind_param("iss", $booking_id, $next_start, $next_end);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc()['cnt'];

    if ($exists > 0) {
        echo "⚠️ Duplicate rent cycle for Booking $booking_id ($next_start → $next_end)\n";
        $skipped_count++;
        continue;
    }



    // Insert new rent cycle
    $insert = $conn->prepare("
        INSERT INTO BOOKING_RENT_CYCLE 
        (booking_id,room_id , user_id , start_date, end_date, next_due_date, room_rent, amount_due, total_amount, rent_status, created_at, updated_at)
        VALUES (?, ?, ?, ? , ? , ?, ?, ?, ? , 'Pending', NOW(), NOW())
    ");
    $insert->bind_param("iiisssddd", $booking_id, $room_id , $user_id, $next_start, $next_end, $next_due, $rent, $rent , $rent);
    if ($insert->execute()) {
        $created_count++;

        // Notify tenant
        $title = "🧾 New Rent Bill Generated";
        $msg = "Your rent bill of ₹" . number_format($rent, 2) . " has been generated for " .
               date('d M', strtotime($next_start)) . " - " . date('d M, Y', strtotime($next_end)) . ".";
        // pushAndSaveNotification($conn, $user_id, 'Rent', $title, $msg, ["booking_id" => $booking_id]);

        echo "✅ Rent cycle created for Booking $booking_id | ₹$rent | Period: $next_start → $next_end\n";
    } else {
        echo "❌ Failed to insert rent cycle for Booking ID: $booking_id\n";
    }
}

// ✅ Summary
echo "==============================\n";
echo "✅ Rent Cycles Created: $created_count\n";
echo "⚠️ Skipped: $skipped_count\n";
echo "🏁 Completed/Released: $completed_count\n";
echo "==============================\n";


// Complete the booking status for day wise bookings
// by checking if end date has passed and no dues are pending
$stmt = $conn->prepare("
    SELECT B.booking_id, B.user_id, B.room_id, B.booking_status
    FROM BOOKINGS B
    WHERE B.booking_status = 'Active' AND B.booking_type = 'day'
");
$stmt->execute();
$res = $stmt->get_result(); 

while ($b = $res->fetch_assoc()) {
    $booking_id = $b['booking_id'];
    $user_id = $b['user_id'];
    $room_id = $b['room_id'];

    // Get booking end date
    $stmt2 = $conn->prepare("
        SELECT end_date 
        FROM BOOKING_RENT_CYCLE 
        WHERE booking_id = ? 
        ORDER BY end_date DESC 
        LIMIT 1
    ");
    $stmt2->bind_param("i", $booking_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();

    if ($res2->num_rows > 0) {
        $end_date = $res2->fetch_assoc()['end_date'];

        if ($today > $end_date) {
            // Check dues
            $dueCheck = $conn->prepare("
                SELECT SUM(total_amount - paid_amount) AS pending_amount 
                FROM BOOKING_RENT_CYCLE 
                WHERE booking_id = ? AND rent_status IN ('Pending','Partially Paid')
            ");
            $dueCheck->bind_param("i", $booking_id);
            $dueCheck->execute();
            $pending_amount = (float)($dueCheck->get_result()->fetch_assoc()['pending_amount'] ?? 0);
            if ($pending_amount <= 0) {
                // Mark as Completed & release room
                $status_update = $conn->prepare("
                    UPDATE BOOKINGS 
                    SET booking_status = 'Completed', updated_at = NOW() 
                    WHERE booking_id = ?
                ");
                $status_update->bind_param("i", $booking_id);
                $status_update->execute();

                $room_release = $conn->prepare("UPDATE ROOM SET occupied = occupied - 1 WHERE room_id = ?");
                $room_release->bind_param("i", $room_id);
                $room_release->execute();

                echo "✅ Day-wise Booking $booking_id completed and room released.\n";
            }
        }
    }
}


?>
