<?php
header('Content-Type: application/json');
include '../dbconn.php';

$response = ["success" => false];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $response['message'] = 'Invalid request method. Use GET.';
    echo json_encode($response);
    exit;
}

// Support both /{subscription_id} and ?subscription_id=1
$subscription_id = null;
if (isset($_GET['subscription_id'])) {
    $subscription_id = intval($_GET['subscription_id']);
} elseif (isset($_SERVER['PATH_INFO'])) {
    $parts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
    // Expecting /{subscription_id}
    if (isset($parts[0]) && is_numeric($parts[0])) {
        $subscription_id = intval($parts[0]);
    }
}

if (!$subscription_id) {
    $response['message'] = 'subscription_id is required';
    echo json_encode($response);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT os.subscription_id, os.owner_id, os.plan_id, os.start_date, os.end_date, os.is_active, os.auto_renew, os.created_at, os.updated_at, sp.name AS plan_name, sp.amount, sp.gst, sp.total_amount, sp.property_limit, sp.features FROM OWNER_SUBSCRIPTIONS os JOIN SUBSCRIPTION_PLANS sp ON os.plan_id = sp.plan_id WHERE os.subscription_id = ? LIMIT 1");
    $stmt->bind_param("i", $subscription_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $row['features'] = $row['features'] ? json_decode($row['features'], true) : null;
        $response['success'] = true;
        $response['subscription'] = $row;
    } else {
        $response['message'] = 'Subscription not found';
    }
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>
