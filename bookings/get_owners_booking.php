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
    $page        = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit       = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset      = ($page - 1) * $limit;

    $status = isset($_GET['status']) && !empty($_GET['status']) 
        ? $_GET['status'] 
        : 'Pending';

    try {

        // ✅ Validate property ownership
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

        // ✅ Count total bookings
        $count_sql = "
            SELECT COUNT(*) AS total 
            FROM BOOKINGS 
            WHERE property_id = ? AND booking_status = ?
        ";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param("is", $property_id, $status);
        $count_stmt->execute();
        $count_res = $count_stmt->get_result();
        $total = $count_res->fetch_assoc()['total'];

        // ✅ Fetch bookings list
        $sql = "
            SELECT 
                b.booking_id, 
                b.property_id, 
                u.name, 
                u.phone, 
                b.booking_status,
                b.booking_type, 
                b.room_id, 
                b.room_type,
                b.created_at
            FROM BOOKINGS b
            JOIN USERS u ON u.user_id = b.user_id
            WHERE b.property_id = ? AND b.booking_status = ?
            ORDER BY b.booking_id DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $conn->prepare($sql);
        // $status = "Pending Approval";
        $stmt->bind_param("isii", $property_id, $status, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }

        echo json_encode([
            "success" => true,
            "message" => "Bookings fetched successfully",
            "status_filter" => $status,
            "page" => $page,
            "limit" => $limit,
            "total_records" => intval($total),
            "total_pages" => ceil($total / $limit),
            "data" => $bookings
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
