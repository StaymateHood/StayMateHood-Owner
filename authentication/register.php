<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
include '../dbconn.php';

// Reusable Upload Helper
function uploadImageFile($fileKey, $subDir = 'profile_images', $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif']) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return [true, null]; // optional file
    }

    $upload_dir = "../uploads/$subDir/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $tmp_name = $_FILES[$fileKey]['tmp_name'];
    $original_name = basename($_FILES[$fileKey]['name']);
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_extensions)) {
        return [false, "Invalid file type for $fileKey. Allowed: " . implode(', ', $allowed_extensions)];
    }

    $unique_name = uniqid() . "_" . preg_replace("/[^a-zA-Z0-9._-]/", "", $original_name);
    $target_file = $upload_dir . $unique_name;

    if (move_uploaded_file($tmp_name, $target_file)) {
        return [true, str_replace('../', '', $target_file)];
    } else {
        return [false, "Failed to upload $fileKey."];
    }
}

// Collect fields
$name            = trim($_POST['name'] ?? '');
$email           = strtolower(trim($_POST['email'] ?? ''));
$phone           = trim($_POST['phone'] ?? '');
// $password removed for OTP-only registration
$user_type       = trim($_POST['user_type'] ?? '');
$relationship    = trim($_POST['relationship'] ?? '');
$food_preference = trim($_POST['food_preference'] ?? '');
$occupation      = trim($_POST['occupation'] ?? '');

// Validate inputs
$errors = [];

if (empty($name) || strlen($name) < 2) {
    $errors[] = "Name is required and must be at least 2 characters.";
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "A valid email is required.";
}
if (empty($phone) || !preg_match('/^\d{10}$/', $phone)) {
    $errors[] = "Phone is required and must be exactly 10 digits.";
}
// Password validation removed
$allowedTypes = ['tenant', 'owner', 'admin'];
if (empty($user_type) || !in_array($user_type, $allowedTypes)) {
    $errors[] = "User type is required and must be one of: tenant, owner, admin.";
}

if ($user_type !== 'owner') {
    $allowedRelationship = ['single', 'married'];
    if (empty($relationship) || !in_array($relationship, $allowedRelationship)) {
        $errors[] = "Relationship is required and must be either single or married.";
    }
    $allowedFood = ['vegetarian', 'non-vegetarian'];
    if (empty($food_preference) || !in_array($food_preference, $allowedFood)) {
        $errors[] = "Food preference is required and must be either veg or non-veg.";
    }
    $allowedOccupation = ['salaried', 'student', 'other'];
    if (empty($occupation) || !in_array($occupation, $allowedOccupation)) {
        $errors[] = "Occupation is required and must be Salaried, Student or Other.";
    }
}

if (!empty($errors)) {
    echo json_encode(["success" => false, "errors" => $errors]);
    exit;
}

// Check for duplicates
$check_sql = "SELECT user_id FROM USERS WHERE email = ? OR phone = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("ss", $email, $phone);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Email or phone already registered."]);
    exit;
}

// Upload Aadhaar and PAN (required)
[$ok1, $aadhaar_card] = uploadImageFile('aadhaar_card', 'aadhaar_cards');
if (!$ok1) exit(json_encode(["success" => false, "message" => $aadhaar_card]));

[$ok2, $pan_card] = uploadImageFile('pan_card', 'pan_cards');
if (!$ok2) exit(json_encode(["success" => false, "message" => $pan_card]));

// Upload Profile Image (optional)
[$ok3, $profile_image] = uploadImageFile('profile_image', 'profile_images');
if (!$ok3 && $profile_image !== null) {
    exit(json_encode(["success" => false, "message" => $profile_image]));
}
// Password hash removed

// Insert into DB
// Remove password_hash from insert
$insert_sql = "INSERT INTO USERS (
    name, email, phone, profile_image, user_type,
    relationship, food_preference, occupation,
    aadhaar_card, pan_card,
    created_at, updated_at, is_verified, is_active, is_property_added
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 0, 1, 'False')";

$stmt = $conn->prepare($insert_sql);
$stmt->bind_param(
    "ssssssssss",
    $name, $email, $phone, $profile_image, $user_type,
    $relationship, $food_preference, $occupation,
    $aadhaar_card, $pan_card
);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "User registered successfully.",
        "user_id" => $stmt->insert_id
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Registration failed: " . $conn->error
    ]);
}

$conn->close();
?>