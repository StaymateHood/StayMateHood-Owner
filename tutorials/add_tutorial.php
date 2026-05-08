<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once '../dbconn.php';

$response = ['success' => false];

// Validate required fields
if (!isset($_POST['title'], $_POST['content'], $_POST['user_type'])) {
    $response['message'] = 'Missing required fields or video file';
    echo json_encode($response);
    exit;
}

#$tutorial_id = intval($_POST['tutorial_id']);
$title = $_POST['title'];
$content = $_POST['content'];
$user_type = $_POST['user_type'];
$created_at = date('Y-m-d H:i:s');
$updated_at = $created_at;

// Handle video file upload
$upload_dir = 'uploads/videos/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// $video = $_FILES['video'];
// $video_name = basename($video['name']);
// $video_ext = strtolower(pathinfo($video_name, PATHINFO_EXTENSION));
// $allowed_extensions = ['mp4', 'avi', 'mov', 'mkv'];

// if (!in_array($video_ext, $allowed_extensions)) {
//     $response['message'] = 'Invalid video format';
//     echo json_encode($response);
//     exit;
// }

//$video_path = $upload_dir . uniqid('video_', true) . '.' . $video_ext;
// $video_path = "/video/";

// if (!move_uploaded_file($video['tmp_name'], $video_path)) {
//     $response['message'] = 'Failed to upload video';
//     echo json_encode($response);
//     exit;
// }

// Save video path (not full URL)
$sql = "INSERT INTO TUTORIALS (title, content, video_url, user_type, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    $response['message'] = 'Prepare failed: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$video_path = null;

$stmt->bind_param("ssssss", $title, $content, $video_path, $user_type, $created_at, $updated_at);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Tutorial added successfully';
    $response['video_url'] = $video_path;
} else {
    $response['message'] = 'Insert failed: ' . $stmt->error;
}

echo json_encode($response);
?>
