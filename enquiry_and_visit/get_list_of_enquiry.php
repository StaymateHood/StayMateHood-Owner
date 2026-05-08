<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
include '../dbconn.php';
include '../middlewares/verifyJWT.php';

$decoded = verifyJWT(); // sets user_id in $_REQUEST
$user_id = $_REQUEST['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (!isset($_GET['property_id']) || empty($_GET['property_id'])) {
        echo json_encode(["success" => false, "message" => "Missing property_id"]);
        exit;
    }

    $property_id = intval($_GET['property_id']);
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;

    // ✅ Status filter
    $status = isset($_GET['status']) && !empty($_GET['status']) 
        ? $_GET['status'] 
        : 'Pending';

    try {
        // ✅ 1️⃣ Validate property owner
        $owner_sql = "SELECT owner_id FROM PROPERTY WHERE property_id = ?";
        $owner_stmt = $conn->prepare($owner_sql);
        $owner_stmt->bind_param("i", $property_id);
        $owner_stmt->execute();
        $owner_res = $owner_stmt->get_result();

        if ($owner_res->num_rows === 0) {
            echo json_encode(["success" => false, "message" => "Property not found"]);
            exit;
        }

        $owner = $owner_res->fetch_assoc();
        if ($owner['owner_id'] != $user_id) {
            echo json_encode(["success" => false, "message" => "Unauthorized Access"]);
            exit;
        }

        // ✅ 2️⃣ Fetch enquiries with pagination + status filter
        $sql = "
            SELECT enquiry_id, user_id, property_id, name, contact, notes, schedule_date, status
            FROM ENQUIRY
            WHERE property_id = ? AND status = ?
            ORDER BY enquiry_id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isii", $property_id, $status, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $enquiries = [];
        while ($row = $result->fetch_assoc()) {
            $enquiries[] = $row;
        }

        echo json_encode([
            "success" => true,
            "message" => "Data fetched successfully",
            "status_filter" => $status,
            "page" => $page,
            "limit" => $limit,
            "total" => count($enquiries),
            "data" => $enquiries
        ], JSON_PRETTY_PRINT);
        exit;

    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
        exit;
    }

} else {
    echo json_encode(["success" => false, "message" => "Invalid request method (use GET)"]);
    exit;
}
?>
