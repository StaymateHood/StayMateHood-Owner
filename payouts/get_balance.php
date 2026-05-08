<?php
header('Content-Type: application/json');
include '../env.php';

// Get token from Authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    echo json_encode([
        'success' => false,
        'message' => 'Authorization token missing or invalid.'
    ]);
    exit;
}

$token = trim(str_replace('Bearer ', '', $authHeader));

// Use sandbox or production URL based on $development_mode
$url = (isset($development_mode) && $development_mode)
    ? 'https://payout-gamma.cashfree.com/payout/v1/getBalance'
    : 'https://payout-api.cashfree.com/payout/v1/getBalance';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$curl_error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curl_error) {
    echo json_encode([
        'success' => false,
        'message' => 'cURL error: ' . $curl_error
    ]);
    exit;
}

if ($http_code !== 200) {
    echo json_encode([
        'success' => false,
        'message' => 'Cashfree API error',
        'http_code' => $http_code,
        'response' => $response
    ]);
    exit;
}

echo $response;
?>