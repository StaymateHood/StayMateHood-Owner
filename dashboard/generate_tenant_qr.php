<?php
header("Content-Type: application/json");
include '../dbconn.php';
include '../token.php';

$response = ["success" => false];

try {
    if (empty($user_id)) {
        throw new Exception("Unauthorized user.");
    }

    $tenant_id = isset($_POST['tenant_id']) ? intval($_POST['tenant_id']) : null;

    if (!$tenant_id) {
        throw new Exception("tenant_id is required.");
    }

    // Fetch ALL tenant data
    $stmt = $conn->prepare("SELECT * FROM USERS WHERE user_type = 'tenant' And user_id = ?");
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Tenant not found.");
    }

    $tenant = $result->fetch_assoc();

    // QR Data (full tenant info)
    $qr_data = json_encode([
        'user_id'        => $tenant['user_id'],
        'name'     => $tenant['name'],
        'email'        => $tenant['email'],
        'phone' => $tenant['phone'],
        'occupation'      => $tenant['occupation'],
        'aadhaar_card'     => $tenant['aadhaar_card'],
        'pan_card'        => $tenant['pan_card'],
        'url_link'    => '',
    ]);

    // Generate QR code URL
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_data);

    $response["success"] = true;
    $response["message"] = "Tenant QR generated successfully.";
    $response["data"] = [
        'qr_code_url' => $qr_url,
        'url_link' => $tenant['url_link']
    ];

} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>