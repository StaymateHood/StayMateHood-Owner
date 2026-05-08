<?php
header("Content-Type: application/json");
include '../dbconn.php';
require_once '../vendor/autoload.php';
require '../env.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$response = ["success" => false];

// =======================================================
// VALIDATE HTTP METHOD
// =======================================================
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["success" => false, "message" => "Invalid Request Method"]);
    exit;
}

// =======================================================
// JWT VALIDATION
// =======================================================
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    echo json_encode(["success" => false, "message" => "Authorization token missing"]);
    exit;
}

try {
    $token = str_replace('Bearer ', '', $authHeader);
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    $owner_id = $decoded->sub ?? null;

    if (!$owner_id) {
        echo json_encode(["success" => false, "message" => "Invalid token"]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Token error: " . $e->getMessage()]);
    exit;
}

// =======================================================
// VALIDATE OWNER
// =======================================================
$stmt = $conn->prepare("SELECT user_id, user_type FROM USERS WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(["success" => false, "message" => "User not found"]);
    exit;
}

if (strtolower($user['user_type']) !== 'owner') {
    echo json_encode(["success" => false, "message" => "User is not an owner"]);
    exit;
}

// =======================================================
// FETCH ACTIVE SUBSCRIPTION
// =======================================================
$stmt = $conn->prepare("
    SELECT 
        os.subscription_id,
        os.plan_id,
        os.start_date,
        os.end_date,
        sp.name AS plan_name,
        sp.property_limit , 
        os.cancel_requested
    FROM OWNER_SUBSCRIPTIONS os
    JOIN SUBSCRIPTION_PLANS sp ON os.plan_id = sp.plan_id
    WHERE os.owner_id = ? AND os.is_active = 1
    ORDER BY os.start_date DESC
    LIMIT 1
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();

if (!$sub) {
    echo json_encode([
        "success" => false,
        "message" => "No active subscription found",
        "can_create_pg" => false
    ]);
    exit;
}

// =======================================================
// CHECK SUBSCRIPTION EXPIRY
// =======================================================
if (strtotime($sub['end_date']) < time()) {
    echo json_encode([
        "success" => false,
        "message" => "Subscription expired. Please renew or upgrade.",
        "can_create_pg" => false
    ]);
    exit;
}

// =======================================================
// FETCH USED PG COUNT
// =======================================================

try {
    $stmt = $conn->prepare("SELECT COUNT(*) AS used FROM PROPERTY WHERE owner_id = ? AND is_active = 1");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $pgRow = $stmt->get_result()->fetch_assoc();
    $used = (int)$pgRow['used'];

} catch (Exception $e) {
    // Table missing or error — safer fallback
    $used = 0;
}

// =======================================================
// CALCULATE PG LIMIT
// =======================================================
$allowed = (int)$sub['property_limit'];
$can_create_pg = $used < $allowed;

// =======================================================
// FINAL RESPONSE
// =======================================================
$response = [
    "success" => true,
    "subscription" => $sub,
    "pg_limit" => [
        "allowed"   => $allowed,
        "used"      => $used,
        "remaining" => max(0, $allowed - $used)
    ],
    "can_create_pg" => $can_create_pg,
    "message" => $can_create_pg
        ? "You can add a new PG."
        : "PG limit reached. Please upgrade your plan."
];

echo json_encode($response);
?>
