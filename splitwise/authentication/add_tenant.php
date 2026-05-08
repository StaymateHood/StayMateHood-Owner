<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
include '../dbconn.php';

// File upload folder
$upload_dir = "../uploads/id_images/";

// Create folder if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Collect form data
$full_name = $_POST['full_name'] ?? '';
$mobile_number = $_POST['mobile_number'] ?? '';
$email = $_POST['email'] ?? '';
$user_type = $_POST['user_type'] ?? '';
$relationship = $_POST['relationship'] ?? '';
$food_preference = $_POST['food_preference'] ?? '';
$occupation = $_POST['occupation'] ?? '';

// Validate required fields
if (empty($full_name) || empty($mobile_number) || empty($email) || empty($user_type)) {
    echo json_encode([
        "success" => false,
        "message" => "full_name, mobile_number, email, and user_type are required."
    ]);
    exit;
}

$image_paths = [];

if (isset($_FILES['ID_images'])) {
    if (is_array($_FILES['ID_images']['name'])) {
        // Multiple files
        $file_count = count($_FILES['ID_images']['name']);

        for ($i = 0; $i < $file_count; $i++) {
            $file_name = basename($_FILES['ID_images']['name'][$i]);
            $file_tmp = $_FILES['ID_images']['tmp_name'][$i];
            $target_file = $upload_dir . time() . "_" . $file_name;

            if (move_uploaded_file($file_tmp, $target_file)) {
                $image_paths[] = $target_file;
            }
        }
    } else {
        // Single file
        $file_name = basename($_FILES['ID_images']['name']);
        $file_tmp = $_FILES['ID_images']['tmp_name'];
        $target_file = $upload_dir . time() . "_" . $file_name;

        if (move_uploaded_file($file_tmp, $target_file)) {
            $image_paths[] = $target_file;
        }
    }
}


// Convert image paths to JSON string
$id_images_json = json_encode($image_paths);

// Insert into USERS table
$insert_sql = "INSERT INTO USERS (name, phone, email, user_type, relationship, food_preference, occupation, ID_images)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("ssssssss", $full_name, $mobile_number, $email, $user_type, $relationship, $food_preference, $occupation, $id_images_json);

if ($insert_stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Tenant added successfully.",
        "user_id" => $insert_stmt->insert_id
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to add tenant: " . $conn->error
    ]);
}

$conn->close();
?>
