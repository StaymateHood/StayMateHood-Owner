<?php
header("Content-Type: application/json");
include '../dbconn.php';

/* ======================================================
   HELPER: GET DETAILS FOR AMOUNT COLLECTED
====================================================== */
function getCollectedDetails($conn, $property_id, $year, $month = null) {
    if ($month) {
        $month_num = date('m', strtotime($month));
        $from_date = "$year-$month_num-01";
        $to_date = date("Y-m-t", strtotime($from_date));
    } else {
        $from_date = "$year-01-01";
        $to_date = "$year-12-31";
    }
    
    $stmt = $conn->prepare("
        SELECT BRC.paid_amount AS amount, B.rent_per_month, U.name, R.room_number
        FROM BOOKING_RENT_CYCLE BRC
        JOIN BOOKINGS B ON B.booking_id=BRC.booking_id
        JOIN USERS U ON U.user_id=BRC.user_id
        LEFT JOIN ROOM R ON R.room_id=B.room_id
        WHERE B.property_id=? AND BRC.start_date BETWEEN ? AND ?
          AND BRC.rent_status IN ('Paid','Partially Paid')
        ORDER BY BRC.start_date DESC
    ");
    $stmt->bind_param("iss", $property_id, $from_date, $to_date);
    $stmt->execute();
    $res = $stmt->get_result();
    $details = [];
    while($row = $res->fetch_assoc()) {
        $details[] = [
            "user_name" => $row['name'],
            "room_number" => $row['room_number'],
            "rent_per_month" => (float)$row['rent_per_month'],
            "amount" => (float)$row['amount']
        ];
    }
    return $details;
}

/* ======================================================
   HELPER: GET DETAILS FOR AMOUNT OUTSTANDING
====================================================== */
function getOutstandingDetails($conn, $property_id, $year, $month = null) {
    if ($month) {
        $month_num = date('m', strtotime($month));
        $from_date = "$year-$month_num-01";
        $to_date = date("Y-m-t", strtotime($from_date));
    } else {
        $from_date = "$year-01-01";
        $to_date = "$year-12-31";
    }
    
    $stmt = $conn->prepare("
        SELECT BRC.total_amount AS amount, B.rent_per_month, U.name, R.room_number
        FROM BOOKING_RENT_CYCLE BRC
        JOIN BOOKINGS B ON B.booking_id=BRC.booking_id
        JOIN USERS U ON U.user_id=BRC.user_id
        LEFT JOIN ROOM R ON R.room_id=B.room_id
        WHERE B.property_id=? AND BRC.start_date BETWEEN ? AND ?
          AND B.booking_status IN ('Active','Notice Given','Pending Clearance','Approved')
        ORDER BY BRC.start_date DESC
    ");
    $stmt->bind_param("iss", $property_id, $from_date, $to_date);
    $stmt->execute();
    $res = $stmt->get_result();
    $details = [];
    while($row = $res->fetch_assoc()) {
        $details[] = [
            "user_name" => $row['name'],
            "room_number" => $row['room_number'],
            "rent_per_month" => (float)$row['rent_per_month'],
            "amount" => (float)$row['amount']
        ];
    }
    return $details;
}

/* ======================================================
   HELPER: GET DETAILS FOR AMOUNT PENDING
====================================================== */
function getPendingDetails($conn, $property_id, $year, $month = null) {
    if ($month) {
        $month_num = date('m', strtotime($month));
        $from_date = "$year-$month_num-01";
        $to_date = date("Y-m-t", strtotime($from_date));
    } else {
        $from_date = "$year-01-01";
        $to_date = "$year-12-31";
    }
    
    $stmt = $conn->prepare("
        SELECT BRC.amount_due AS pending_amount, B.rent_per_month, U.name, R.room_number
        FROM BOOKING_RENT_CYCLE BRC
        JOIN BOOKINGS B ON B.booking_id=BRC.booking_id
        JOIN USERS U ON U.user_id=BRC.user_id
        LEFT JOIN ROOM R ON R.room_id=B.room_id
        WHERE B.property_id=? AND BRC.start_date BETWEEN ? AND ?
          AND BRC.rent_status IN ('Pending','Partially Paid','Overdue')
    ");
    $stmt->bind_param("iss", $property_id, $from_date, $to_date);
    $stmt->execute();
    $res = $stmt->get_result();
    $details = [];
    while($row = $res->fetch_assoc()) {
        $details[] = [
            "user_name" => $row['name'],
            "room_number" => $row['room_number'],
            "rent_per_month" => (float)$row['rent_per_month'],
            "amount" => (float)$row['pending_amount']
        ];
    }
    return $details;
}

/* ======================================================
   HELPER: GET DETAILS FOR AMOUNT DEPOSIT
====================================================== */
function getDepositDetails($conn, $property_id, $year, $month = null) {
    if ($month) {
        $month_num = date('m', strtotime($month));
        $from_date = "$year-$month_num-01";
        $to_date = date("Y-m-t", strtotime($from_date));
    } else {
        $from_date = "$year-01-01";
        $to_date = "$year-12-31";
    }
    
    $stmt = $conn->prepare("
        SELECT U.name, R.room_number, BRC.security_amount
        FROM BOOKING_RENT_CYCLE BRC
        JOIN BOOKINGS B ON B.booking_id=BRC.booking_id
        JOIN USERS U ON U.user_id=BRC.user_id
        LEFT JOIN ROOM R ON R.room_id=B.room_id
        WHERE B.property_id=? AND BRC.start_date BETWEEN ? AND ?
          AND B.booking_status IN ('Pending Approval','Approved','Active','Notice Given','Pending Clearance')
          AND BRC.security_amount > 0
        ORDER BY BRC.start_date DESC
    ");
    $stmt->bind_param("iss", $property_id, $from_date, $to_date);
    $stmt->execute();
    $res = $stmt->get_result();
    $details = [];
    while($row = $res->fetch_assoc()) {
        $details[] = [
            "user_name" => $row['name'],
            "room_number" => $row['room_number'],
            "deposit_paid" => (float)$row['security_amount']
        ];
    }
    return $details;
}

/* ======================================================
   HELPER: GET DETAILS FOR AMOUNT EXPENSE
====================================================== */
function getExpenseDetails($conn, $property_id, $year, $month = null) {
    if ($month) {
        $month_num = date('m', strtotime($month));
        $from_date = "$year-$month_num-01";
        $to_date = date("Y-m-t", strtotime($from_date));
    } else {
        $from_date = "$year-01-01";
        $to_date = "$year-12-31";
    }
    
    $stmt = $conn->prepare("
        SELECT e.room_id, r.room_number, e.expense_type, e.sub_category, e.description, e.amount, e.expense_date
        FROM EXPENSES e
        LEFT JOIN ROOM r ON r.room_id=e.room_id
        WHERE e.property_id=? AND DATE(e.expense_date) BETWEEN ? AND ?
        ORDER BY e.expense_date DESC
    ");
    $stmt->bind_param("iss", $property_id, $from_date, $to_date);
    $stmt->execute();
    $res = $stmt->get_result();
    $details = [];
    while($row = $res->fetch_assoc()) {
        $details[] = [
            "room_number" => $row['room_number'],
            "expense_type" => $row['expense_type'],
            "sub_category" => $row['sub_category'],
            "description" => $row['description'],
            "amount" => (float)$row['amount'],
            "expense_date" => $row['expense_date']
        ];
    }
    return $details;
}

/* ======================================================
   AMOUNT COLLECTED CHART
====================================================== */
if ($_POST['action'] == 'amount_collected') {
    
    $property_id = intval($_POST['property_id'] ?? 0);
    $year = intval($_POST['year'] ?? date('Y'));
    $month = $_POST['month'] ?? null;
    $view = $_POST['view'] ?? 'year';
    
    if ($property_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid property_id"]);
        exit;
    }

    $response = ["status" => "success", "view" => $view, "year" => $year];
    $data = [];
    $total = 0;

    if ($view == 'year') {
        for ($m = 1; $m <= 12; $m++) {
            $month_name = date('M', mktime(0, 0, 0, $m, 1));
            $from_date = "$year-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01";
            $to_date = date("Y-m-t", strtotime($from_date));
            
            $stmt = $conn->prepare("
                SELECT IFNULL(SUM(BRC.paid_amount), 0)
                FROM BOOKING_RENT_CYCLE BRC
                JOIN BOOKINGS B ON B.booking_id = BRC.booking_id
                WHERE B.property_id = ?
                  AND BRC.start_date BETWEEN ? AND ?
                  AND BRC.rent_status IN ('Paid', 'Partially Paid')
            ");
            $stmt->bind_param("iss", $property_id, $from_date, $to_date);
            $stmt->execute();
            $stmt->bind_result($amount);
            $stmt->fetch();
            $stmt->close();
            
            $data[] = ["label" => $month_name, "amount" => (float)$amount];
            $total += $amount;
        }
    } else if ($view == 'month') {
        if (!$month) {
            echo json_encode(["status" => "error", "message" => "Month required"]);
            exit;
        }
        
        $month_num = date('m', strtotime($month));
        $from_date = "$year-$month_num-01";
        $days_in_month = date('t', strtotime($from_date));
        
        for ($d = 1; $d <= $days_in_month; $d++) {
            $date = "$year-$month_num-" . str_pad($d, 2, '0', STR_PAD_LEFT);
            
            $stmt = $conn->prepare("
                SELECT IFNULL(SUM(BRC.paid_amount), 0)
                FROM BOOKING_RENT_CYCLE BRC
                JOIN BOOKINGS B ON B.booking_id = BRC.booking_id
                WHERE B.property_id = ?
                  AND BRC.start_date = ?
                  AND BRC.rent_status IN ('Paid', 'Partially Paid')
            ");
            $stmt->bind_param("is", $property_id, $date);
            $stmt->execute();
            $stmt->bind_result($amount);
            $stmt->fetch();
            $stmt->close();
            
            $data[] = ["label" => (string)$d, "amount" => (float)$amount];
            $total += $amount;
        }
        $response['month'] = $month;
    }
    
    $response['data'] = $data;
    $response['total_collected'] = $total;
    $response['transactions'] = getCollectedDetails($conn, $property_id, $year, $month);
    echo json_encode($response);
    exit;
}

/* ======================================================
   AMOUNT OUTSTANDING CHART
====================================================== */
if ($_POST['action'] == 'amount_outstanding') {
    
    $property_id = intval($_POST['property_id'] ?? 0);
    $year = intval($_POST['year'] ?? date('Y'));
    $month = $_POST['month'] ?? null;
    $view = $_POST['view'] ?? 'year';
    
    if ($property_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid property_id"]);
        exit;
    }

    $response = ["status" => "success", "view" => $view, "year" => $year];
    $data = [];
    $total = 0;

    if ($view == 'year') {
        for ($m = 1; $m <= 12; $m++) {
            $month_name = date('M', mktime(0, 0, 0, $m, 1));
            $from_date = "$year-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01";
            $to_date = date("Y-m-t", strtotime($from_date));
            
            $stmt = $conn->prepare("
                SELECT IFNULL(SUM(BRC.total_amount), 0)
                FROM BOOKING_RENT_CYCLE BRC
                JOIN BOOKINGS B ON B.booking_id = BRC.booking_id
                WHERE B.property_id = ?
                  AND BRC.start_date BETWEEN ? AND ?
                  AND B.booking_status IN ('Active','Notice Given','Pending Clearance','Approved')
            ");
            $stmt->bind_param("iss", $property_id, $from_date, $to_date);
            $stmt->execute();
            $stmt->bind_result($amount);
            $stmt->fetch();
            $stmt->close();
            
            $data[] = ["label" => $month_name, "amount" => (float)$amount];
            $total += $amount;
        }
    } else if ($view == 'month') {
        if (!$month) {
            echo json_encode(["status" => "error", "message" => "Month required"]);
            exit;
        }
        
        $month_num = date('m', strtotime($month));
        $from_date = "$year-$month_num-01";
        $days_in_month = date('t', strtotime($from_date));
        
        for ($d = 1; $d <= $days_in_month; $d++) {
            $date = "$year-$month_num-" . str_pad($d, 2, '0', STR_PAD_LEFT);
            
            $stmt = $conn->prepare("
                SELECT IFNULL(SUM(BRC.total_amount), 0)
                FROM BOOKING_RENT_CYCLE BRC
                JOIN BOOKINGS B ON B.booking_id = BRC.booking_id
                WHERE B.property_id = ?
                  AND BRC.start_date = ?
                  AND B.booking_status IN ('Active','Notice Given','Pending Clearance','Approved')
            ");
            $stmt->bind_param("is", $property_id, $date);
            $stmt->execute();
            $stmt->bind_result($amount);
            $stmt->fetch();
            $stmt->close();
            
            $data[] = ["label" => (string)$d, "amount" => (float)$amount];
            $total += $amount;
        }
        $response['month'] = $month;
    }
    
    $response['data'] = $data;
    $response['total_outstanding'] = $total;
    $response['transactions'] = getOutstandingDetails($conn, $property_id, $year, $month);
    echo json_encode($response);
    exit;
}

/* ======================================================
   AMOUNT PENDING CHART
====================================================== */
if ($_POST['action'] == 'amount_pending') {
    
    $property_id = intval($_POST['property_id'] ?? 0);
    $year = intval($_POST['year'] ?? date('Y'));
    $month = $_POST['month'] ?? null;
    $view = $_POST['view'] ?? 'year';
    
    if ($property_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid property_id"]);
        exit;
    }

    $response = ["status" => "success", "view" => $view, "year" => $year];
    $data = [];
    $total = 0;

    if ($view == 'year') {
        for ($m = 1; $m <= 12; $m++) {
            $month_name = date('M', mktime(0, 0, 0, $m, 1));
            $from_date = "$year-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01";
            $to_date = date("Y-m-t", strtotime($from_date));
            
            $stmt = $conn->prepare("
                SELECT IFNULL(SUM(BRC.amount_due), 0)
                FROM BOOKING_RENT_CYCLE BRC
                JOIN BOOKINGS B ON B.booking_id = BRC.booking_id
                WHERE B.property_id = ?
                  AND BRC.start_date BETWEEN ? AND ?
                  AND BRC.rent_status IN ('Pending', 'Partially Paid', 'Overdue')
            ");
            $stmt->bind_param("iss", $property_id, $from_date, $to_date);
            $stmt->execute();
            $stmt->bind_result($amount);
            $stmt->fetch();
            $stmt->close();
            
            $data[] = ["label" => $month_name, "amount" => (float)$amount];
            $total += $amount;
        }
    } else if ($view == 'month') {
        if (!$month) {
            echo json_encode(["status" => "error", "message" => "Month required"]);
            exit;
        }
        
        $month_num = date('m', strtotime($month));
        $from_date = "$year-$month_num-01";
        $days_in_month = date('t', strtotime($from_date));
        
        for ($d = 1; $d <= $days_in_month; $d++) {
            $date = "$year-$month_num-" . str_pad($d, 2, '0', STR_PAD_LEFT);
            
            $stmt = $conn->prepare("
                SELECT IFNULL(SUM(BRC.amount_due), 0)
                FROM BOOKING_RENT_CYCLE BRC
                JOIN BOOKINGS B ON B.booking_id = BRC.booking_id
                WHERE B.property_id = ?
                  AND BRC.start_date = ?
                  AND BRC.rent_status IN ('Pending', 'Partially Paid', 'Overdue')
            ");
            $stmt->bind_param("is", $property_id, $date);
            $stmt->execute();
            $stmt->bind_result($amount);
            $stmt->fetch();
            $stmt->close();
            
            $data[] = ["label" => (string)$d, "amount" => (float)$amount];
            $total += $amount;
        }
        $response['month'] = $month;
    }
    
    $response['data'] = $data;
    $response['total_pending'] = $total;
    $response['transactions'] = getPendingDetails($conn, $property_id, $year, $month);
    echo json_encode($response);
    exit;
}

/* ======================================================
   AMOUNT DEPOSIT CHART
====================================================== */
if ($_POST['action'] == 'amount_deposit') {
    
    $property_id = intval($_POST['property_id'] ?? 0);
    $year = intval($_POST['year'] ?? date('Y'));
    $month = $_POST['month'] ?? null;
    $view = $_POST['view'] ?? 'year';
    
    if ($property_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid property_id"]);
        exit;
    }

    $response = ["status" => "success", "view" => $view, "year" => $year];
    $data = [];
    $total = 0;

    if ($view == 'year') {
        for ($m = 1; $m <= 12; $m++) {
            $month_name = date('M', mktime(0, 0, 0, $m, 1));
            $from_date = "$year-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01";
            $to_date = date("Y-m-t", strtotime($from_date));
            
            $stmt = $conn->prepare("
                SELECT IFNULL(SUM(BRC.security_amount), 0)
                FROM BOOKING_RENT_CYCLE BRC
                JOIN BOOKINGS B ON B.booking_id = BRC.booking_id
                WHERE B.property_id = ?
                  AND BRC.start_date BETWEEN ? AND ?
                  AND B.booking_status IN ('Pending Approval','Approved','Active','Notice Given','Pending Clearance')
                  AND BRC.security_amount > 0
            ");
            $stmt->bind_param("iss", $property_id, $from_date, $to_date);
            $stmt->execute();
            $stmt->bind_result($amount);
            $stmt->fetch();
            $stmt->close();
            
            $data[] = ["label" => $month_name, "amount" => (float)$amount];
            $total += $amount;
        }
    } else if ($view == 'month') {
        if (!$month) {
            echo json_encode(["status" => "error", "message" => "Month required"]);
            exit;
        }
        
        $month_num = date('m', strtotime($month));
        $from_date = "$year-$month_num-01";
        $days_in_month = date('t', strtotime($from_date));
        
        for ($d = 1; $d <= $days_in_month; $d++) {
            $date = "$year-$month_num-" . str_pad($d, 2, '0', STR_PAD_LEFT);
            
            $stmt = $conn->prepare("
                SELECT IFNULL(SUM(BRC.security_amount), 0)
                FROM BOOKING_RENT_CYCLE BRC
                JOIN BOOKINGS B ON B.booking_id = BRC.booking_id
                WHERE B.property_id = ?
                  AND BRC.start_date = ?
                  AND B.booking_status IN ('Pending Approval','Approved','Active','Notice Given','Pending Clearance')
                  AND BRC.security_amount > 0
            ");
            $stmt->bind_param("is", $property_id, $date);
            $stmt->execute();
            $stmt->bind_result($amount);
            $stmt->fetch();
            $stmt->close();
            
            $data[] = ["label" => (string)$d, "amount" => (float)$amount];
            $total += $amount;
        }
        $response['month'] = $month;
    }
    
    $response['data'] = $data;
    $response['total_deposit'] = $total;
    $response['transactions'] = getDepositDetails($conn, $property_id, $year, $month);
    echo json_encode($response);
    exit;
}

/* ======================================================
   AMOUNT EXPENSE CHART
====================================================== */
if ($_POST['action'] == 'amount_expense') {
    
    $property_id = intval($_POST['property_id'] ?? 0);
    $year = intval($_POST['year'] ?? date('Y'));
    $month = $_POST['month'] ?? null;
    $view = $_POST['view'] ?? 'year';
    
    if ($property_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid property_id"]);
        exit;
    }

    $response = ["status" => "success", "view" => $view, "year" => $year];
    $data = [];
    $total = 0;

    if ($view == 'year') {
        for ($m = 1; $m <= 12; $m++) {
            $month_name = date('M', mktime(0, 0, 0, $m, 1));
            $from_date = "$year-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01";
            $to_date = date("Y-m-t", strtotime($from_date));
            
            $stmt = $conn->prepare("
                SELECT IFNULL(SUM(amount), 0)
                FROM EXPENSES
                WHERE property_id = ?
                  AND expense_date BETWEEN ? AND ?
            ");
            $stmt->bind_param("iss", $property_id, $from_date, $to_date);
            $stmt->execute();
            $stmt->bind_result($amount);
            $stmt->fetch();
            $stmt->close();
            
            $data[] = ["label" => $month_name, "amount" => (float)$amount];
            $total += $amount;
        }
    } else if ($view == 'month') {
        if (!$month) {
            echo json_encode(["status" => "error", "message" => "Month required"]);
            exit;
        }
        
        $month_num = date('m', strtotime($month));
        $from_date = "$year-$month_num-01";
        $days_in_month = date('t', strtotime($from_date));
        
        for ($d = 1; $d <= $days_in_month; $d++) {
            $date = "$year-$month_num-" . str_pad($d, 2, '0', STR_PAD_LEFT);
            
            $stmt = $conn->prepare("
                SELECT IFNULL(SUM(amount), 0)
                FROM EXPENSES
                WHERE property_id = ?
                  AND expense_date = ?
            ");
            $stmt->bind_param("is", $property_id, $date);
            $stmt->execute();
            $stmt->bind_result($amount);
            $stmt->fetch();
            $stmt->close();
            
            $data[] = ["label" => (string)$d, "amount" => (float)$amount];
            $total += $amount;
        }
        $response['month'] = $month;
    }
    
    $response['data'] = $data;
    $response['total_expense'] = $total;
    $response['transactions'] = getExpenseDetails($conn, $property_id, $year, $month);
    echo json_encode($response);
    exit;
}



echo json_encode(["status" => "error", "message" => "Invalid action"]);
?>
