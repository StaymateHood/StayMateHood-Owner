<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once '../vendor/autoload.php';
require_once '../env.php'; // Ensure JWT_SECRET is available

function verifyJWT()
{
    header("Content-Type: application/json");
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;
    // ✅ 1️⃣ Validate Authorization header
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Authorization token missing"]);
        exit;
    }

    $token = $matches[1];

    try {
        // ✅ 2️⃣ Decode and verify token
        $secret_key = "e3ff5f077839c1331b1d893a728246685cb7dba9e3a77bffe7d52eaccf660988";
        $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
        // print_r($decoded);
        // ✅ 3️⃣ Extract payload values
        $user_id = isset($decoded->sub) ? intval($decoded->sub) : null;
        $email = isset($decoded->email) ? $decoded->email : null;
        $user_type = isset($decoded->user_type) ? $decoded->user_type : null;
       
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Invalid token payload"]);
            exit;
        }

        // ✅ 4️⃣ Add extracted data to global request array
        $_REQUEST['user_id'] = $user_id;
        $_REQUEST['email'] = $email;
        $_REQUEST['user_type'] = $user_type;

        // ✅ 5️⃣ Return payload for optional use
        return [
            "user_id" => $user_id,
            "email" => $email,
            "user_type" => $user_type
        ];

    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Unauthorized: " . $e->getMessage()
        ]);
        exit;
    }
}
