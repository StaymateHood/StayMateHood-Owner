<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
include '../dbconn.php';
include '../middlewares/verifyJWT.php';

$decoded = verifyJWT(); // Sets user_id into $_REQUEST
$user_id = $_REQUEST['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {

    // ✅ Read body for PATCH request
    $input = json_decode(file_get_contents("php://input"), true);

    if (!isset($input['enquiry_id']) || empty($input['enquiry_id'])) {
        echo json_encode(["success" => false, "message" => "Missing enquiry_id"]);
        exit;
    }

    $enquiry_id = intval($input['enquiry_id']);

    try {
        // ✅ 1️⃣ Get property owner
        $sql = "
            SELECT E.property_id, P.owner_id, E.status
            FROM ENQUIRY E
            JOIN PROPERTY P ON E.property_id = P.property_id
            WHERE E.enquiry_id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $enquiry_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            echo json_encode(["success" => false, "message" => "Enquiry not found"]);
            exit;
        }

        $data = $res->fetch_assoc();
        $owner_id = $data['owner_id'];
        $current_status = $data['status'];

        // ✅ 2️⃣ Check access
        if ($owner_id != $user_id) {
            echo json_encode(["success" => false, "message" => "Unauthorized Access"]);
            exit;
        }

        // ✅ 3️⃣ Check already updated
        if ($current_status === "Completed") {
            echo json_encode(["success" => false, "message" => "Already completed"]);
            exit;
        }

        // ✅ 4️⃣ Update status → Completed
        $update_sql = "UPDATE ENQUIRY SET status = 'Completed' WHERE enquiry_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $enquiry_id);

        if ($update_stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Enquiry status updated to Completed",
                "enquiry_id" => $enquiry_id
            ], JSON_PRETTY_PRINT);
            exit;
        } else {
            echo json_encode(["success" => false, "message" => "Update failed"]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
        exit;
    }

} else {
    // ✅ Update message for correct method
    echo json_encode(["success" => false, "message" => "Invalid request method (Use PATCH)"]);
    exit;
}
?>
