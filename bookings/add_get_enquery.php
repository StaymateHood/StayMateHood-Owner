<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
include '../dbconn.php';
require_once '../vendor/autoload.php';
require '../env.php';



$method = $_SERVER['REQUEST_METHOD'];

// ── POST: add_inquiry ──────────────────────────────────────────────────────────
if ($method === 'POST') {

    $user_id     = $_POST['user_id'] ?? null;
    $property_id = $_POST['property_id'] ?? null;

    if (!$user_id || !$property_id) {
        echo json_encode(["status" => false, "message" => "user_id and property_id required"]);
        exit;
    }
    
    
        // 🔍 Check if already exists
    $checkStmt = $conn->prepare("SELECT id FROM inquery_data WHERE user_id = ? AND property_id = ?");
    $checkStmt->bind_param("ii", $user_id, $property_id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        echo json_encode([
            "status" => false,
            "message" => "Inquiry already exists"
        ]);
        $checkStmt->close();
        exit;
    }
    $checkStmt->close();


    $stmt = $conn->prepare("INSERT INTO inquery_data (user_id, property_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $property_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => true, "message" => "Inquiry added successfully"]);
    } else {
        echo json_encode(["status" => false, "message" => "Insert failed: " . $conn->error]);
    }

    $stmt->close();

// ── GET: get_inquiry ───────────────────────────────────────────────────────────
} elseif ($method === 'GET') {

    $user_id     = $_GET['user_id'] ?? null;
    $property_id = $_GET['property_id'] ?? null;

    $sql = "SELECT 
                iq.id,
                iq.created_at,
                u.user_id,
                u.name     AS user_name,
                u.email    AS user_email,
                u.phone    AS user_phone,
                p.property_id,
                p.name     AS property_name,
                p.address  AS property_address,
                p.property_type
            FROM inquery_data iq
            LEFT JOIN USERS    u ON iq.user_id     = u.user_id
            LEFT JOIN PROPERTY p ON iq.property_id = p.property_id
            WHERE 1=1";

    $params = [];
    $types  = "";

    if ($user_id) {
        $sql     .= " AND iq.user_id = ?";
        $params[] = $user_id;
        $types   .= "i";
    }

    if ($property_id) {
        $sql     .= " AND iq.property_id = ?";
        $params[] = $property_id;
        $types   .= "i";
    }

    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(["status" => true, "count" => count($data), "data" => $data], JSON_PRETTY_PRINT);

} else {
    echo json_encode(["status" => false, "message" => "Use POST to add, GET to fetch"]);
}

$conn->close();
?>
