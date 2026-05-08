<?php
header("Content-Type: application/json");
include '../dbconn.php';
include '../token.php';

$response = ["success" => false];

try {
    if (empty($user_id)) {
        throw new Exception("Unauthorized user.");
    }

    $property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : null;

    if (!$property_id) {
        throw new Exception("property_id is required.");
    }

    // Fetch ALL property data
    $stmt = $conn->prepare("SELECT * FROM PROPERTY WHERE property_id = ?");
    $stmt->bind_param("i", $property_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Property not found.");
    }

    $property = $result->fetch_assoc();

    // QR Data (full property info)
    $qr_data = json_encode([
        'property_id' => $property['property_id'],
        'name'        => $property['name'],
        'address'     => $property['address'],
        'city'        => $property['city'],
        'property_type' => $property['property_type'],
         'latitude'      => $property['latitude'],
        'longitude'     => $property['longitude'],
        'amount'        => $property['rent_per_day'],
        'url_link'    => ''
        ]);

    // Generate QR code URL
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_data);

    $response["success"] = true;
    $response["message"] = "Property QR generated successfully.";
    $response["data"] = [
        'qr_code_url' => $qr_url,
        'url_link' => $property['url_link']
    ];

} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>