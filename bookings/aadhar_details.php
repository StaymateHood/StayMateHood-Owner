<?php
header("Content-Type: application/json");

// Get request data
$data = json_decode(file_get_contents("php://input"), true);
$qrData = $data['qrData'] ?? '';

if (!$qrData) {
    echo json_encode([
        "status" => false,
        "message" => "No QR data received"
    ]);
    exit;
}

try {
    // Step 1: Base64 decode
    $decoded = base64_decode($qrData);

    if (!$decoded) {
        throw new Exception("Base64 decode failed");
    }

    // Step 2: Try multiple decompression methods
    $xml = @gzuncompress($decoded);

    if (!$xml) {
        $xml = @gzinflate($decoded);
    }

    if (!$xml) {
        $xml = @gzdecode($decoded);
    }

    // if (!$xml) {
    //     throw new Exception("Decompression failed (Secure QR)");
    // }
    if (!$xml) {
    echo json_encode([
        "status" => "secure",
        "message" => "Secure Aadhaar QR detected"
    ]);
    exit;
    }

    // Step 3: Parse XML
    $xmlObj = simplexml_load_string($xml);

    if (!$xmlObj) {
        throw new Exception("Invalid XML format");
    }

    $attr = $xmlObj->attributes();

    // Extract data safely
    $response = [
        "status" => true,
        "data" => [
            "name" => (string)($attr['name'] ?? ''),
            "gender" => (string)($attr['gender'] ?? ''),
            "yob" => (string)($attr['yob'] ?? ''),
            "dob" => (string)($attr['dob'] ?? ''),
            "state" => (string)($attr['state'] ?? ''),
            "pincode" => (string)($attr['pc'] ?? ''),
            "address" =>
                (string)($attr['house'] ?? '') . ', ' .
                (string)($attr['street'] ?? '') . ', ' .
                (string)($attr['loc'] ?? '') . ', ' .
                (string)($attr['dist'] ?? '')
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}