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

$base_url = (isset($development_mode) && $development_mode)
    ? "https://sandbox.cashfree.com/payout"
    : "https://api.cashfree.com/payout";
$url = $base_url . "/beneficiary";

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

// Get POST body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
$requiredFields = [
    'beneficiary_id',
    'beneficiary_name',
    'beneficiary_instrument_details',
    'beneficiary_contact_details'
];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Missing required field: $field"
        ]);
        exit;
    }
}

// Validate instrument details (bank_account_number and bank_ifsc required, vpa optional)
if (empty($data['beneficiary_instrument_details']['bank_account_number']) ||
    empty($data['beneficiary_instrument_details']['bank_ifsc'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'bank_account_number and bank_ifsc are required in beneficiary_instrument_details.'
    ]);
    exit;
}
// vpa is optional, but if present, must be a string
if (isset($data['beneficiary_instrument_details']['vpa']) && !is_string($data['beneficiary_instrument_details']['vpa'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'vpa must be a string if provided.'
    ]);
    exit;
}

// Validate contact details
$contactFields = [
    'beneficiary_email',
    'beneficiary_phone',
    'beneficiary_country_code',
    'beneficiary_address',
    'beneficiary_city',
    'beneficiary_state',
    'beneficiary_postal_code'
];
foreach ($contactFields as $field) {
    if (empty($data['beneficiary_contact_details'][$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Missing required field in beneficiary_contact_details: $field"
        ]);
        exit;
    }
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
        CURLOPT_POSTFIELDS => json_encode($data),
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

    if ($httpCode === 201) {
        // Success response
        echo json_encode([
            'success' => true,
            'message' => 'Beneficiary created successfully.',
            'request_id' => $request_id,
            'data' => $responseData
        ]);
    } else {
        // Error response from Cashfree
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create beneficiary.',
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