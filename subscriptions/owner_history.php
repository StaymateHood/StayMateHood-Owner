<?php
header('Content-Type: application/json');
include '../dbconn.php';

$response = ["success" => false];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $response['message'] = 'Invalid request method. Use GET.';
    echo json_encode($response);
    exit;
}

// Support both /owner/{owner_id}/history and /history.php?owner_id=1
$owner_id = null;
if (isset($_GET['owner_id'])) {
    $owner_id = intval($_GET['owner_id']);
} elseif (isset($_SERVER['PATH_INFO'])) {
    $parts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
    // Expecting /owner/{owner_id}/history
    if (isset($parts[1]) && is_numeric($parts[1])) {
        $owner_id = intval($parts[1]);
    }
}

if (!$owner_id) {
    $response['message'] = 'owner_id is required';
    echo json_encode($response);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT os.subscription_id, os.owner_id, os.plan_id, os.start_date, os.end_date, os.is_active, os.created_at, os.updated_at, sp.name AS plan_name, sp.amount, sp.gst, sp.total_amount, sp.property_limit, sp.features FROM OWNER_SUBSCRIPTIONS os JOIN SUBSCRIPTION_PLANS sp ON os.plan_id = sp.plan_id WHERE os.owner_id = ? ORDER BY os.start_date DESC");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $row['features'] = $row['features'] ? json_decode($row['features'], true) : null;
        $history[] = $row;
    }
    $response['success'] = true;
    $response['history'] = $history;
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>
