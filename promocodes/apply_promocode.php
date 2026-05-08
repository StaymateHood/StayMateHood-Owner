<?php
header("Content-Type: application/json");
require_once '../dbconn.php';

$response = ["success" => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['code']) || !isset($_POST['booking_amount'])) {
        $response['message'] = 'Missing promocode or booking amount.';
        echo json_encode($response);
        exit;
    }

    $code = $_POST['code'];
    $booking_amount = floatval($_POST['booking_amount']);

    $sql = "SELECT * FROM PROMOCODES WHERE code = ? AND is_active = 1 AND valid_from <= CURDATE() AND valid_to >= CURDATE()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $promo = $result->fetch_assoc();

    if (!$promo) {
        $response['message'] = 'Invalid or expired promocode.';
    } elseif ($promo['current_usage'] >= $promo['usage_limit']) {
        $response['message'] = 'Promocode usage limit reached.';
    } elseif ($booking_amount < $promo['minimum_booking_amount']) {
        $response['message'] = 'Booking amount is less than the minimum required.';
    } else {
        $discount = $promo['discount_amount'];
        $final_amount = $booking_amount - $discount;

        // Optionally update usage count here if you're applying directly:
        $update = $conn->prepare("UPDATE PROMOCODES SET current_usage = current_usage + 1, updated_at = NOW() WHERE promocode_id = ?");
        $update->bind_param("i", $promo['promocode_id']);
        $update->execute();

        $response['success'] = true;
        $response['message'] = 'Promocode applied successfully.';
        $response['discount'] = $discount;
        $response['final_amount'] = $final_amount;
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>
