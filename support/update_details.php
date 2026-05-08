<?php
header('Content-Type: application/json');
require_once '../dbconn.php';
$response = ['success' => false];

// Check if support_id is provided
if (!isset($_POST['support_id'])) {
    $response['message'] = 'Missing support_id';
    echo json_encode($response);
    exit;
}

$support_id = intval($_POST['support_id']);

// Define allowed fields to update
$allowed_fields = ['contact_us', 'chat_with_us', 'support_email', 'question', 'answer'];

// Build SET clause dynamically
$set_clauses = [];
$params = [];
$types = '';

foreach ($allowed_fields as $field) {
    if (isset($_POST[$field])) {
        $set_clauses[] = "$field = ?";
        $params[] = $_POST[$field];
        $types .= 's'; // assuming all fields are strings
    }
}

// If no fields to update
if (empty($set_clauses)) {
    $response['message'] = 'No valid fields to update';
    echo json_encode($response);
    exit;
}

// Add updated_at timestamp if your table has this field
// Uncomment the line below if your SUPPORT table has an updated_at column
// $set_clauses[] = "updated_at = NOW()";

// Prepare the SQL query
$sql = "UPDATE SUPPORT SET " . implode(', ', $set_clauses) . " WHERE id = ?";
$params[] = $support_id;
$types .= 'i'; // integer for support_id

// Prepare and execute the statement
$stmt = $conn->prepare($sql);
if (!$stmt) {
    $response['message'] = 'Prepare failed: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Support details updated successfully';
    } else {
        $response['message'] = 'No changes made or support record not found';
    }
} else {
    $response['message'] = 'Update failed: ' . $stmt->error;
}

echo json_encode($response);
?>