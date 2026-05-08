<?php
// Firebase configuration for owner community messages
class FirebaseOwnerCommunity {
    private $firebase_url;
    private $firebase_key;
    
    public function __construct() {
        // Replace with your Firebase Realtime Database URL
        // Format: https://your-project-id-default-rtdb.firebaseio.com/
        $this->firebase_url = getenv('FIREBASE_URL') ?: "https://sand111.firebaseio.com/";
        $this->firebase_key = getenv('FIREBASE_KEY') ?: "AIzaSyC75jVwyAQ_shMjLgZMYmROiJZlm6nUtEE";
    }
    
    public function sendMessage($communityId, $messageData) {
        $url = $this->firebase_url . "owner_community/" . $communityId . "/messages.json";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return [
                'success' => true,
                'response' => json_decode($response, true)
            ];
        }
        
        return [
            'success' => false,
            'error' => $error ?: 'HTTP ' . $httpCode
        ];
    }
    
    public function getMessages($communityId, $limit = 50) {
        $url = $this->firebase_url . "owner_community/" . $communityId . "/messages.json?orderBy=\"timestamp\"&limitToLast=" . $limit;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data) {
                $messages = [];
                foreach ($data as $key => $message) {
                    $message['firebase_key'] = $key;
                    $messages[] = $message;
                }
                return $messages;
            }
        }
        
        return [];
    }
    
    public function updateCommunityLastMessage($communityId, $message, $timestamp, $senderName) {
        $url = $this->firebase_url . "owner_community/" . $communityId . "/last_message.json";
        
        $lastMessageData = [
            'message' => $message,
            'timestamp' => $timestamp,
            'sender_name' => $senderName
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($lastMessageData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
?>