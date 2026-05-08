<?php
header("Content-Type: application/json");
include '../dbconn.php';
include '../token.php';

$response = ["success" => false];

try {
    if (empty($user_id)) {
        throw new Exception("Unauthorized user.");
    }

    // Fetch user details
    $stmt = $conn->prepare("SELECT user_id, name, email, phone FROM USERS WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("User not found.");
    }
    
    $user = $result->fetch_assoc();
    
    // Generate unique QR data
    $qr_data = json_encode([
        'user_id' => $user['user_id'],
        'name' => $user['name'],
        'timestamp' => time()
    ]);
    
    // Generate QR code URL using Google Charts API
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_data);
    
    $response["success"] = true;
    $response["message"] = "QR code generated successfully.";
    $response["data"] = [
        'qr_code_url' => $qr_url,
        // 'qr_data' => $qr_data,
        'user_id' => $user['user_id']
    ];

} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>
