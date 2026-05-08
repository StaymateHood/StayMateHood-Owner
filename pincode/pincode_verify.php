<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../dbconn.php';
include "pincode.php";
try{
    $validation = [];$check=0;
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (empty($_POST["pincode"])) {$check=1;$array_row['pincode'] = 'pincode is required';}
        if (empty($_POST["state"])) {$check=1;$array_row['state'] = 'state code is required';}
        if($check==1){
            array_push($validation,$array_row);
            $rtn = array('success'=>false,'message'=>$validation);
            throw new Exception(json_encode($rtn),200);
            exit();
        }
    }
    else{http_response_code(405);exit();}
    if($check==0){
        $val = pincodecheck($_POST["pincode"], $_POST["state"]);
        if ($val['status'] == 1) {
            $rtn = array("success" => true, "message" => 'Pincode validated successfully', 'locations' => $val['data']);
        } elseif ($val['status'] == 0) {
            $rtn = array("success" => false, "message" => 'Invalid Pin Code', 'locations' => $val['data']);
        } elseif ($val['status'] == 2) {
            $rtn = array("success" => false, "message" => 'Pin Code doesn\'t match to state', 'locations' => $val['data']);
        }
        echo json_encode($rtn);
    }
}catch(Exception $e){echo $e->getMessage();}
?>