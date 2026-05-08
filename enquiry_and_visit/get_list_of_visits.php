<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../middlewares/verifyJWT.php';


header("Content-Type: application/json");
include '../dbconn.php';

$response = ["success" => false];

$decoded = verifyJWT(); // Verifies and injects payload into $_REQUEST
$user_id = $_REQUEST['user_id'];


if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // ✅ Required: property_id, user_id (for authorization)
    if (!isset($_GET['property_id'])) {
        echo json_encode(["success" => false, "message" => "Missing Property Id"]);
        exit;
    }

    $property_id = intval($_GET['property_id']);
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;

    try {
        // ✅ Step 1: Verify property exists and get owner_id
        $owner_sql = "SELECT owner_id FROM PROPERTY WHERE property_id = ?";
        $owner_stmt = $conn->prepare($owner_sql);
        $owner_stmt->bind_param("i", $property_id);
        $owner_stmt->execute();
        $owner_result = $owner_stmt->get_result();

        if ($owner_result->num_rows === 0) {
            echo json_encode(["success" => false, "message" => "Invalid property ID"]);
            exit;
        }

        $owner_data = $owner_result->fetch_assoc();
        $owner_id = $owner_data['owner_id'];
        // echo $owner_id;
        // echo $user_id;
        
        // ✅ Step 2: Check if logged-in user is property owner
        if ($owner_id !== $user_id) {
            http_response_code(403);
            echo json_encode([
                "success" => false,
                "message" => "Unauthorized: You are not allowed to view visits for this property"
            ]);
            exit;
        }

        // ✅ Step 3: Fetch visit records (latest first)
        $visits_sql = "
            SELECT v.visit_id, v.user_id, v.property_id, v.visited_date, v.name, v.contact, v.created_at
            FROM VISITS v
            WHERE v.property_id = ?
            ORDER BY v.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $visits_stmt = $conn->prepare($visits_sql);
        $visits_stmt->bind_param("iii", $property_id, $limit, $offset);
        $visits_stmt->execute();
        $visits_result = $visits_stmt->get_result();

        $visits = [];
        while ($row = $visits_result->fetch_assoc()) {
            $visits[] = $row;
        }

        // ✅ Step 4: Get total count for pagination
        $count_sql = "SELECT COUNT(*) AS total FROM VISITS WHERE property_id = ?";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param("i", $property_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total = $count_result->fetch_assoc()['total'];

        $response = [
            "success" => true,
            "message" => "Visit records fetched successfully",
            "data" => $visits,
            "pagination" => [
                "page" => $page,
                "limit" => $limit,
                "total" => intval($total),
                "total_pages" => ceil($total / $limit)
            ]
        ];
    } catch (Exception $e) {
        http_response_code(500);
        $response["message"] = "Error: " . $e->getMessage();
    }
} else {
    $response["message"] = "Invalid request method (use GET)";
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
