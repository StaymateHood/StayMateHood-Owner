<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
include '../dbconn.php';
require '../vendor/autoload.php';
require '../env.php'; // Assuming env.php has Firebase config

// use Kreait\Firebase\Factory;

// // Initialize Firebase
// $factory = (new Factory)->withServiceAccount('../firebase-notifications/sand111-firebase-adminsdk-8cfhs-5a1ae6c94d.json');
// $database = $factory->createDatabase();

// Get POST data
$owner_id = $_POST['owner_id'] ?? '';
$tenant_id = $_POST['tenant_id'] ?? '';

if (empty($owner_id) || empty($tenant_id)) {
    echo json_encode(["success" => false, "message" => "Owner ID and Tenant ID are required."]);
    exit;
}

// Check if users exist
$check_sql = "SELECT user_id FROM USERS WHERE user_id IN (?, ?)";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $owner_id, $tenant_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows < 2) {
    echo json_encode(["success" => false, "message" => "One or both users do not exist. Please check user IDs."]);
    exit;
}

// Check if chat already exists
$sql = "SELECT chat_id FROM private_chats WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $owner_id, $tenant_id, $tenant_id, $owner_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode(["success" => true, "chat_id" => $row['chat_id'], "message" => "Chat already exists."]);
} else {
    // Create new chat
    $insert_sql = "INSERT INTO private_chats (user1_id, user2_id) VALUES (?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("ii", $owner_id, $tenant_id);
    if ($insert_stmt->execute()) {
        $chat_id = $conn->insert_id;
        echo json_encode(["success" => true, "chat_id" => $chat_id, "message" => "Chat started successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to start chat."]);
    }
}
?>