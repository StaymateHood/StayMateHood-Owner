<?php
header('Content-Type: application/json');
include '../dbconn.php';

// Get property_id from POST or GET (you can choose one)
$property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;

if ($property_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid property_id']);
    exit();
}

// Fetch expenses
$sql = "SELECT expense_id, expense_type, amount, expense_date, description, receipt_image, created_at, updated_at 
        FROM EXPENSES 
        WHERE property_id = ? 
        ORDER BY expense_date DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $property_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $expenses = [];
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }

    echo json_encode([
        'status' => 'success',
        'property_id' => $property_id,
        'total_expenses' => count($expenses),
        'expenses' => $expenses
    ]);

    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Query preparation failed']);
}

$conn->close();
?>
