<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

include '../dbconn.php';

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
}

$property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;

if ($property_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid property_id']);
    exit();
}

/* ================= TESTING MODE ================= */
$testing_mode = true; // 🔥 production me false kar dena

if ($testing_mode) {
    $from_date = date("Y-m-d");
    $to_date   = date("Y-m-d");
} else {
    $from_date = date("Y-m-01");
    $to_date   = date("Y-m-t");
}

$month = date("Y-m");

/* ================= VALIDATE PROPERTY ================= */
$stmt = $conn->prepare("SELECT property_id FROM PROPERTY WHERE property_id=? LIMIT 1");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows == 0) {
    echo json_encode(['status'=>'error','message'=>'Property not found']);
    exit();
}
$stmt->close();

/* =====================================================
   1️⃣ TOTAL OUTSTANDING (direct rent sum from BOOKINGS)
===================================================== */
$stmt = $conn->prepare("
    SELECT IFNULL(SUM(B.rent_per_month), 0)
    FROM BOOKINGS B
    WHERE B.property_id = ?
      AND B.booking_status IN ('Approved','Active','Notice Given','Pending Clearance')
");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$stmt->bind_result($total_outstanding);
$stmt->fetch();
$stmt->close();

/* =====================================================
   2️⃣ AMOUNT COLLECTED (RENT ONLY)
   Rent is collected only AFTER deposit is fully paid.
   rent_collected = MAX(0, paid_amount - security_amount)
===================================================== */
$stmt = $conn->prepare("
    SELECT IFNULL(SUM(
        GREATEST(0, BRC.paid_amount - BRC.security_amount)
    ),0)
    FROM BOOKING_RENT_CYCLE BRC
    INNER JOIN BOOKINGS B ON B.booking_id = BRC.booking_id
    WHERE B.property_id = ?
      AND DATE(BRC.created_at) BETWEEN ? AND ?
      AND BRC.rent_status IN ('Paid','Partially Paid')
");
$stmt->bind_param("iss", $property_id, $from_date, $to_date);
$stmt->execute();
$stmt->bind_result($amount_collected);
$stmt->fetch();
$stmt->close();

/* =====================================================
   3️⃣ AMOUNT PENDING (FIXED)
   = SUM of row-wise pending (same as detail)
===================================================== */
$stmt = $conn->prepare("
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
INNER JOIN BOOKINGS B ON B.booking_id = BRC.booking_id
WHERE B.property_id = ?
AND DATE(BRC.created_at) BETWEEN ? AND ?
AND BRC.rent_status IN ('Pending','Partially Paid','Overdue')
");

$stmt->bind_param("iss", $property_id, $from_date, $to_date);
$stmt->execute();
$stmt->bind_result($amount_pending);
$stmt->fetch();
$stmt->close();

// $amount_pending = $total_outstanding - $amount_collected;
/* =====================================================
   4️⃣ TOTAL EXPENSE
===================================================== */
$stmt = $conn->prepare("
    SELECT IFNULL(SUM(amount),0)
    FROM EXPENSES
    WHERE property_id = ?
      AND expense_date BETWEEN ? AND ?
");
$stmt->bind_param("iss", $property_id, $from_date, $to_date);
$stmt->execute();
$stmt->bind_result($total_expense);
$stmt->fetch();
$stmt->close();

/* =====================================================
   5️⃣ PROFIT (RENT - EXPENSE)
===================================================== */
$profit = $amount_collected - $total_expense;

/* =====================================================
   6️⃣ TOTAL DEPOSIT (OVERALL - NOT MONTH BASED)
   Only count deposit when tenant has actually paid it fully (paid_amount >= security_amount)
===================================================== */
$stmt = $conn->prepare("
    SELECT IFNULL(SUM(BRC.security_amount),0)
    FROM BOOKING_RENT_CYCLE BRC
    INNER JOIN BOOKINGS B ON B.booking_id = BRC.booking_id
    WHERE B.property_id = ?
      AND B.booking_status IN ('Approved','Active','Notice Given','Pending Clearance')
      AND BRC.paid_amount >= BRC.security_amount
      AND BRC.security_amount > 0
");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$stmt->bind_result($total_deposit);
$stmt->fetch();
$stmt->close();

/* =====================================================
   7️⃣ VISITS & ENQUIRIES
===================================================== */
// $stmt = $conn->prepare("SELECT visits, enquiries FROM PROPERTY WHERE property_id = ?");
// $stmt->bind_param("i", $property_id);
// $stmt->execute();
// $stmt->bind_result($total_visits, $total_enquiry);
// $stmt->fetch();
// $stmt->close();

// Get total visits
$stmt1 = $conn->prepare("SELECT COUNT(*) FROM PROPERTY_VISITS WHERE property_id = ?");
$stmt1->bind_param("i", $property_id);
$stmt1->execute();
$stmt1->bind_result($total_visits);
$stmt1->fetch();
$stmt1->close();

// Get total enquiries
$stmt2 = $conn->prepare("SELECT COUNT(*) FROM inquery_data WHERE property_id = ?");
$stmt2->bind_param("i", $property_id);
$stmt2->execute();
$stmt2->bind_result($total_enquiry);
$stmt2->fetch();
$stmt2->close();

/* =====================================================
   8️⃣ BED DETAILS
===================================================== */
$stmt = $conn->prepare("
    SELECT IFNULL(SUM(capacity),0), IFNULL(SUM(occupied),0)
    FROM ROOM WHERE property_id=?
");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$stmt->bind_result($total_beds, $occupied_beds);
$stmt->fetch();
$stmt->close();

$vacant_beds = $total_beds - $occupied_beds;

/* =====================================================
   9️⃣ TENANT SUMMARY
===================================================== */
$stmt = $conn->prepare("
    SELECT 
        COUNT(*),
        SUM(CASE WHEN booking_status='Notice Given' THEN 1 ELSE 0 END)
    FROM BOOKINGS
    WHERE property_id=?
      AND booking_status IN ('Active','Notice Given','Approved')
");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$stmt->bind_result($total_tenants, $notice_period_tenants);
$stmt->fetch();
$stmt->close();

/* =====================================================
   🔟 PAID / UNPAID TENANTS
===================================================== */
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM BOOKING_RENT_CYCLE BRC
    INNER JOIN BOOKINGS B ON B.booking_id=BRC.booking_id
    WHERE B.property_id=?
      AND BRC.start_date BETWEEN ? AND ?
      AND BRC.rent_status='Paid'
");
$stmt->bind_param("iss",$property_id,$from_date,$to_date);
$stmt->execute();
$stmt->bind_result($paid_tenants);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM BOOKING_RENT_CYCLE BRC
    INNER JOIN BOOKINGS B ON B.booking_id=BRC.booking_id
    WHERE B.property_id=?
      AND BRC.start_date BETWEEN ? AND ?
      AND BRC.rent_status IN ('Pending','Partially Paid','Overdue')
      AND (BRC.total_amount - BRC.security_amount) > 0
");
$stmt->bind_param("iss",$property_id,$from_date,$to_date);
$stmt->execute();
$stmt->bind_result($unpaid_tenants);
$stmt->fetch();
$stmt->close();

/* =====================================================
   1️⃣1️⃣ PENDING REQUESTS
===================================================== */
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM BOOKINGS
    WHERE property_id=? AND booking_status='Pending Approval'
");
$stmt->bind_param("i",$property_id);
$stmt->execute();
$stmt->bind_result($pending_requests);
$stmt->fetch();
$stmt->close();

/* ================= FINAL RESPONSE ================= */
echo json_encode([
    'status' => 'success',
    'property_id' => $property_id,
    'month' => $month,

    'amount_collected' => (int)$amount_collected,
    'amount_pending' => (int)$amount_pending,
    'total_outstanding' => (int)$total_outstanding,
    'deposit' => (int)$total_deposit,
    'total_expense' => (int)$total_expense,
    'profit' => (int)$profit,

    'total_visits' => (int)$total_visits,
    'total_enquiry' => (int)$total_enquiry,

    'total_beds' => (int)$total_beds,
    'occupied_beds' => (int)$occupied_beds,
    'vacant_beds' => (int)$vacant_beds,

    'total_tenants' => (int)$total_tenants,
    'paid_tenants' => (int)$paid_tenants,
    'unpaid_tenants' => (int)$unpaid_tenants,
    'notice_period_tenants' => (int)$notice_period_tenants,
    'pending_requests' => (int)$pending_requests
]);

$conn->close();
?>