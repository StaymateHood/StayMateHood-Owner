<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../dbconn.php';

$user_id   = $_POST['userid'];
$user_type = $_POST['user_type'];
$medium    = $_POST['medium'];
$mobile    = $_POST['mobile'];
$token     = $_POST['token'];
$dt        = date('Y-m-d H:i:s');

// Check if token exists
$tok_stmt = $conn->prepare("SELECT id FROM PUSH_TOKENS WHERE userid=? AND user_type=? AND medium=?");
$tok_stmt->bind_param("iss", $user_id, $user_type, $medium);
$tok_stmt->execute();
$res = $tok_stmt->get_result();

if($res->num_rows > 0){
    // Update existing token and mobile
    $row = $res->fetch_assoc();
    $upd_stmt = $conn->prepare("UPDATE PUSH_TOKENS SET token=?, mobile=?, updated_at=? WHERE id=?");
    $upd_stmt->bind_param("sssi", $token, $mobile, $dt, $row['id']);
    if($upd_stmt->execute()){
        $rtn = array("success"=>true,"message"=>"Token updated successfully");
    } else {
        $rtn = array("success"=>false,"message"=>"Failed to update token");
    }
} else {
    // Insert new token with mobile
    $ins_stmt = $conn->prepare("INSERT INTO PUSH_TOKENS (userid, user_type, mobile, medium, token, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $ins_stmt->bind_param("isssss", $user_id, $user_type, $mobile, $medium, $token, $dt);
    if($ins_stmt->execute()){
        $rtn = array("success"=>true,"message"=>"Token inserted successfully");
    } else {
        $rtn = array("success"=>false,"message"=>"Failed to insert token");
    }
}

echo json_encode($rtn);
?>
