<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
	exit(0);
}

include '../env.php';

// Only allow DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
	http_response_code(405);
	echo json_encode([
		'success' => false,
		'message' => 'Method not allowed. Only DELETE requests are supported.'
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

// Accept beneficiary_id from query param or JSON body
$beneficiary_id = '';
if (isset($_GET['beneficiary_id'])) {
	$beneficiary_id = trim($_GET['beneficiary_id']);
} else {
	$input = file_get_contents('php://input');
	$data = json_decode($input, true);
	if (isset($data['beneficiary_id'])) {
		$beneficiary_id = trim($data['beneficiary_id']);
	}
}

if (empty($beneficiary_id)) {
	http_response_code(400);
	echo json_encode([
		'success' => false,
		'message' => 'beneficiary_id is required.'
	]);
	exit;
}

// Validate beneficiary_id
if (strlen($beneficiary_id) > 50) {
	http_response_code(400);
	echo json_encode([
		'success' => false,
		'message' => 'beneficiary_id must not exceed 50 characters.'
	]);
	exit;
}

// Build query string
$url .= '?beneficiary_id=' . urlencode($beneficiary_id);

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
		CURLOPT_CUSTOMREQUEST => "DELETE",
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
			'message' => 'Beneficiary deleted successfully.',
			'request_id' => $request_id,
			'data' => $responseData
		]);
	} else {
		// Error response from Cashfree
		echo json_encode([
			'success' => false,
			'message' => 'Failed to delete beneficiary.',
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