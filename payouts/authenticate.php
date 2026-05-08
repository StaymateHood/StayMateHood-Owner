<?php
header('Content-Type: application/json');
include '../env.php';

// Use sandbox or production URL based on $development_mode
$url = (isset($development_mode) && $development_mode)
    ? "https://payout-gamma.cashfree.com/payout/v1/authorize"
    : "https://payout-api.cashfree.com/payout/v1/authorize";

$clientId = isset($Client_ID) ? $Client_ID : '';
$clientSecret = isset($Client_Secret) ? $Client_Secret : '';

if (empty($clientId) || empty($clientSecret)) {
    echo json_encode([
        'success' => false,
        'message' => 'Cashfree payout credentials missing in env.php.'
    ]);
    exit;
}

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_HTTPHEADER => [
        "X-Client-Id: $clientId",
        "X-Client-Secret: $clientSecret"
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($err) {
    echo json_encode([
        'success' => false,
        'message' => 'cURL Error: ' . $err
    ]);
} elseif ($http_code !== 200) {
    echo json_encode([
        'success' => false,
        'message' => 'Cashfree API error',
        'http_code' => $http_code,
        'response' => $response
    ]);
} else {
    echo $response;
}
?>