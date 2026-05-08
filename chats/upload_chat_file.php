<?php
header('Content-Type: application/json');
include '../dbconn.php';

// Create uploads directory if not exists
$upload_dir = '../uploads/chat_files/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(["success" => false, "message" => "No file uploaded"]);
    exit;
}

$file = $_FILES['file'];
$chat_id = $_POST['chat_id'] ?? '';
$sender_id = $_POST['sender_id'] ?? '';
$chat_type = $_POST['chat_type'] ?? 'private'; // 'private' or 'group'

if (empty($chat_id) || empty($sender_id)) {
    echo json_encode(["success" => false, "message" => "Chat ID and Sender ID required"]);
    exit;
}

// Validate file
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'video/mp4'];
$max_size = 10 * 1024 * 1024; // 10MB

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(["success" => false, "message" => "File type not allowed"]);
    exit;
}

if ($file['size'] > $max_size) {
    echo json_encode(["success" => false, "message" => "File too large (max 10MB)"]);
    exit;
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '_' . time() . '.' . $extension;
$filepath = $upload_dir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Get file URL
    $file_url = 'http://' . $_SERVER['HTTP_HOST'] . '/NEWAPI/uploads/chat_files/' . $filename;
    
    // Determine message type
    $message_type = 'file';
    if (strpos($file['type'], 'image') !== false) {
        $message_type = 'image';
    } elseif (strpos($file['type'], 'video') !== false) {
        $message_type = 'video';
    }
    
    echo json_encode([
        "success" => true,
        "file_url" => $file_url,
        "file_name" => $file['name'],
        "file_type" => $message_type,
        "file_size" => $file['size'],
        "message" => "File uploaded successfully"
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to upload file"]);
}
?>
