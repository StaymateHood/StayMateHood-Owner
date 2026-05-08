<?php
header('Content-Type: application/json');
require_once '../dbconn.php';
$response = ['success' => false];

// Define all possible fields
$all_fields = ['contact_us', 'chat_with_us', 'support_email', 'question', 'answer'];

// Check if at least one field is provided
$has_fields = false;
foreach ($all_fields as $field) {
    if (isset($_POST[$field]) && !empty($_POST[$field])) {
        $has_fields = true;
        break;
    }
}

if (!$has_fields) {
    $response['message'] = 'At least one field is required';
    echo json_encode($response);
    exit;
}

// Build query dynamically based on provided fields
$field_names = [];
$placeholders = [];
$param_values = [];
$param_types = '';

foreach ($all_fields as $field) {
    $field_names[] = $field;
    
    if (isset($_POST[$field]) && !empty($_POST[$field])) {
        $placeholders[] = '?';
        $param_values[] = $_POST[$field];
        $param_types .= 's'; // assuming all are strings
    } else {
        $placeholders[] = 'NULL';
    }
}

$sql = "INSERT INTO SUPPORT (" . implode(', ', $field_names) . ") VALUES (" . implode(', ', $placeholders) . ")";

// Only prepare with parameters if we have any
if (!empty($param_values)) {
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $response['message'] = 'Prepare failed: ' . $conn->error;
        echo json_encode($response);
        exit;
    }
    
    if (!empty($param_types)) {
        $stmt->bind_param($param_types, ...$param_values);
    }
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Support details added';
    } else {
        $response['message'] = 'Insert failed: ' . $stmt->error;
    }
} else {
    // In case all fields are NULL (shouldn't happen due to earlier check)
    $result = $conn->query($sql);
    if ($result) {
        $response['success'] = true;
        $response['message'] = 'Support details added with NULL values';
    } else {
        $response['message'] = 'Insert failed: ' . $conn->error;
    }
}

echo json_encode($response);
?>