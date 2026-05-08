<?php
header('Content-Type: application/json');
include '../dbconn.php';
require_once '../vendor/autoload.php';
require '../env.php'; // Must define $secret_key

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function normalizePhone($number) {
    // Remove all non-digit characters
    $number = preg_replace('/\D/', '', $number);

    // Remove country code (91) if present
    if (strlen($number) == 12 && substr($number, 0, 2) == '91') {
        $number = substr($number, 2);
    }

    return $number;
}




// Reusable Upload Helper (from register.php)
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

$response = ["success" => false];

// JWT Authentication
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Authorization token missing"]);
    exit;
}
$token = str_replace('Bearer ', '', $authHeader);
try {
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    $user_id = $decoded->sub ?? null;
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Invalid token (missing user_id)"]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Token error: " . $e->getMessage()]);
    exit;
}

$stmt_phone = $conn->prepare("SELECT phone FROM USERS WHERE user_id = ?");
$stmt_phone->bind_param('i', $user_id);
$stmt_phone->execute();
$stmt_phone->bind_result($user_phone);
$stmt_phone->fetch();
$stmt_phone->close();

// Only allow these fields to be updated
$fields = [
    'name', 'email', 'relationship', 'food_preference', 'occupation',
    'emergency_contact', 'emergency_contact_name', 'emergency_contact_relation'
];

$set_clauses = [];
$params = [];
$types = '';
$errors = [];

// Handle non-file fields with sanitization and validation
foreach ($fields as $field) {
    if (isset($_POST[$field])) {
        $value = sanitize($_POST[$field]);
        // Field-specific validation
        if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
            continue;
        }
        $set_clauses[] = "$field = ?";
        $params[] = $value;
        $types .= 's';
    }
}

if (isset($_POST['emergency_contact'])) {
    $emergency_contact = sanitize($_POST['emergency_contact']);

    $normalized_user_phone = normalizePhone($user_phone);
    $normalized_emergency = normalizePhone($emergency_contact);

    if ($normalized_user_phone === $normalized_emergency) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Emergency contact number cannot be same as user's phone number"
        ]);
        exit;
    }
}


// Handle profile_image upload
[$ok, $profile_image] = uploadImageFile('profile_image', 'profile_images');
if (!$ok) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $profile_image]);
    exit;
}
if ($profile_image !== null) {
    // Delete previous image if exists and not default
    $stmt_img = $conn->prepare("SELECT profile_image FROM USERS WHERE user_id = ?");
    $stmt_img->bind_param('i', $user_id);
    $stmt_img->execute();
    $stmt_img->bind_result($current_image);
    $stmt_img->fetch();
    $stmt_img->close();
    $set_clauses[] = "profile_image = ?";
    $params[] = $profile_image;
    $types .= 's';
    if (!empty($current_image) && strpos($current_image, 'default') === false) {
        $old_path = '../' . ltrim($current_image, '/');
        if (file_exists($old_path) && $old_path !== '../' . $profile_image) {
            @unlink($old_path);
        }
    }
}

// Handle aadhaar_card upload
[$ok, $aadhaar_card] = uploadImageFile('aadhaar_card', 'aadhaar_cards');
if (!$ok) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $aadhaar_card]);
    exit;
}
if ($aadhaar_card !== null) {
    $stmt_aadhaar = $conn->prepare("SELECT aadhaar_card FROM USERS WHERE user_id = ?");
    $stmt_aadhaar->bind_param('i', $user_id);
    $stmt_aadhaar->execute();
    $stmt_aadhaar->bind_result($current_aadhaar);
    $stmt_aadhaar->fetch();
    $stmt_aadhaar->close();
    $set_clauses[] = "aadhaar_card = ?";
    $params[] = $aadhaar_card;
    $types .= 's';
    if (!empty($current_aadhaar)) {
        $old_path = '../' . ltrim($current_aadhaar, '/');
        if (file_exists($old_path) && $old_path !== '../' . $aadhaar_card) {
            @unlink($old_path);
        }
    }
}

// Handle pan_card upload
[$ok, $pan_card] = uploadImageFile('pan_card', 'pan_cards');
if (!$ok) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $pan_card]);
    exit;
}
if ($pan_card !== null) {
    $stmt_pan = $conn->prepare("SELECT pan_card FROM USERS WHERE user_id = ?");
    $stmt_pan->bind_param('i', $user_id);
    $stmt_pan->execute();
    $stmt_pan->bind_result($current_pan);
    $stmt_pan->fetch();
    $stmt_pan->close();
    $set_clauses[] = "pan_card = ?";
    $params[] = $pan_card;
    $types .= 's';
    if (!empty($current_pan)) {
        $old_path = '../' . ltrim($current_pan, '/');
        if (file_exists($old_path) && $old_path !== '../' . $pan_card) {
            @unlink($old_path);
        }
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(["success" => false, "errors" => $errors]);
    exit;
}

if (empty($set_clauses)) {
    http_response_code(400);
    $response['message'] = 'No valid fields to update';
    echo json_encode($response);
    exit;
}

// Always update updated_at
$set_clauses[] = "updated_at = NOW()";

$sql = "UPDATE USERS SET " . implode(', ', $set_clauses) . " WHERE user_id = ?";
$params[] = $user_id;
$types .= 'i';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    $response['message'] = 'Prepare failed: ' . $conn->error;
    echo json_encode($response);
    exit;
}
$stmt->bind_param($types, ...$params);
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // Set is_details_updated=1 after successful update
        $stmt2 = $conn->prepare("UPDATE USERS SET is_details_updated = 1 WHERE user_id = ?");
        $stmt2->bind_param('i', $user_id);
        $stmt2->execute();
        $stmt2->close();
        http_response_code(200);
        $response['success'] = true;
        $response['message'] = 'Profile updated successfully';
    } else {
        $response['message'] = 'No changes made or user not found';
    }
} else {
    http_response_code(500);
    $response['message'] = 'Execution failed: ' . $stmt->error;
}
echo json_encode($response);
$conn->close();
?>
