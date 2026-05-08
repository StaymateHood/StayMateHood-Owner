<?php
header("Content-Type: application/json");
include '../dbconn.php';

/* ======================================================
   DATE RANGE (TESTING MODE: One Day = One Month)
====================================================== */

// $today = date("Y-m-d");

// /* TESTING MODE */
// $from_date = date("Y-m-01", strtotime($today));
// $to_date   = date("Y-m-t", strtotime($today));
/* ================= TESTING MODE ================= */
$testing_mode = true; // ðŸ”¥ production me false kar dena

if ($testing_mode) {
    $from_date = date("Y-m-d");
    $to_date   = date("Y-m-d");
} else {
    $from_date = date("Y-m-01");
    $to_date   = date("Y-m-t");
}

$month = date("Y-m");

/* PRODUCTION MODE (Later use this)
$from_date = date("Y-m-01");
$to_date   = date("Y-m-t");
*/


/* ======================================================
   AMOUNT COLLECTED
====================================================== */
if (isset($_POST['action']) && $_POST['action'] == 'amount_collected') {

    $property_id = intval($_POST['property_id'] ?? 0);
    if ($property_id <= 0) {
        echo json_encode(["success"=>false,"message"=>"Invalid property_id"]);
        exit;
    }

    $sql = "
    SELECT 
           GREATEST(0, BRC.paid_amount - BRC.security_amount) AS amount,
           B.rent_per_month, 
           U.name,
           P.payment_date,
           CONCAT('RENT-',BRC.cycle_id) AS transaction_id,
           R.room_number
    FROM BOOKING_RENT_CYCLE BRC
    JOIN BOOKINGS B ON B.booking_id=BRC.booking_id
    JOIN USERS U ON U.user_id=BRC.user_id
    LEFT JOIN PAYMENTS P ON P.payment_id=BRC.payment_id
    LEFT JOIN ROOM R ON R.room_id=B.room_id
    WHERE B.property_id=?
   AND DATE(BRC.created_at) BETWEEN ? AND ?
      AND BRC.rent_status IN ('Paid','Partially Paid')
      AND (BRC.total_amount - BRC.security_amount) > 0
    ORDER BY BRC.start_date DESC
    ";

    $stmt=$conn->prepare($sql);
    $stmt->bind_param("iss",$property_id,$from_date,$to_date);
    $stmt->execute();
    $res=$stmt->get_result();

    $details=[];
    while($row=$res->fetch_assoc()){
        $details[]=[
            "amount"=>(float)$row['amount'],
            "rent_per_month"=>(float)$row['rent_per_month'],
            "user_name"=>$row['name'],
            "payment_date"=>$row['payment_date'],
            "transaction_id"=>$row['transaction_id'],
            "room_number"=>$row['room_number']
        ];
    }

    echo json_encode(["success"=>true,"details"=>$details]);
    exit;
}


/* ======================================================
   AMOUNT OUTSTANDING
====================================================== */
if ($_POST['action']=='amount_outstanding') {

    $property_id=intval($_POST['property_id']??0);

    // Total Outstanding = direct rent sum from BOOKINGS
    $stmt=$conn->prepare("
    SELECT IFNULL(SUM(B.rent_per_month), 0)
    FROM BOOKINGS B
    WHERE B.property_id=?
      AND B.booking_status IN ('Active','Notice Given','Pending Clearance','Approved')
    ");
    $stmt->bind_param("i",$property_id);
    $stmt->execute();
    $stmt->bind_result($total_outstanding);
    $stmt->fetch();
    $stmt->close();

    // Details
    $stmt=$conn->prepare("
    SELECT 
           B.rent_per_month AS amount,
           B.rent_per_month, 
           U.name,
           NULL AS payment_date,
           NULL AS transaction_id,
           R.room_number
    FROM BOOKINGS B
    JOIN USERS U ON U.user_id=B.user_id
    LEFT JOIN ROOM R ON R.room_id=B.room_id
    WHERE B.property_id=?
      AND B.booking_status IN ('Active','Notice Given','Pending Clearance','Approved')
    ORDER BY B.booking_id DESC
    ");

    $stmt->bind_param("i",$property_id);
    $stmt->execute();
    $res=$stmt->get_result();

    $details=[];
    while($r=$res->fetch_assoc()){
        $details[]=[
            "amount"=>(float)$r['amount'],
            "rent_per_month"=>(float)$r['rent_per_month'],
            "user_name"=>$r['name'],
            "payment_date"=>$r['payment_date'],
            "transaction_id"=>$r['transaction_id'],
            "room_number"=>$r['room_number']
        ];
    }

    echo json_encode([
        "success"=>true,
        "total_outstanding"=>$total_outstanding,
        "details"=>$details
    ]);
    exit;
}


/* ======================================================
   AMOUNT PENDING
====================================================== */
if ($_POST['action']=='amount_pending') {

    $property_id=intval($_POST['property_id']??0);

    // Outstanding = direct rent sum from BOOKINGS
    $stmt=$conn->prepare("
    SELECT IFNULL(SUM(B.rent_per_month), 0)
    FROM BOOKINGS B
    WHERE B.property_id=?
      AND B.booking_status IN ('Active','Notice Given','Pending Clearance','Approved')
    ");
    $stmt->bind_param("i",$property_id);
    $stmt->execute();
    $stmt->bind_result($total_outstanding);
    $stmt->fetch();
    $stmt->close();

    // Collected = rent paid today
    $stmt=$conn->prepare("
    SELECT IFNULL(SUM(GREATEST(0, BRC.paid_amount - BRC.security_amount)), 0)
    FROM BOOKING_RENT_CYCLE BRC
    JOIN BOOKINGS B ON B.booking_id=BRC.booking_id
    WHERE B.property_id=?
      AND DATE(BRC.created_at) BETWEEN ? AND ?
      AND BRC.rent_status IN ('Paid','Partially Paid')
    ");
    $stmt->bind_param("iss",$property_id,$from_date,$to_date);
    $stmt->execute();
    $stmt->bind_result($amount_collected);
    $stmt->fetch();
    $stmt->close();

 $stmt=$conn->prepare("
SELECT IFNULL(SUM(
    B.rent_per_month +
    CASE
        WHEN BRC.security_amount > 0 AND BRC.paid_amount < BRC.security_amount
            THEN (BRC.security_amount - BRC.paid_amount)
        ELSE 0
    END
    - GREATEST(0, BRC.paid_amount - BRC.security_amount)
), 0)
FROM BOOKING_RENT_CYCLE BRC
JOIN BOOKINGS B ON B.booking_id=BRC.booking_id
WHERE B.property_id=?
AND DATE(BRC.created_at) BETWEEN ? AND ?
AND BRC.rent_status IN ('Pending','Partially Paid','Overdue')
");

$stmt->bind_param("iss", $property_id, $from_date, $to_date);
$stmt->execute();
$stmt->bind_result($pending_amount);
$stmt->fetch();
$stmt->close();

    $stmt=$conn->prepare("
    SELECT
        B.rent_per_month +
        CASE
            WHEN BRC.security_amount > 0 AND BRC.paid_amount < BRC.security_amount
                THEN (BRC.security_amount - BRC.paid_amount)
            ELSE 0
        END
        - GREATEST(0, BRC.paid_amount - BRC.security_amount) AS pending_amount,
        B.rent_per_month,
        U.name,
        R.room_number
    FROM BOOKING_RENT_CYCLE BRC
    JOIN BOOKINGS B ON B.booking_id=BRC.booking_id
    JOIN USERS U ON U.user_id=BRC.user_id
    LEFT JOIN ROOM R ON R.room_id=B.room_id
    WHERE B.property_id=?
    AND DATE(BRC.created_at) BETWEEN ? AND ?
    AND BRC.rent_status IN ('Pending','Partially Paid','Overdue')
    ");

    $stmt->bind_param("iss", $property_id, $from_date, $to_date);
    $stmt->execute();
    $res=$stmt->get_result();

    $details=[];
    while($r=$res->fetch_assoc()){
        $details[]=[
            "user_name"=>$r['name'],
            "room_number"=>$r['room_number'],
            "rent_per_month"=>(float)$r['rent_per_month'],
            "pending_amount"=>(float)$r['pending_amount']
        ];
    }

    echo json_encode([
        "success"=>true,
        "total_outstanding"=>$total_outstanding,
        "amount_collected"=>$amount_collected,
        "pending_amount"=>$pending_amount,
        "details"=>$details
    ]);
    exit;
}


/* ======================================================
   AMOUNT DEPOSIT (NOT MONTH BASED)
====================================================== */
if ($_POST['action']=='amount_deposit') {

    $property_id=intval($_POST['property_id']??0);

    // Deposit - only show when tenant has fully paid the deposit (paid_amount >= security_amount)
    $stmt = $conn->prepare("
    SELECT IFNULL(SUM(BRC.security_amount), 0)
    FROM BOOKING_RENT_CYCLE AS BRC
    JOIN BOOKINGS AS B ON B.booking_id = BRC.booking_id
    WHERE B.property_id = ?
      AND B.booking_status IN ('Approved','Active','Notice Given','Pending Clearance')
      AND BRC.security_amount > 0
      AND BRC.paid_amount >= BRC.security_amount
    ");
    $stmt->bind_param("i",$property_id);
    $stmt->execute();
    $stmt->bind_result($total_deposit);
    $stmt->fetch();
    $stmt->close();

    $stmt=$conn->prepare("
    SELECT U.name, R.room_number, BRC.security_amount, BRC.start_date
    FROM BOOKING_RENT_CYCLE BRC
    JOIN BOOKINGS B ON B.booking_id=BRC.booking_id
    JOIN USERS U ON U.user_id=BRC.user_id
    LEFT JOIN ROOM R ON R.room_id=B.room_id
    WHERE B.property_id=?
      AND B.booking_status IN ('Approved','Active','Notice Given','Pending Clearance')
      AND BRC.security_amount > 0
      AND BRC.paid_amount >= BRC.security_amount
    ORDER BY BRC.start_date DESC
    ");
    $stmt->bind_param("i",$property_id);
    $stmt->execute();
    $res=$stmt->get_result();

    $details=[];
    while($r=$res->fetch_assoc()){
        $details[]=[
            "user_name"=>$r['name'],
            "room_number"=>$r['room_number'],
            "deposit_paid"=>(float)$r['security_amount'],
            "start_date"=>$r['start_date']
        ];
    }

    echo json_encode([
        "success"=>true,
        "total_deposit"=>$total_deposit,
        "details"=>$details
    ]);
    exit;
}


/* ======================================================
   AMOUNT EXPENSE
====================================================== */
if ($_POST['action']=='amount_expense') {

    $property_id=intval($_POST['property_id']??0);

    $stmt=$conn->prepare("
    SELECT IFNULL(SUM(amount),0)
    FROM EXPENSES
    WHERE property_id=?
      AND DATE(expense_date) BETWEEN ? AND ?
    ");
    $stmt->bind_param("iss",$property_id,$from_date,$to_date);
    $stmt->execute();
    $stmt->bind_result($total_expense);
    $stmt->fetch();
    $stmt->close();

    $stmt=$conn->prepare("
    SELECT e.room_id,r.room_number,e.expense_type,
           e.sub_category,e.description,
           e.amount,e.expense_date,e.receipt_image
    FROM EXPENSES e
    LEFT JOIN ROOM r ON r.room_id=e.room_id
    WHERE e.property_id=?
      AND DATE(e.expense_date) BETWEEN ? AND ?
    ORDER BY e.expense_date DESC
    ");
    $stmt->bind_param("iss",$property_id,$from_date,$to_date);
    $stmt->execute();
    $res=$stmt->get_result();

    $details=[];
    while($r=$res->fetch_assoc()){
        $details[]=[
            "room_id"=>$r['room_id'],
            "room_number"=>$r['room_number'],
            "expense_type"=>$r['expense_type'],
            "sub_category"=>$r['sub_category'],
            "description"=>$r['description'],
            "amount"=>(float)$r['amount'],
            "expense_date"=>$r['expense_date'],
            "receipt_images"=>json_decode($r['receipt_image'],true) ?? []
        ];
    }

    echo json_encode([
        "success"=>true,
        "total_expense"=>$total_expense,
        "details"=>$details
    ]);
    exit;
}
?>