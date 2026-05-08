<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


header("Content-Type: application/json");
include '../middlewares/verifyJWT.php';
include '../dbconn.php';
include '../notifications/save_notification.php';

$response = ["success" => false];


// $decoded = verifyJWT(); // Verifies and injects payload into $_REQUEST

// $user_id = $_REQUEST['user_id'];
// $email = $_REQUEST['email'];
// $user_type = $_REQUEST['user_type'];

// echo $user_id;



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ✅ Required fields
    $required_fields = ['user_id', 'property_id'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field])) {
            echo json_encode(["success" => false, "message" => "Missing field: $field"]);
            exit;
        }
    }

    // ✅ Extract and sanitize inputs
    $user_id = intval($_POST['user_id']);
    $property_id = intval($_POST['property_id']);
    $visited_date = isset($_POST['visited_date']) && !empty($_POST['visited_date'])
        ? $_POST['visited_date']
        : date('Y-m-d');

    try {
        $conn->begin_transaction();

        // ✅ 1️⃣ Check if unique pair already exists
        $check_sql = "SELECT visit_id FROM VISITS WHERE visited_date = ? AND property_id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sii", $visited_date, $property_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            http_response_code(409);
            echo json_encode([
                "success" => false,
                "message" => "Visit already exists for this user, property, and date"
            ]);
            $conn->rollback();
            exit;
        }

        // ✅ Fetch name and contact from USERS table
        $user_sql = "SELECT name, phone FROM USERS WHERE user_id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();

        $visitor_name = $user_data ? $user_data['name'] : null;
        $visitor_contact = $user_data ? $user_data['phone'] : null;

        // ✅ 2️⃣ Insert visit and fetch owner_id in one query
        $insert_sql = "
            INSERT INTO VISITS (user_id, property_id, name, contact, visited_date, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iisss", $user_id, $property_id, $visitor_name, $visitor_contact, $visited_date);

        if ($insert_stmt->execute()) {
            $visit_id = $conn->insert_id;

            // ✅ Fetch owner_id using subquery (still within same transaction)
            $owner_sql = "SELECT owner_id, name FROM PROPERTY WHERE property_id = ?";
            $owner_stmt = $conn->prepare($owner_sql);
            $owner_stmt->bind_param("i", $property_id);
            $owner_stmt->execute();
            $owner_result = $owner_stmt->get_result();
            $owner_data = $owner_result->fetch_assoc();
            $owner_id = $owner_data ? $owner_data['owner_id'] : null;
            $property_name = $owner_data ? $owner_data['name'] : null;

            // ✅ Send notification only if owner exists
            if ($owner_id) {
                $title = "🧍‍♂️ New Visit Request!";
                $msg = "A tenant has shown interest in your property {{$property_name}}. 📅 Visit Date: {{$visited_date}}";
                $pushResponse = pushAndSaveNotification(
                    $conn,
                    $owner_id,
                    'Visits',
                    $title,
                    $msg
                );
            }

            $conn->commit();

            http_response_code(201);
            $response = [
                "success" => true,
                "message" => "Visit created successfully",
                "visit_id" => $visit_id,
                "user_id" => $user_id,
                "property_id" => $property_id,
                "owner_id" => $owner_id,
                "name" => $visitor_name,
                "contact" => $visitor_contact,
                "visited_date" => $visited_date
            ];
        } else {
            $conn->rollback();
            $response["message"] = "Failed to insert visit record";
        }

        $insert_stmt->close();
        $check_stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $response["message"] = "Error: " . $e->getMessage();
    }
} else {
    $response["message"] = "Invalid request method (use POST)";
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
