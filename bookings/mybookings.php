<?php
header("Content-Type: application/json");
include '../notifications/save_notification.php'; // includes JWT decoding + $conn + $user_id

$response = ["success" => false];

try {
    // Fetch all bookings for this user
    $sql = "SELECT 
                B.booking_id,
                B.property_id,
                B.room_id,
                B.start_date,
                B.end_date,
                B.booking_status,
                B.booking_type,
                B.total_amount,
                B.security_deposit,
                B.notice_period_days,
                P.name AS property_name,
                P.address,
                P.property_type,
                P.Maintenance_amount,
                P.refundable_amount,
                R.room_number
            FROM BOOKINGS B
            JOIN PROPERTY P ON B.property_id = P.property_id
            LEFT JOIN ROOM R ON B.room_id = R.room_id
            WHERE B.user_id = ?
            ORDER BY B.start_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $active_bookings = [];
    $completed_bookings = [];
    $cancelled_bookings = [];

    $today = date('Y-m-d');

    while ($row = $result->fetch_assoc()) {
        $booking_id = (int)$row['booking_id'];

        // Auto-complete expired bookings
        if ($row['booking_status'] === 'Active' && !empty($row['end_date']) && $row['end_date'] < $today) {
            $upd = $conn->prepare("UPDATE BOOKINGS SET booking_status = 'Completed' WHERE booking_id = ?");
            $upd->bind_param("i", $booking_id);
            $upd->execute();
            $upd->close();
            $row['booking_status'] = 'Completed';
        }

        // ----- Rent Cycle Summary -----
        $next_due_date = null;
        $total_due_amount = 0.0;
        $rent_status = 'Paid';

        $cycle_sql = "SELECT cycle_id, start_date, end_date, amount_due ,total_amount , next_due_date
                      FROM BOOKING_RENT_CYCLE 
                      WHERE booking_id = ?
                      ORDER BY start_date ASC";
        $cstmt = $conn->prepare($cycle_sql);
        $cstmt->bind_param("i", $booking_id);
        $cstmt->execute();
        $cycles = $cstmt->get_result();

        while ($cycle = $cycles->fetch_assoc()) {
            $cycle_id = (int)$cycle['cycle_id'];
            $total_amount = (float)$cycle['total_amount'];

            // Total amount paid for this cycle
            $pay_sql = "SELECT COALESCE(SUM(amount),0) AS paid 
                        FROM PAYMENTS 
                        WHERE cycle_id = ? AND payment_status = 'Completed'";
            $pstmt = $conn->prepare($pay_sql);
            $pstmt->bind_param("i", $cycle_id);
            $pstmt->execute();
            $paid_row = $pstmt->get_result()->fetch_assoc();
            $amount_paid = (float)($paid_row['paid'] ?? 0.0);

            $remaining = round(max(0, $total_amount - $amount_paid), 2);

            if ($remaining > 0) {
                $total_due_amount += $remaining;

                // Use next unpaid cycle's start_date as "next due date"
                $next_due_date = $cycle['next_due_date'];
                // Determine rent status
                if ($amount_paid > 0 && $amount_paid < $total_amount) {
                    $rent_status = 'Partial';
                } else {
                    if ($rent_status !== 'Partial') $rent_status = 'Due';
                }
            }
        }

        // ----- Booking Data -----
        $booking_data = [
            "booking_id" => $booking_id,
            "property_name" => $row['property_name'],
            "property_type" => $row['property_type'],
            "address" => $row['address'],
            "room_number" => $row['room_number'],
            "checkin" => $row['start_date'],
            "checkout" => $row['end_date'],
            "booking_status" => $row['booking_status'],
            "booking_type" => $row['booking_type'],
            "total_amount" => (float)$row['total_amount'],
            "security_deposit" => (float)$row['security_deposit'],
            "refundable_amount" => (float)$row['refundable_amount'],
            "notice_period_days" => $row['notice_period_days'],
            "maintenance" => (float)$row['Maintenance_amount'],
            "rent_status" => $rent_status,
            "total_due_amount" => (float)round($total_due_amount, 2),
            "next_due_date" => $next_due_date
        ];

        // Categorize booking
        switch ($row['booking_status']) {
            case 'Completed':
                $completed_bookings[] = $booking_data;
                break;
            case 'Cancelled':
                $cancelled_bookings[] = $booking_data;
                break;
            default:
                $active_bookings[] = $booking_data;
                break;
        }
    }

    // ----- Final Response -----
    $response = [
        "success" => true,
        "counts" => [
            "active" => count($active_bookings),
            "completed" => count($completed_bookings),
            "cancelled" => count($cancelled_bookings)
        ],
        "active_bookings" => $active_bookings,
        "completed_bookings" => $completed_bookings,
        "cancelled_bookings" => $cancelled_bookings
    ];

} catch (Exception $e) {
    $response = [
        "success" => false,
        "message" => "Server error: " . $e->getMessage()
    ];
}

echo json_encode($response);
?>
