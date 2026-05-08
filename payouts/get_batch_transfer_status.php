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

$batch_transfer_id = isset($input['batch_transfer_id']) ? $input['batch_transfer_id'] : null;
$cf_batch_transfer_id = isset($input['cf_batch_transfer_id']) ? $input['cf_batch_transfer_id'] : null;

if (!$batch_transfer_id && !$cf_batch_transfer_id) {
    echo json_encode([
        'success' => false,
        'message' => 'At least one of batch_transfer_id or cf_batch_transfer_id is required.'
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
    ? 'https://sandbox.cashfree.com/payout/transfers/batch'
    : 'https://api.cashfree.com/payout/transfers/batch';

// Build query string
$query = [];
if ($batch_transfer_id) $query['batch_transfer_id'] = $batch_transfer_id;
if ($cf_batch_transfer_id) $query['cf_batch_transfer_id'] = $cf_batch_transfer_id;
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