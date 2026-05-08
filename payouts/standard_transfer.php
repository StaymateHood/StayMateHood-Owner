<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include '../env.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only POST requests are supported.'
    ]);
    exit;
}

// Get JSON body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload.'
    ]);
    exit;
}

// Validate required fields
$transfer_id = $data['transfer_id'] ?? null;
$transfer_amount = isset($data['transfer_amount']) ? floatval($data['transfer_amount']) : null;
$beneficiary_details = $data['beneficiary_details'] ?? null;

if (empty($transfer_id) || $transfer_amount === null || empty($beneficiary_details['beneficiary_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'transfer_id, transfer_amount, and beneficiary_details.beneficiary_id are required.'
    ]);
    exit;
}

$clientId = isset($Client_ID) ? $Client_ID : '';
$clientSecret = isset($Client_Secret) ? $Client_Secret : '';

if (empty($clientId) || empty($clientSecret)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Cashfree payout credentials missing in env.php.'
    ]);
    exit;
}

// Use sandbox or production URL based on $development_mode
$base_url = (isset($development_mode) && $development_mode)
    ? 'https://sandbox.cashfree.com/payout'
    : 'https://api.cashfree.com/payout';
$url = $base_url . '/transfers';

// Build comprehensive payload supporting all possible fields
$payload = [
    'transfer_id' => $transfer_id,
    'transfer_amount' => $transfer_amount,
    'beneficiary_details' => $beneficiary_details
];

// Add optional fields if provided
if (isset($data['transfer_mode'])) {
    $payload['transfer_mode'] = $data['transfer_mode'];
}
if (isset($data['transfer_note'])) {
    $payload['transfer_note'] = $data['transfer_note'];
}
if (isset($data['transfer_tags'])) {
    $payload['transfer_tags'] = $data['transfer_tags'];
}

// Generate request ID for tracking
$request_id = 'REQ_' . uniqid() . '_' . time();

try {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "x-client-id: " . $clientId,
            "x-client-secret: " . $clientSecret,
            "x-api-version: 2024-01-01",
            "x-request-id: " . $request_id,
            "Content-Type: application/json",
            "Accept: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        throw new Exception("cURL Error: " . $err);
    }

    $responseData = json_decode($response, true);

    if ($httpCode === 200) {
        // Success response
        echo json_encode([
            'success' => true,
            'message' => 'Transfer initiated successfully.',
            'request_id' => $request_id,
            'data' => $responseData
        ]);
    } else {
        // Error response from Cashfree
        http_response_code($httpCode);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to initiate transfer.',
            'request_id' => $request_id,
            'http_code' => $httpCode,
            'error' => $responseData
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'request_id' => $request_id
    ]);
}
?>