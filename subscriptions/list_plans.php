<?php
header('Content-Type: application/json');
include '../dbconn.php';

$response = ["success" => false];

try {
    $sql = "SELECT plan_id, name, amount, gst, total_amount, property_limit, features, is_active, created_at, updated_at FROM SUBSCRIPTION_PLANS WHERE is_active = 1 ORDER BY amount ASC";
    $result = $conn->query($sql);
    $plans = [];
    while ($row = $result->fetch_assoc()) {
        // Decode features JSON if present
        $row['features'] = $row['features'] ? json_decode($row['features'], true) : null;
        $plans[] = $row;
    }
    $response['success'] = true;
    $response['plans'] = $plans;
} catch (Exception $e) {
    $response['message'] = 'Error fetching plans: ' . $e->getMessage();
}

echo json_encode($response);
?>