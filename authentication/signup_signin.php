<?php
header('Content-Type: application/json');
include '../dbconn.php';

// Get form data
$phone = $_POST['phone'] ?? '';
$account_type = $_POST['account_type'] ?? '';

// Validate required fields
if (empty($phone) || empty($account_type)) {
    echo json_encode([
        "success" => false,
        "message" => "Phone number and account type are required."
    ]);
    exit;
}

// Check if user exists with that phone number
$user_stmt = $conn->prepare("SELECT user_id, user_type FROM USERS WHERE phone = ? LIMIT 1");
$user_stmt->bind_param("s", $phone);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows > 0) {
    // User exists - check if user_type matches account_type
    $user_row = $user_result->fetch_assoc();
    $user_id = $user_row['user_id'];
    $user_type = $user_row['user_type'];
    
    if ($user_type !== $account_type) {
        echo json_encode([
            "success" => false,
            "message" => "Account type mismatch. User is registered as " . $user_type . " but trying to login as " . $account_type . "."
        ]);
        exit;
    }
} else {
    // User doesn't exist - create new user
    $insert_user_sql = "INSERT INTO USERS (phone, user_type, is_active, created_at) VALUES (?, ?, 1, NOW())";
    $insert_user_stmt = $conn->prepare($insert_user_sql);
    $insert_user_stmt->bind_param("ss", $phone, $account_type);
    
    if ($insert_user_stmt->execute()) {
        $user_id = $conn->insert_id;
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Failed to create user: " . $conn->error
        ]);
        exit;
    }
}

// Generate OTP (6 digits)
$otp = rand(100000, 999999);
$otp_str = strval($otp); // Ensure OTP is a string before using it

// Set expiry time (10 minutes from now)
$created_at = date("Y-m-d H:i:s");
$expires_at = date("Y-m-d H:i:s", strtotime("+10 minutes"));
// Insert into OTP_VERIFICATION_PHONE (user_id is now guaranteed to exist)
$insert_sql = "INSERT INTO OTP_VERIFICATION_PHONE (user_id, phone, otp, created_at, expires_at, is_verified) VALUES (?, ?, ?, ?, ?, 0)";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("issss", $user_id, $phone, $otp_str, $created_at, $expires_at);

if ($insert_stmt->execute()) {
    // Prepare WhatsApp payload
    // $whatsapp_payload = [
    //     "to" => $phone,
    //     "phoneNoId" => "775402075647549",
    //     "type" => "template",
    //     "name" => "staymatehood1",
    //     "language" => "en",
    //     "bodyParams" => [
    //         $otp_str
    //     ],
    //     "buttons" => [
    //         [
    //             "type" => "button",
    //             "sub_type" => "url",
    //             "text" => $otp_str
    //         ]
    //     ]
    // ];

    // $ch = curl_init("https://app.veblika.com/api/v2/whatsapp-business/messages");
    // curl_setopt($ch, CURLOPT_POST, 1);
    // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($whatsapp_payload));
    // curl_setopt($ch, CURLOPT_HTTPHEADER, [
    //     'Content-Type: application/json',
    //     'Authorization: Bearer 69edb57c3038d718470a4a18c3d10cb6a8a96b7d184c877c696941b37b6293ef',
    //     'API_KEY: 69edb57c3038d718470a4a18c3d10cb6a8a96b7d184c877c696941b37b6293ef',
    //     'Accept: application/json',
    //     'Expect:' // avoid 100-continue delays
    // ]);
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12); // seconds to establish connection
    // curl_setopt($ch, CURLOPT_TIMEOUT, 12);       // max total time for the request
    // if (defined('CURL_IPRESOLVE_V4')) {
    //     curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // avoid IPv6 DNS delays
    // }
    // if (defined('CURL_HTTP_VERSION_1_1')) {
    //     curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    // }
    // $whatsapp_response = curl_exec($ch);
    // $curl_error = curl_error($ch);
    // curl_close($ch);

    echo json_encode([
        "success" => true,
        "message" => "OTP sent successfully.",
      //  "otp" => $otp,  // Only for testing; in production, remove this!
        "expires_at" => $expires_at,
        "user_id" => $user_id,
        "whatsapp_response" => $whatsapp_response ?? '',  // Debug: see what WhatsApp API returns
        "curl_error" => $curl_error  // Debug: see if there are cURL errors
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to send OTP: " . $conn->error
    ]);
}

$conn->close();

?>