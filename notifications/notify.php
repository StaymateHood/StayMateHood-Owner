<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require '../firebase-notifications/vendor/autoload.php'; // Ensure you have installed google/apiclient via Composer

use Google\Client;
use Google\Service\FirebaseCloudMessaging;

define('FIREBASE_PROJECT_ID', 'sand111'); // Replace with your Firebase project ID
define('SERVICE_ACCOUNT_JSON', '../firebase-notifications/sand111-firebase-adminsdk-8cfhs-5a1ae6c94d.json'); // Path to your service account JSON file

function sendPushNotification($deviceToken, $title, $body, $data) {
    // Ensure data values are strings
    $data = array_map('strval', $data);

    // Initialize Google Client and authenticate
    $client = new Client();
    $client->setAuthConfig(SERVICE_ACCOUNT_JSON);
    $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

    // Get access token
    $accessToken = $client->fetchAccessTokenWithAssertion()['access_token'];

    // Set up the HTTP request data for the notification
    $url = "https://fcm.googleapis.com/v1/projects/" . FIREBASE_PROJECT_ID . "/messages:send";
    $postData = [
        'message' => [
            'token' => $deviceToken,
            'notification' => [
                'title' => $title,
                'body' => $body  // Including data in the body for visibility
            ],
            'data' => $data // Separate custom data block
        ]
    ];

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ]);

    // Execute cURL request
    $response = curl_exec($ch);
    
    if ($response === FALSE) {
        die('Error: ' . curl_error($ch));
    }

    curl_close($ch);
    return $response;
}
// Example usage
// $deviceToken = 'ehCqxwjrQDGm623E-yfSJE:APA91bH_n9PJpAaph-PsW0YlDN-WKT4XA7f0cffIqbOUbzLbWX3E5ay-uW2fMwcnSPkjgPZ85sU6LexBXsqEDZzM7h67zhcz8Q4be7sfcMsU0-GOvrLjtuI'; // Replace with the actual device token
// $title = "Test Notification";
// $body = "This is a test notification sent via FCM HTTP v1 API.";
// $data = [
//     'example_key' => 'example_value'
// ];

// $response = sendPushNotification($deviceToken, $title, $body, $data);
// echo "Response: " . $response;




?>
