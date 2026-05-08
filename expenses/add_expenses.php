<?php
include '../dbconn.php';

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // =============================
    // Read Input Fields
    // =============================
    $property_id   = isset($_POST['property_id']) ? intval($_POST['property_id']) : null;
    $room_id       = isset($_POST['room_id']) ? intval($_POST['room_id']) : null;

    $expense_type  = html_entity_decode(trim($_POST['expense_type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $sub_category  = html_entity_decode(trim($_POST['sub_category'] ?? ''), ENT_QUOTES, 'UTF-8');

    $amount        = isset($_POST['amount']) ? floatval($_POST['amount']) : '';
    $expense_date  = trim($_POST['expense_date'] ?? '');
    $description   = sanitize($_POST['description'] ?? '');

    // =============================
    // Validate Inputs
    // =============================
    $errors = [];

    if (!$property_id || $expense_type === '' || $amount === '' || $expense_date === '') {
        $errors[] = "Required fields are missing.";
    }
    if ($property_id && $property_id <= 0) {
        $errors[] = "Invalid property_id.";
    }
    if ($amount !== '' && $amount < 0) {
        $errors[] = "Amount must be a positive number.";
    }
    if ($expense_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expense_date)) {
        $errors[] = "Expense date must be in YYYY-MM-DD format.";
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(["success" => false, "errors" => $errors]);
        exit;
    }

    // =============================
    // MULTIPLE IMAGE UPLOAD
    // =============================
    $uploaded_images = [];

if (isset($_FILES['receipt_image']) && is_array($_FILES['receipt_image']['name'])) {

    $uploadRelativePath = "../uploads/expenses/";
    $uploadDir = __DIR__ . "/" . $uploadRelativePath;

    // Ensure directory exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Ensure directory is writable
    if (!is_writable($uploadDir)) {
        error_log("Upload directory not writable: $uploadDir");
        echo json_encode(["success" => false, "error" => "Upload directory is not writable"]);
        exit;
    }

    $fileCount = count($_FILES['receipt_image']['name']);

    for ($i = 0; $i < $fileCount; $i++) {

        if ($_FILES['receipt_image']['error'][$i] === UPLOAD_ERR_OK) {

            // Original File Name
            $originalName = basename($_FILES['receipt_image']['name'][$i]);

            // Safe File Name
            $safeName = preg_replace("/[^a-zA-Z0-9._-]/", "_", $originalName);

            // Timestamped file name
            $timestampedName = time() . "_" . $safeName;

            // Full path on server
            $targetFilePath = $uploadDir . $timestampedName;

            // Relative path returned to frontend
            $webPath = $uploadRelativePath . $timestampedName;

            // Move image to upload folder
            if (!empty($_FILES['receipt_image']['tmp_name'][$i])) {

                if (move_uploaded_file($_FILES['receipt_image']['tmp_name'][$i], $targetFilePath)) {

                    $uploaded_images[] = $webPath;
                    error_log("Uploaded: $webPath");

                } else {

                    error_log("Failed moving upload: " . $_FILES['receipt_image']['tmp_name'][$i] . " to $targetFilePath");
                }

            } else {
                error_log("Empty tmp_name index $i");
            }

        } else {
            error_log("Upload error for index $i");
        }
    }

} else {
    // If images are coming in POST (optional fallback)
    $uploaded_images = isset($_POST['receipt_image'])
        ? (array)$_POST['receipt_image']
        : [];

    error_log("Images used from POST fallback");
}


    // Save as JSON array
    $receipt_image_json = json_encode($uploaded_images);

    // =============================
    // Insert Into Database
    // =============================
    $created_at = date('Y-m-d H:i:s');
    $updated_at = $created_at;

    $sql = "
        INSERT INTO EXPENSES 
        (property_id, room_id, expense_type, sub_category, amount, expense_date, description, receipt_image, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param(
        "iissdsssss",
        $property_id,
        $room_id,
        $expense_type,
        $sub_category,
        $amount,
        $expense_date,
        $description,
        $receipt_image_json,
        $created_at,
        $updated_at
    );

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Expense added successfully."]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $stmt->error]);
    }

    $stmt->close();

} else {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Only POST method allowed."
    ]);
}
?>
