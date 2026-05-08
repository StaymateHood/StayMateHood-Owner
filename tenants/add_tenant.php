<?php
/**
 * Manual Tenant Addition (Owner App)
 * - Creates user (tenant) if phone not exists
 * - Creates booking (day/month)
 * - Creates first BOOKING_RENT_CYCLE
 * - Creates PAYMENT for advance (if any) and applies to cycle
 * - Updates ROOM occupancy
 * - Sends notifications
 *
 * Expected POST fields:
 *  - name (required)
 *  - phone (required)
 *  - room_id (required)
 *  - booking_type (required) => 'month' or 'day'
 *  - start_date (required) => YYYY-MM-DD
 *  - end_date (required only when booking_type == 'day') => YYYY-MM-DD
 *  - rent (required) => numeric (rent_per_month or rent_per_day depending on booking_type)
 *  - advance_paid (required) => numeric (can be 0)
 *  - security_deposit (optional) => numeric
 *  - email (optional)
 *  - owner_flow (optional) => 1 (if from owner branch)
 *
 * Uses: include '../notifications/save_notification.php' for pushAndSaveNotification (it must provide $conn)
 */

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

header('Content-Type: application/json');

include '../notifications/save_notification.php';

// must provide $conn and pushAndSaveNotification()
if (isset($_POST['action']) && $_POST['action'] == 'owner') {

    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception("Invalid request method");
        }

        // --------------------------
        // INPUTS (kept EXACT same names)
        // --------------------------
        $name             = trim($_POST['name'] ?? '');
        $phone            = trim($_POST['phone'] ?? '');
        $room_id          = intval($_POST['room_id'] ?? 0);
        $booking_type     = strtolower(trim($_POST['booking_type'] ?? ''));
        $start_date_raw   = trim($_POST['start_date'] ?? '');
        $rent             = floatval($_POST['rent'] ?? 0);
        $advance_paid     = floatval($_POST['advance_paid'] ?? 0);
        $security_deposit = floatval($_POST['security_deposit'] ?? 0);
        $email            = trim($_POST['email'] ?? '');
        $owner_flow       = isset($_POST['owner_flow']) ? (bool)$_POST['owner_flow'] : true;

        $end_date_raw = ($booking_type == 'day') ? trim($_POST['end_date'] ?? '') : null;
        
        // $end_date_raw =  trim($_POST['end_date']) ?? '';

        // --------------------------
        // BASIC VALIDATION (no change)
        // --------------------------
        if ($name === '' || $phone === '' || $room_id <= 0 ||
            !in_array($booking_type, ['month','day']) ||
            $start_date_raw === '' || $rent <= 0) {
            throw new Exception("Missing or invalid required fields. Required: name, phone, room_id, booking_type(month|day), start_date, rent, advance_paid (0 allowed). For day booking end_date is required.");
        }

        if ($booking_type === 'day' && ($end_date_raw === '' || strtotime($end_date_raw) === false)) {
            throw new Exception("For 'day' booking_type end_date is required and must be valid date.");
        }

        // --------------------------
        // DATE NORMALIZATION
        // --------------------------
        $start_ts = strtotime($start_date_raw);
        if ($start_ts === false) throw new Exception("Invalid start_date format");
        $start_date = date('Y-m-d', $start_ts);

        $end_date = null;
        if ($end_date_raw !== null) {
            $end_ts = strtotime($end_date_raw);
            if ($end_ts === false) throw new Exception("Invalid end_date format");
            if ($end_ts < $start_ts) throw new Exception("end_date cannot be before start_date");
            $end_date = date('Y-m-d', $end_ts);
        }

        $now = date("Y-m-d H:i:s");

        // --------------------------
        // ROOM DETAILS
        // --------------------------
        $stmt = $conn->prepare("
            SELECT room_id, property_id, room_type, occupied, capacity, room_number 
            FROM ROOM WHERE room_id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $room = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$room) throw new Exception("Room not found");

        $property_id = $room['property_id'];
        $room_type_db = $room['room_type'];
        $room_number = $room['room_number'];
        $capacity_full = ($room['occupied'] >= $room['capacity']);

        // --------------------------
        // CHECK USER EXISTS
        // --------------------------
        $stmt = $conn->prepare("SELECT user_id FROM USERS WHERE phone = ? LIMIT 1");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Tenant with this phone number already exists");
        }
        $stmt->close();

        // --------------------------
        // TRANSACTION START
        // --------------------------
        $conn->begin_transaction();

        // --------------------------
        // CREATE USER
        // --------------------------
        $stmt = $conn->prepare("
            INSERT INTO USERS (name, phone, email, profile_image, created_at, updated_at, is_verified, is_active, user_type)
            VALUES (?, ?, ?, NULL, ?, ?, 0, 1, 'tenant')
        ");
        $stmt->bind_param("sssss", $name, $phone, $email, $now, $now);
        $stmt->execute();
        $user_id = $stmt->insert_id;
        $stmt->close();

        // --------------------------
        // BOOKING HANDLING (same logic)
        // --------------------------
        // if ($booking_type === 'month') {
        //     $rent_per_month = $rent;
        //     $cycle_start = $start_date;
        //     $cycle_end   = date('Y-m-d', strtotime($cycle_start . ' +29 days'));
        //     $total_amount = $rent_per_month + $security_deposit;
        //     $end_date_db = null;
        // } else {
        //     $rent_per_month = $rent;
        //     $total_amount = $rent + $security_deposit;
        //     $cycle_start = $start_date;
        //     $cycle_end   = $end_date;
        //     $end_date_db = $end_date;
        // }

        $today = date('Y-m-d');

        if ($booking_type === 'month') {

            // Always create ONLY current month cycle
            $cycle_start = date('Y-m-01');                 // 1st day of current month
            $cycle_end   = date('Y-m-t');                  // Last day of current month

            $rent_per_month = $rent;
            $total_amount   = $rent_per_month + $security_deposit;
            // $end_date_db    = null;
                        $end_date_db    = date('Y-m-d', strtotime($start_date . ' +1 month - 1 day'));


        } else {
            $rent_per_month = $rent;
            $total_amount = $rent;
            $security_deposit = 0;
            $cycle_start = $start_date;
            $cycle_end   = $end_date;
            $end_date_db = $end_date;
        }


        // --------------------------
        // INSERT BOOKING
        // --------------------------
        $stmt = $conn->prepare("
            INSERT INTO BOOKINGS
            (user_id, room_id, property_id, booking_type, start_date, end_date,
             rent_per_month, room_type, security_deposit, total_amount, booking_status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?)
        ");

        $stmt->bind_param(
            "iiisssdsddss",
            $user_id,
            $room_id,
            $property_id,
            $booking_type,
            $start_date,
            $end_date_db,
            $rent_per_month,
            $room_type_db,
            $security_deposit,
            $total_amount,
            $now,
            $now
        );
        $stmt->execute();
        $booking_id = $stmt->insert_id;
        $stmt->close();

        // --------------------------
        // UPDATE OCCUPANCY
        // --------------------------
        $stmt = $conn->prepare("UPDATE ROOM SET occupied = occupied + 1, updated_at = ? WHERE room_id = ?");
        $stmt->bind_param("si", $now, $room_id);
        $stmt->execute();
        $stmt->close();

        // --------------------------
        // RENT CYCLE
        // --------------------------
        $paid_for_cycle = min($advance_paid, $total_amount);
        $amount_due = $total_amount - $paid_for_cycle;

        if ($paid_for_cycle >= $total_amount) $rent_status = "Paid";
        elseif ($paid_for_cycle > 0)        $rent_status = "Partially Paid";
        else                                $rent_status = "Pending";

        // $stmt = $conn->prepare("
        //     INSERT INTO BOOKING_RENT_CYCLE
        //     (booking_id, user_id, room_id, start_date, end_date, room_rent, total_amount,
        //      security_amount, amount_due, paid_amount, rent_status, created_at, updated_at)
        //     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        // ");

        // $stmt->bind_param(
        //     "iiissdddddsss",
        //     $booking_id,
        //     $user_id,
        //     $room_id,
        //     $cycle_start,
        //     $cycle_end,
        //     $rent_per_month,
        //     $total_amount,
        //     $security_deposit,
        //     $amount_due,
        //     $paid_for_cycle,
        //     $rent_status,
        //     $now,
        //     $now
        // );
        // $stmt->execute();
        // $cycle_id = $stmt->insert_id;
        // $stmt->close();
        
        
        $next_due_date = date('Y-m-d', strtotime($cycle_end . ' +1 day'));

        $stmt = $conn->prepare("
            INSERT INTO BOOKING_RENT_CYCLE
            (booking_id, user_id, room_id, start_date, end_date, next_due_date, room_rent, total_amount,
             security_amount, amount_due, paid_amount, rent_status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "iiisssdddddsss",
            $booking_id,
            $user_id,
            $room_id,
            $cycle_start,
            $cycle_end,
            $next_due_date,
            $rent_per_month,
            $total_amount,
            $security_deposit,
            $amount_due,
            $paid_for_cycle,
            $rent_status,
            $now,
            $now
        );
        $stmt->execute();
        $cycle_id = $stmt->insert_id;
        $stmt->close();


        // --------------------------
        // PAYMENT (ONLY if advance_paid)
        // --------------------------
        $payment_id = null;
        if ($advance_paid > 0) {
            $transaction_id = uniqid("txn_");

            $stmt = $conn->prepare("
                INSERT INTO PAYMENTS
                (user_id, booking_id, cycle_id, amount, payment_type, payment_status,
                 payment_session_id, transaction_id, payment_method, currency, payment_date, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'rent', 'Completed', NULL, ?, 'Cash (Owner)', 'INR', NOW(), ?, ?)
            ");
            $stmt->bind_param(
                "iiidsss",
                $user_id,
                $booking_id,
                $cycle_id,
                $advance_paid,
                $transaction_id,
                $now,
                $now
            );
            $stmt->execute();
            $payment_id = $stmt->insert_id;
            $stmt->close();

            // Update cycle
            $stmt = $conn->prepare("UPDATE BOOKING_RENT_CYCLE SET rent_status = ?, payment_id = ?, updated_at = ? WHERE cycle_id = ?");
            $stmt->bind_param("sisi", $rent_status, $payment_id, $now, $cycle_id);
            $stmt->execute();
            $stmt->close();
        }

        // --------------------------
        // COMMIT
        // --------------------------
        $conn->commit();

        // --------------------------
        // NOTIFICATIONS (same logic)
        // --------------------------
        $stmt = $conn->prepare("SELECT name FROM PROPERTY WHERE property_id = ? LIMIT 1");
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $prop = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $property_name = $prop['name'] ?? 'Property';

        pushAndSaveNotification(
            $conn, 
            $user_id,
            'Other',
            "Welcome to {$property_name}",
            "You have been added as a tenant for Room {$room_number}. Check-in: {$start_date}.",
            ["booking_id" => $booking_id]
        );

        // Owner notify
        $stmt = $conn->prepare("SELECT owner_id FROM PROPERTY WHERE property_id = ? LIMIT 1");
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $owner = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($owner['owner_id'])) {
            pushAndSaveNotification(
                $conn,
                $owner['owner_id'],
                'Other',
                "Tenant Added",
                "{$name} added as tenant for Room {$room_number} (Booking ID: {$booking_id})",
                ["booking_id" => $booking_id]
            );
        }

        // --------------------------
        // SUCCESS RESPONSE (unchanged keys)
        // --------------------------
        $response = [
            "status" => "success",
            "message" => "Tenant added and booking created successfully",
            "user_id" => $user_id,
            "booking_id" => $booking_id,
            "cycle_id" => $cycle_id,
            "payment_id" => $payment_id,
            "advance_applied" => round($paid_for_cycle, 2),
            "amount_due_for_cycle" => round($amount_due, 2)
        ];
        if ($capacity_full) $response['warning'] = "Room capacity was already full; occupancy was still incremented.";

        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;

    } catch (Exception $e) {
        @$conn->rollback();
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ], JSON_PRETTY_PRINT);
        exit;
}
}else {
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $room_id = $_POST['room_id'] ?? '';
    $security_deposit = $_POST['security_deposit'] ?? 0;
    $security_deposit = is_numeric($security_deposit) ? (float) $security_deposit : 0;

    // Validate required fields
    if (empty($name) || empty($phone) || empty($room_id)) {
        echo json_encode(["status" => "error", "message" => "Missing required fields"]);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT user_id FROM USERS WHERE phone = ? LIMIT 1");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Tenant with this phone number already exists"]);
        exit;
    }
    $stmt->close();

    $created_at = date('Y-m-d H:i:s');

    // Upload images
    $upload_dir = "uploads/profile_images/";
    if (!is_dir($upload_dir))
        mkdir($upload_dir, 0777, true);

    $profile_image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $profile_ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $profile_name = uniqid('profile_') . '.' . $profile_ext;
        $profile_image = $upload_dir . $profile_name;
        move_uploaded_file($_FILES["image"]["tmp_name"], $profile_image);
    }

    $id_image = null;
    if (isset($_FILES['id_image']) && $_FILES['id_image']['error'] == 0) {
        $id_upload_dir = "uploads/id_images/";
        if (!is_dir($id_upload_dir))
            mkdir($id_upload_dir, 0777, true);
        $id_ext = pathinfo($_FILES["id_image"]["name"], PATHINFO_EXTENSION);
        $id_name = uniqid('id_') . '.' . $id_ext;
        $id_image = $id_upload_dir . $id_name;
        move_uploaded_file($_FILES["id_image"]["tmp_name"], $id_image);
    }

    // 1. Insert into USERS
    $stmt = $conn->prepare("INSERT INTO USERS (name, phone, profile_image, ID_images, created_at, updated_at, is_verified, is_active, user_type) VALUES (?, ?, ?, ?, ?, ?, 1, 1, 'tenant')");
    $stmt->bind_param("ssssss", $name, $phone, $profile_image, $id_image, $created_at, $created_at);
    if (!$stmt->execute()) {
        echo json_encode(["status" => "error", "message" => "User creation failed: " . $stmt->error]);
        exit;
    }
    $user_id = $conn->insert_id;

    // 2. Get room details using room_id
    $stmt = $conn->prepare("SELECT rent_per_room, property_id FROM ROOM WHERE room_id = ? LIMIT 1");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        echo json_encode(["status" => "error", "message" => "Room not found"]);
        exit;
    }
    $room = $result->fetch_assoc();
    $property_id = $room['property_id'];
    $rent_per_month = $room['rent_per_room'];

    // 3. Insert into BOOKINGS
    $booking_status = 'Confirmed';
    $total_amount = $rent_per_month + $security_deposit;
    $payment_amount = $total_amount;
    $stmt = $conn->prepare("INSERT INTO BOOKINGS (user_id, room_id, property_id, start_date, rent_per_month, security_deposit_paid, booking_status, total_amount, created_at, updated_at) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiddssss", $user_id, $room_id, $property_id, $rent_per_month, $security_deposit, $booking_status, $total_amount, $created_at, $created_at);
    if (!$stmt->execute()) {
        echo json_encode(["status" => "error", "message" => "Booking creation failed: " . $stmt->error]);
        exit;
    }
    $booking_id = $conn->insert_id;

    // 3.1 Update ROOM occupancy
    $stmt = $conn->prepare("UPDATE ROOM SET occupied = occupied + 1, updated_at = ? WHERE room_id = ?");
    $stmt->bind_param("si", $created_at, $room_id);
    if (!$stmt->execute()) {
        echo json_encode(["status" => "error", "message" => "Failed to update room occupancy: " . $stmt->error]);
        exit;
    }
    if ($stmt->affected_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Room occupancy not updated. Check if room_id exists and is correct."]);
        exit;
    }

    // 4. Insert into PAYMENTS
    $payment_status = 'Completed';
    $payment_method = 'Cash';
    $transaction_id = uniqid('txn_');
    $stmt = $conn->prepare("INSERT INTO PAYMENTS (user_id, booking_id, amount, payment_status, transaction_id, payment_method, payment_date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssssss", $user_id, $booking_id, $payment_amount, $payment_status, $transaction_id, $payment_method, $created_at, $created_at, $created_at);
    if (!$stmt->execute()) {
        echo json_encode(["status" => "error", "message" => "Payment creation failed: " . $stmt->error]);
        exit;
    }
    
    // Push notification
    $stmt = $conn->prepare("SELECT P.name, R.room_number 
                            FROM PROPERTY P 
                            JOIN ROOM R ON P.property_id = R.property_id 
                            WHERE R.room_id = ? LIMIT 1");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $propResult = $stmt->get_result();
    $propData = $propResult->fetch_assoc();
    $property_name = $propData['name'] ?? 'Unknown Property';
    $room_number = $propData['room_number'] ?? 'N/A';
    
    $ownerStmt = $conn->prepare("SELECT owner_id, name FROM PROPERTY WHERE property_id = ?");
    $ownerStmt->bind_param("i", $property_id);
    $ownerStmt->execute();
    $ownerResult = $ownerStmt->get_result();
    $owner = $ownerResult->fetch_assoc();
    $owner_id = $owner['owner_id'];
    $room_name = $owner['name'];
    
    // Prepare notification message
    $title = "New Tenant Added";
    $msg = "A tenant added in {$property_name}, Room {$room_name}.";
    
    // Send push notification with readable data
    $pushData = [
        "booking_id" => $booking_id,
        "property_id" => $property_id,
        "property_name" => $property_name,
        "room_number" => $room_number
    ];
    
    $pushResponse = pushAndSaveNotification(
        $conn,
        $owner_id,
        'Other',
        $title,
        $msg,
        $pushData
    );

    echo json_encode([
        "status" => "success",
        "message" => "Tenant added successfully",
        "user_id" => $user_id,
        "booking_id" => $booking_id,
        "profile_image" => $profile_image,
        "id_image" => $id_image
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}
}
?>
