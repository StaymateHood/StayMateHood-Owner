<?php
header('Content-Type: application/json');
include '../dbconn.php';
require_once '../vendor/autoload.php';
require '../env.php'; // Must define $secret_key

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$response = ["success" => false];

// JWT Authentication
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    echo json_encode(["success" => false, "message" => "Authorization token missing"]);
    exit;
}

$token = str_replace('Bearer ', '', $authHeader);
try {
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    $user_id = $decoded->sub ?? null;
    if (!$user_id) {
        echo json_encode(["success" => false, "message" => "Invalid token (missing user_id)"]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Token error: " . $e->getMessage()]);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

// // Ensure user_id is sent
// if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
//     echo json_encode(["success" => false, "message" => "Missing user_id"]);
//     exit;
// }

// $user_id = intval($_POST['user_id']);

// Validate user exists, is active and verified
$query = "SELECT * FROM USERS WHERE user_id = ? AND is_verified = 1 AND is_active = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "User not found or not verified/active"]);
    exit;
}

$user = $result->fetch_assoc();

// Directory to save uploads
$upload_dir = "uploads/profile_images/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$profile_path = null;
$id_path = null;

// Handle profile_image upload
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $profile_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
    $profile_name = 'profile_' . $user_id . '_' . time() . '.' . $profile_ext;
    $profile_path = $upload_dir . $profile_name;
    move_uploaded_file($_FILES['profile_image']['tmp_name'], $profile_path);
}

// Handle id_image upload
if (isset($_FILES['id_image']) && $_FILES['id_image']['error'] === UPLOAD_ERR_OK) {
    $id_ext = pathinfo($_FILES['id_image']['name'], PATHINFO_EXTENSION);
    $id_name = 'id_' . $user_id . '_' . time() . '.' . $id_ext;
    // Save id_image in uploads/id_images/ for separation
    $id_upload_dir = "uploads/id_images/";
    if (!is_dir($id_upload_dir)) {
        mkdir($id_upload_dir, 0755, true);
    }
    $id_path = $id_upload_dir . $id_name;
    move_uploaded_file($_FILES['id_image']['tmp_name'], $id_path);
}

// Update DB
$update = $conn->prepare("UPDATE USERS SET profile_image = ?, ID_images = ?, updated_at = NOW() WHERE user_id = ?");
$update->bind_param("ssi", $profile_path, $id_path, $user_id);

if ($update->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Images uploaded and paths saved",
        "profile_image" => $profile_path,
        "id_image" => $id_path
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to update user record"
    ]);
}
?>
