<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../dbconn.php';
require_once '../vendor/autoload.php';
require '../env.php'; // contains $secret_key

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Token validation
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    echo json_encode(['success' => false, 'message' => 'Authorization token missing']);
    exit;
}

$token = str_replace('Bearer ', '', $authHeader);

try {
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    $user_id = $decoded->sub ?? null;
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid token: user ID not found']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Token error: ' . $e->getMessage()]);
    exit;
}

// Extract POST data
$property_id  = $_POST['property_id'] ?? null;
$room_id      = $_POST['room_id'] ?? null;
$subject      = $_POST['subject'] ?? null;
$description  = $_POST['description'] ?? null;
$ticket_type  = $_POST['ticket_type'] ?? null;
$priority     = $_POST['priority'] ?? null;
$assignee_id  = $_POST['assignee_id'] ?? null;
$status       = 'New';
$created_at   = date('Y-m-d H:i:s');
$updated_at   = $created_at;
$attachments  = null;

// Validate required fields
if (!$property_id || !$subject || !$description || !$ticket_type || !$priority) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}
if ($room_id === null || $room_id === '' || strtolower($room_id) === 'undefined') {
    echo json_encode(['success' => false, 'message' => 'room_id has not been assigned']);
    exit;
}

// Handle file upload
if (isset($_FILES['attachments']) && $_FILES['attachments']['error'] === 0) {
    $target_dir = "uploads/";
    $file_name = basename($_FILES["attachments"]["name"]);
    $file_tmp = $_FILES["attachments"]["tmp_name"];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($file_ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Only image files are allowed']);
        exit;
    }

    $new_name = uniqid('ticket_') . "." . $file_ext;
    $target_file = $target_dir . $new_name;

    if (move_uploaded_file($file_tmp, $target_file)) {
        $attachments = $target_file;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
        exit;
    }
}

// Insert into database
$stmt = $conn->prepare("INSERT INTO TICKETS 
    (user_id, property_id, room_id, subject, description, ticket_type, priority, status, assignee_id, created_at, updated_at, attachments)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("iiisssssssss", $user_id, $property_id, $room_id, $subject, $description, $ticket_type, $priority, $status, $assignee_id, $created_at, $updated_at, $attachments);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Ticket raised successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to raise ticket: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>