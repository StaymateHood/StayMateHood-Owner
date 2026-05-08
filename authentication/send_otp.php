<?php
header('Content-Type: application/json');
include '../dbconn.php';

// Performance timers
$__route_start = microtime(true);
$__timings = [];

// Get form data
$phone = $_POST['phone'] ?? '';
$verification_type = $_POST['verification_type'] ?? '';
$account_type = $_POST['account_type'] ?? '';

// Check user existence and type before OTP logic
$user_stmt = $conn->prepare("SELECT user_id, user_type FROM USERS WHERE phone = ? LIMIT 1");
$user_stmt->bind_param("s", $phone);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
if ($user_result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "User not found."
    ]);
    exit;
}
$user_row = $user_result->fetch_assoc();
$user_id = $user_row['user_id'];
$user_type = $user_row['user_type'];
if (($account_type === 'owner' && $user_type !== 'owner') || ($account_type === 'tenant' && $user_type !== 'tenant')) {
    echo json_encode([
        "success" => false,
        "message" => "Account type mismatch. Please use the correct account type."
    ]);
    exit;
}
$__timings['user_lookup_ms'] = round((microtime(true) - $__route_start) * 1000);

// Generate OTP (6 digits)
$otp = rand(100000, 999999);
$otp_str = strval($otp); // Ensure OTP is a string before using it

// Set expiry time (10 minutes from now)
$created_at = date("Y-m-d H:i:s");
$expires_at = date("Y-m-d H:i:s", strtotime("+10 minutes"));

// Insert into OTP_VERIFICATION_PHONE (with user_id)
$insert_sql = "INSERT INTO OTP_VERIFICATION_PHONE (user_id, phone, otp, created_at, expires_at, is_verified) VALUES (?, ?, ?, ?, ?, 0)";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("issss", $user_id, $phone, $otp_str, $created_at, $expires_at);

if ($insert_stmt->execute()) {
//     $__timings['after_insert_ms'] = round((microtime(true) - $__route_start) * 1000);
//     // Prepare WhatsApp payload
//     $whatsapp_payload = [
//         "to" => $phone,
//         "phoneNoId" => "775402075647549",
//         "type" => "template",
//         "name" => "staymatehood1",
//         "language" => "en",
//         "bodyParams" => [
//             $otp_str
//         ],
//         "buttons" => [
//             [
//                 "type" => "button",
//                 "sub_type" => "url",
//                 "text" => $otp_str
//             ]
//         ]
//     ];

//     $ch = curl_init("https://app.veblika.com/api/v2/whatsapp-business/messages");
//     curl_setopt($ch, CURLOPT_POST, 1);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($whatsapp_payload));
//     curl_setopt($ch, CURLOPT_HTTPHEADER, [
//         'Content-Type: application/json',
//         'Authorization: Bearer 69edb57c3038d718470a4a18c3d10cb6a8a96b7d184c877c696941b37b6293ef',
//         'API_KEY: 69edb57c3038d718470a4a18c3d10cb6a8a96b7d184c877c696941b37b6293ef',
//         'Accept: application/json',
//         'Expect:' // avoid 100-continue delays
//     ]);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     // cURL hardening and timeouts
//     curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12); // seconds to establish connection
//     curl_setopt($ch, CURLOPT_TIMEOUT, 12);       // max total time for the request
//     if (defined('CURL_IPRESOLVE_V4')) {
//         curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // avoid IPv6 DNS delays
//     }
//     if (defined('CURL_HTTP_VERSION_1_1')) {
//         curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
//     }
//     $curl_start = microtime(true);
//     $whatsapp_response = curl_exec($ch);
//     $curl_error = curl_error($ch);
//     $curl_info = curl_getinfo($ch);
//     $curl_total_time = isset($curl_info['total_time']) ? $curl_info['total_time'] : (microtime(true) - $curl_start);
//     curl_close($ch);
//     $__timings['curl_exec_ms'] = round($curl_total_time * 1000);
//     $__timings['total_route_ms'] = round((microtime(true) - $__route_start) * 1000);

//     // Debug output
//     $debug_log = "Timestamp: " . date('c') . "\n"
//         . "Timings(ms): " . json_encode($__timings) . "\n"
//         . "cURL info: " . json_encode($curl_info) . "\n"
//         . "Payload: " . json_encode($whatsapp_payload) . "\n"
//         . "Response: " . $whatsapp_response . "\n"
//         . "CurlError: " . $curl_error . "\n\n";
//     file_put_contents(__DIR__ . '/whatsapp_debug.log', $debug_log, FILE_APPEND);

//     echo json_encode([
//         "success" => true,
//         "message" => "OTP sent successfully.",
//       //  "otp" => $otp,  // Only for testing; in production, remove this!
//         "expires_at" => $expires_at,
//         "user_id" => $user_id,
//         "whatsapp_response" => $whatsapp_response,  // Debug: see what WhatsApp API returns
//         "curl_error" => $curl_error,  // Debug: see if there are cURL errors
//         "timings" => $__timings
//     ]);
// } else {
//     echo json_encode([
//         "success" => false,
//         "message" => "Failed to send OTP: " . $conn->error
//     ]);
}

echo json_encode([
        "success" => true,
        "message" => "OTP sent successfully.",
      //  "otp" => $otp,  // Only for testing; in production, remove this!
        "expires_at" => "",
        "user_id" => $user_id,
        "whatsapp_response" => "",  // Debug: see what WhatsApp API returns
        "curl_error" => "",  // Debug: see if there are cURL errors
        "timings" => ""
    ]);

$conn->close();
?>
