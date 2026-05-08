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

// Get JSON body
$input = json_decode(file_get_contents('php://input'), true);
$amount = isset($input['amount']) ? floatval($input['amount']) : null;
$rechargeAccount = $input['rechargeAccount'] ?? null;
$transferId = $input['transferId'] ?? null;

if (!$amount || !$rechargeAccount) {
    echo json_encode([
        'success' => false,
        'message' => 'amount, rechargeAccount are required.'
    ]);
    exit;
}

// Build payload
$payload = [
    'amount' => $amount,
    'rechargeAccount' => $rechargeAccount
];
if (!empty($transferId)) {
    $payload['transferId'] = $transferId;
}

// Use sandbox or production URL based on $development_mode
$url = (isset($development_mode) && $development_mode)
    ? 'https://payout-gamma.cashfree.com/payout/v1/internalTransfer'
    : 'https://payout-api.cashfree.com/payout/v1/internalTransfer';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

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