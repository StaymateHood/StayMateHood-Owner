<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
include '../dbconn.php';
include '../middlewares/verifyJWT.php';

// Verify Login
$decoded = verifyJWT();
$user_id = $_REQUEST['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (!isset($_GET['property_id']) || empty($_GET['property_id'])) {
        echo json_encode(["success" => false, "message" => "property_id is required"]);
        exit;
    }

    $property_id = intval($_GET['property_id']);

    try {

        // // ✅ Count Pending Enquiries for this Property
        // $sqlEnquiry = "SELECT COUNT(*) AS pending_enquiries 
        //                FROM ENQUIRY 
        //                WHERE status = 'Pending' AND property_id = ?";
        // $stmt1 = $conn->prepare($sqlEnquiry);
        // $stmt1->bind_param("i", $property_id);
        // $stmt1->execute();
        // $pending_enquiries = $stmt1->get_result()->fetch_assoc()['pending_enquiries'] ?? 0;

        // // ✅ Count Visits for this Property
        // $sqlVisits = "SELECT COUNT(*) AS total_visits 
        //               FROM VISITS 
        //               WHERE property_id = ?";
        // $stmt2 = $conn->prepare($sqlVisits);
        // $stmt2->bind_param("i", $property_id);
        // $stmt2->execute();
        // $total_visits = $stmt2->get_result()->fetch_assoc()['total_visits'] ?? 0;
        $stmt1 = $conn->prepare("SELECT COUNT(*) FROM PROPERTY_VISITS WHERE property_id = ?");
        $stmt1->bind_param("i", $property_id);
        $stmt1->execute();
        $stmt1->bind_result($total_visits);
        $stmt1->fetch();
        $stmt1->close();

        // Get total enquiries
        $stmt2 = $conn->prepare("SELECT COUNT(*) FROM inquery_data WHERE property_id = ?");
        $stmt2->bind_param("i", $property_id);
        $stmt2->execute();
        $stmt2->bind_result($total_enquiry);
        $stmt2->fetch();
        $stmt2->close();


        echo json_encode([
            "success" => true,
            "property_id" => $property_id,
            "pending_enquiries" => $total_enquiry,
            "total_visits" => $total_visits
        ], JSON_PRETTY_PRINT);
        exit;

    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
        exit;
    }

} else {
    echo json_encode(["success" => false, "message" => "Invalid request method (Use GET)"]);
    exit;
}
