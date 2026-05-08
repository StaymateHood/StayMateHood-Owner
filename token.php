<?php
require_once 'vendor/autoload.php';
require 'env.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// JWT Authentication
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    echo json_encode(["success" => false, "message" => "Authorization token missing"]);
    exit;
}

$token = str_replace('Bearer ', '', $authHeader);
try {
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    $user_id = $decoded->sub ?? null;

    if (!$user_id) {
        echo json_encode(["success" => false, "message" => "Invalid token (missing user_id)"]);
        exit;
    }
    $_POST['user_id'] = $user_id;
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Token error: " . $e->getMessage()]);
    exit;
}
