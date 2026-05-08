<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include '../env.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only GET requests are supported.'
    ]);
    exit;
}

// Use sandbox or production URL based on $development_mode
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

// Get query parameters
$beneficiary_id = isset($_GET['beneficiary_id']) ? trim($_GET['beneficiary_id']) : '';
$bank_account_number = isset($_GET['bank_account_number']) ? trim($_GET['bank_account_number']) : '';
$bank_ifsc = isset($_GET['bank_ifsc']) ? trim($_GET['bank_ifsc']) : '';

// Validate input - either beneficiary_id OR both bank_account_number and bank_ifsc must be provided
if (empty($beneficiary_id) && (empty($bank_account_number) || empty($bank_ifsc))) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Either beneficiary_id OR both bank_account_number and bank_ifsc must be provided.'
    ]);
    exit;
}

// If both bank_account_number and bank_ifsc are provided, validate them
if (!empty($bank_account_number) && !empty($bank_ifsc)) {
    if (strlen($bank_account_number) < 4 || strlen($bank_account_number) > 25) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Bank account number must be between 4 and 25 characters.'
        ]);
        exit;
    }
    
    if (strlen($bank_ifsc) !== 11) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Bank IFSC must be exactly 11 characters.'
        ]);
        exit;
    }
}

// If beneficiary_id is provided, validate its length
if (!empty($beneficiary_id) && strlen($beneficiary_id) > 50) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Beneficiary ID must not exceed 50 characters.'
    ]);
    exit;
}

// Build query string
$query_params = [];
if (!empty($beneficiary_id)) {
    $query_params['beneficiary_id'] = $beneficiary_id;
}
if (!empty($bank_account_number)) {
    $query_params['bank_account_number'] = $bank_account_number;
}
if (!empty($bank_ifsc)) {
    $query_params['bank_ifsc'] = $bank_ifsc;
}

if (!empty($query_params)) {
    $url .= '?' . http_build_query($query_params);
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
        CURLOPT_CUSTOMREQUEST => "GET",
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
            'message' => 'Beneficiary details retrieved successfully.',
            'request_id' => $request_id,
            'data' => $responseData
        ]);
    } else {
        // Error response from Cashfree
        echo json_encode([
            'success' => false,
            'message' => 'Failed to retrieve beneficiary details.',
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