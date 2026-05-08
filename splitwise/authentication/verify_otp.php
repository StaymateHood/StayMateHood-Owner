<?php
header('Content-Type: application/json');
include '../dbconn.php';

// Get form data
$user_id = $_POST['user_id'] ?? '';
$otp = $_POST['otp'] ?? '';
$verification_type = $_POST['verification_type'] ?? ''; // email or phone

// Validate input
// if (empty($user_id) || empty($otp) || empty($verification_type)) {
//     echo json_encode([
//         "success" => false,
//         "message" => "user_id, otp, and verification_type are required."
//     ]);
//     exit;
// }

// Allow static OTP "2000" for testing
if ($otp === "2000") {
    // Mark all OTPs as verified for this user and type
    $update_sql = "UPDATE OTP_VERIFICATION SET is_verified = 1 WHERE user_id = ? AND verification_type = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("is", $user_id, $verification_type);
    $update_stmt->execute();

    // Optionally mark user as verified
    $verify_user = "UPDATE USERS SET is_verified = 1 WHERE user_id = ?";
    $vu_stmt = $conn->prepare($verify_user);
    $vu_stmt->bind_param("i", $user_id);
    $vu_stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Static OTP verified successfully (testing mode)."
    ]);
    exit;
}

// Verify OTP from database
$sql = "SELECT * FROM OTP_VERIFICATION 
        WHERE user_id = ? AND otp = ? AND verification_type = ? AND is_verified = 0 
        ORDER BY created_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $otp, $verification_type);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid or expired OTP."
    ]);
    exit;
}

// Mark OTP as verified
$row = $result->fetch_assoc();
$update_sql = "UPDATE OTP_VERIFICATION SET is_verified = 1 WHERE verification_id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("i", $row['verification_id']);
$update_stmt->execute();

// Optionally mark user as verified
$verify_user = "UPDATE USERS SET is_verified = 1 WHERE user_id = ?";
$vu_stmt = $conn->prepare($verify_user);
$vu_stmt->bind_param("i", $user_id);
$vu_stmt->execute();

echo json_encode([
    "success" => true,
    "message" => "OTP verified successfully."
]);

$conn->close();
?>
