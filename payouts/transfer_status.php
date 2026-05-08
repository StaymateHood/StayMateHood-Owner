<?php
header('Content-Type: application/json');
include '../env.php';

// Accept query params from GET or JSON body (for flexibility)
$input = $_GET;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = json_decode(file_get_contents('php://input'), true);
    if (is_array($json)) {
        $input = array_merge($input, $json);
    }
}


// Accept transfer_id and cf_transfer_id for standard transfer status
$transfer_id = isset($input['transfer_id']) ? $input['transfer_id'] : null;
$cf_transfer_id = isset($input['cf_transfer_id']) ? $input['cf_transfer_id'] : null;

if (!$transfer_id && !$cf_transfer_id) {
    echo json_encode([
        'success' => false,
        'message' => 'At least one of transfer_id or cf_transfer_id is required.'
    ]);
    exit;
}

$clientId = isset($Client_ID) ? $Client_ID : '';
$clientSecret = isset($Client_Secret) ? $Client_Secret : '';
if (empty($clientId) || empty($clientSecret)) {
    echo json_encode([
        'success' => false,
        'message' => 'Cashfree payout credentials missing in env.php.'
    ]);
    exit;
}

// Use sandbox or production URL based on $development_mode
$url = (isset($development_mode) && $development_mode)
    ? 'https://sandbox.cashfree.com/payout/transfers'
    : 'https://api.cashfree.com/payout/transfers';

// Build query string
$query = [];
if ($transfer_id) $query['transfer_id'] = $transfer_id;
if ($cf_transfer_id) $query['cf_transfer_id'] = $cf_transfer_id;
if (!empty($query)) {
    $url .= '?' . http_build_query($query);
}

// Build headers
$headers = [
    'x-api-version: 2024-01-01',
    'x-client-id: ' . $clientId,
    'x-client-secret: ' . $clientSecret
];
// Optional: x-request-id and x-cf-signature from frontend
if (!empty($input['x-request-id'])) {
    $headers[] = 'x-request-id: ' . $input['x-request-id'];
}
if (!empty($input['x-cf-signature'])) {
    $headers[] = 'x-cf-signature: ' . $input['x-cf-signature'];
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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
?><?php















?>