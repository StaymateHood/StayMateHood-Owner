<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");

require_once '../dbconn.php';
require_once '../token.php'; // JWT already handled

$response = ["success" => false];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (!isset($_GET['property_id'])) {
        echo json_encode(["success" => false, "message" => "Missing property_id"]);
        exit;
    }

    $property_id = intval($_GET['property_id']); 
    $page        = intval($_GET['page'] ?? 1);
    $limit       = intval($_GET['limit'] ?? 5000);
    $offset      = ($page - 1) * $limit;

    /* -------------------------------------------
       MAIN PAYMENT LIST QUERY
    ------------------------------------------- */
    $sql = "
       SELECT 
    b.booking_id,
    b.user_id,
    u.name AS user_name,
    r.room_number,

    p.payment_id,
    p.amount,
    p.payment_status,
    p.transaction_id,
    p.currency,
    p.payment_method,
    p.payment_date,
    p.payment_type,
    p.cycle_id

FROM BOOKINGS b
LEFT JOIN PAYMENTS p 
    ON p.booking_id = b.booking_id
    AND p.payment_status = 'Completed'

LEFT JOIN ROOM r 
    ON r.room_id = b.room_id

LEFT JOIN USERS u 
    ON u.user_id = b.user_id

WHERE b.property_id = ?
ORDER BY b.booking_id DESC
LIMIT ? OFFSET ?;
; ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "Failed to prepare SQL"]);
        exit;
    }

    $stmt->bind_param("iii", $property_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $payments = [];

    while ($row = $result->fetch_assoc()) {

        $cycle_details = null;
        $unpaid_cycle_details = null;

        /* -------------------------------------------
           FETCH RENT CYCLE DETAILS (NEW SYSTEM)
        ------------------------------------------- */
        if (!empty($row['cycle_id'])) {

            $csql = "
                SELECT 
                    cycle_id,
                    start_date,
                    end_date,
                    amount_due,
                    rent_status
                FROM BOOKING_RENT_CYCLE
                WHERE cycle_id = ?
            ";

            $cstmt = $conn->prepare($csql);
            $cstmt->bind_param("i", $row['cycle_id']);
            $cstmt->execute();
            $cres = $cstmt->get_result();
            $cycle_details = $cres->fetch_assoc();
            $cstmt->close();
        }

        if (!empty($row['user_id'])) {

        $csql = "
            SELECT 
                cycle_id,
                start_date,
                end_date,
                amount_due,
                rent_status
            FROM BOOKING_RENT_CYCLE
            WHERE booking_id = ? AND rent_status IN ('Pending', 'Partially Paid')
        ";

        $cstmt = $conn->prepare($csql);
        $cstmt->bind_param("i", $row['booking_id']);
        $cstmt->execute();
        $cres = $cstmt->get_result();

        $unpaid_cycle_details = $cres->fetch_all(MYSQLI_ASSOC);
        $cstmt->close();

        }


        
        /* -------------------------------------------
           PUSH PAYMENT INTO FINAL ARRAY
        ------------------------------------------- */
        $payments[] = [
            "payment_id"     => $row["payment_id"],
            "user_id"        => $row["user_id"],
            "user_name"      => $row["user_name"],
            "booking_id"     => $row["booking_id"],
            "room_number"    => $row["room_number"],
            "amount"         => $row["amount"],
            "payment_status" => $row["payment_status"],
            "transaction_id" => $row["transaction_id"],
            "currency"       => $row["currency"],
            "payment_method" => $row["payment_method"],
            "payment_date"   => $row["payment_date"],

            // ✔ Same key as old system → safe for frontend
            "payment_type"   => $row["payment_type"],

            // ✔ Updated cycle details from new system
            "cycle_details"  => $cycle_details,
            "unpaid_cycle_details"  => $unpaid_cycle_details
        ];
    }

    $response["success"] = true;
    $response["data"] = $payments;

    echo json_encode($response);
    exit;

} else {

    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

?>
