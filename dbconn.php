<?php
// $servername = "pmpframe.com";
$servername = "localhost";
$username = "staymate_dev";
$password = "M?xOMiMGy=.,Q0uJ";
$database = "staymate_dev";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        "success" => false,
        "message" => "Database connection failed: " . $conn->connect_error
    ]));
}

date_default_timezone_set('Asia/Kolkata');
?>
