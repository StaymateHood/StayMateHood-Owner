<?php
header('Content-Type: application/json');
include '../env.php';

function log_debug($msg) {
    file_put_contents(__DIR__ . '/verify_debug.log', date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

// Get token from Authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
log_debug('Authorization header: ' . $authHeader);
if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    log_debug('Missing or invalid Authorization header');
    echo json_encode([
        'success' => false,
        'message' => 'Authorization token missing or invalid.'
    ]);
    exit;
}

$token = trim(str_replace('Bearer ', '', $authHeader));
log_debug('Token: ' . $token);

// Use sandbox or production URL based on $development_mode
$url = (isset($development_mode) && $development_mode)
    ? 'https://payout-gamma.cashfree.com/payout/v1/verifyToken'
    : 'https://payout-api.cashfree.com/payout/v1/verifyToken';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$curl_error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

log_debug('cURL error: ' . $curl_error);
log_debug('HTTP code: ' . $http_code);
log_debug('Cashfree response: ' . $response);

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