<?php
header('Content-Type: application/json');
include '../dbconn.php';

// Get form data
$user_id = $_POST['user_id'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$otp = $_POST['otp'] ?? '';
$verification_type = $_POST['verification_type'] ?? ''; // email or phone

// Validate input
// if (empty($user_id) || empty($new_password) || empty($otp) || empty($verification_type)) {
//     echo json_encode([
//         "success" => false,
//         "message" => "user_id, new_password, otp, and verification_type are required."
//     ]);
//     exit;
// }

// Support static OTP "200" for testing
$otp_verified = false;

if ($otp === "200") {
    $otp_verified = true;
} else {
    // Verify OTP from database
    $sql = "SELECT * FROM OTP_VERIFICATION 
            WHERE user_id = ? AND otp = ? AND verification_type = ? AND is_verified = 1 
            ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $otp, $verification_type);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $otp_verified = true;
    }
}

if (!$otp_verified) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid or unverified OTP."
    ]);
    exit;
}

// Hash new password
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Update user's password
$update_sql = "UPDATE USERS SET password_hash = ?, updated_at = NOW() WHERE user_id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("si", $password_hash, $user_id);

if ($update_stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Password reset successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to reset password: " . $conn->error
    ]);
}

$conn->close();
?>
