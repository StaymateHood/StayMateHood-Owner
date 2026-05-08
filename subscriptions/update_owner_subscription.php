<?php
header('Content-Type: application/json');
include '../dbconn.php';

$response = ["success" => false];

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    $response['message'] = 'Invalid request method. Use PATCH.';
    echo json_encode($response);
    exit;
}

// Support both /owner/{owner_id}/{subscription_id} and ?owner_id=1&subscription_id=2
$owner_id = null;
$subscription_id = null;
if (isset($_GET['owner_id']) && isset($_GET['subscription_id'])) {
    $owner_id = intval($_GET['owner_id']);
    $subscription_id = intval($_GET['subscription_id']);
} elseif (isset($_SERVER['PATH_INFO'])) {
    $parts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
    // Expecting /owner/{owner_id}/{subscription_id}
    if (isset($parts[1]) && is_numeric($parts[1]) && isset($parts[2]) && is_numeric($parts[2])) {
        $owner_id = intval($parts[1]);
        $subscription_id = intval($parts[2]);
    }
}

if (!$owner_id || !$subscription_id) {
    $response['message'] = 'owner_id and subscription_id are required';
    echo json_encode($response);
    exit;
}

// Parse PATCH body (JSON)
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $response['message'] = 'No data provided';
    echo json_encode($response);
    exit;
}

$fields = [];
$params = [];
$types = '';

// Allow updating is_active, auto_renew, end_date (for cancel/renew/extend)
if (isset($input['is_active'])) {
    $fields[] = 'is_active = ?';
    $params[] = intval($input['is_active']);
    $types .= 'i';
}
if (isset($input['auto_renew'])) {
    $fields[] = 'auto_renew = ?';
    $params[] = intval($input['auto_renew']);
    $types .= 'i';
}
if (isset($input['end_date'])) {
    $fields[] = 'end_date = ?';
    $params[] = $input['end_date'];
    $types .= 's';
}

if (empty($fields)) {
    $response['message'] = 'No valid fields to update';
    echo json_encode($response);
    exit;
}

$fields[] = 'updated_at = NOW()';

$sql = "UPDATE OWNER_SUBSCRIPTIONS SET ".implode(', ', $fields)." WHERE owner_id = ? AND subscription_id = ?";
$params[] = $owner_id;
$params[] = $subscription_id;
$types .= 'ii';

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Subscription updated successfully';
    } else {
        $response['message'] = 'Failed to update subscription';
    }
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>
