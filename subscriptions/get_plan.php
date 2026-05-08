<?php
header('Content-Type: application/json');
include '../dbconn.php';

$response = ["success" => false];

$plan_id = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Support both /plans.php?plan_id=1 and RESTful /plans/1 via PATH_INFO
    if (isset($_GET['plan_id'])) {
        $plan_id = intval($_GET['plan_id']);
    } elseif (isset($_SERVER['PATH_INFO'])) {
        $parts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
        if (isset($parts[1]) && is_numeric($parts[1])) {
            $plan_id = intval($parts[1]);
        }
    }
}

if (!$plan_id) {
    $response['message'] = 'plan_id is required';
    echo json_encode($response);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT plan_id, name, amount, gst, total_amount, property_limit, features, is_active, created_at, updated_at FROM SUBSCRIPTION_PLANS WHERE plan_id = ? LIMIT 1");
    $stmt->bind_param("i", $plan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $row['features'] = $row['features'] ? json_decode($row['features'], true) : null;
        $response['success'] = true;
        $response['plan'] = $row;
    } else {
        $response['message'] = 'Plan not found';
    }
} catch (Exception $e) {
    $response['message'] = 'Error fetching plan: ' . $e->getMessage();
}

echo json_encode($response);
?>