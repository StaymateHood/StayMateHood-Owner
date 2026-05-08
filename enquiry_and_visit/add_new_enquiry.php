<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
include '../dbconn.php';
include '../middlewares/verifyJWT.php';
include '../notifications/save_notification.php'; // ✅ Add this

$response = ["success" => false];

$decoded = verifyJWT();
$user_id = $_REQUEST['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $required_fields = ['property_id','schedule_date'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            echo json_encode(["success" => false, "message" => "Missing field: $field"]);
            exit;
        }
    }

    $property_id = intval($_POST['property_id']);
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
    $schedule_date = !empty($_POST['schedule_date']) ? $_POST['schedule_date'] : date('Y-m-d');

    try {
        // ✅ Check Duplicate
        $check_sql = "SELECT enquiry_id FROM ENQUIRY WHERE user_id=? AND property_id=? AND schedule_date=?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iis", $user_id, $property_id, $schedule_date);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            echo json_encode([
                "success" => false,
                "message" => "Already scheduled on the same date for this property"
            ]);
            exit;
        }

        $conn->begin_transaction();

        // ✅ Get User Details
        $user_sql = "SELECT name, phone FROM USERS WHERE user_id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_data = $user_stmt->get_result()->fetch_assoc();

        if (!$user_data) {
            $conn->rollback();
            echo json_encode(["success" => false, "message" => "User not found"]);
            exit;
        }

        $name = $user_data['name'];
        $contact = $user_data['phone'];

        // ✅ Get Owner & Property Name
        $property_sql = "SELECT name, owner_id FROM PROPERTY WHERE property_id = ?";
        $property_stmt = $conn->prepare($property_sql);
        $property_stmt->bind_param("i", $property_id);
        $property_stmt->execute();
        $property_data = $property_stmt->get_result()->fetch_assoc();

        if (!$property_data) {
            $conn->rollback();
            echo json_encode(["success" => false, "message" => "Property not found"]);
            exit;
        }

        $property_name = $property_data['name'];
        $owner_id = $property_data['owner_id'];

        // ✅ Insert ENQUIRY
        $insert_sql = "INSERT INTO ENQUIRY (user_id, property_id, notes, schedule_date, name, contact)
                       VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iissss", $user_id, $property_id, $notes, $schedule_date, $name, $contact);

        if ($insert_stmt->execute()) {

            // ✅ Send Notification to Owner
            if ($owner_id) {
                $title = "🧍‍♂️ New Visit Request!";
                $msg = "A tenant has requested a visit to your property {{$property_name}} on {{$schedule_date}} .";
                $title1 = "✅ Visit Request Sent!";
                $msg1 = "Your visit request for {{$property_name}} has been sent to the owner.";
                pushAndSaveNotification($conn, $owner_id, 'Inquiry', $title, $msg);
                pushAndSaveNotification($conn, $user_id, 'Inquiry', $title1, $msg1);
            }

            $conn->commit();
            $eq_id =$conn->insert_id;
            echo json_encode([
                "success" => true,
                "message" => "Enquiry created successfully",
                "enquiry_id" => $eq_id,
                "name" => $name,
                // "contact" => $contact,
                "schedule_date" => $schedule_date
            ], JSON_PRETTY_PRINT);
            exit;
        }

        $conn->rollback();
        echo json_encode(["success" => false, "message" => "Failed to create enquiry"]);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
        exit;
    }

} else {
    echo json_encode(["success" => false, "message" => "Invalid request method (Use POST)"]);
    exit;
}
?>
