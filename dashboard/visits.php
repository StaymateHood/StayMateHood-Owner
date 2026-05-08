<?php
header('Content-Type: application/json');
include '../dbconn.php';

if (isset($_POST['action']) && $_POST['action'] == 'add_visit') {
  function api_response($success, $message, $data = null) {
    $resp = ["success" => $success, "message" => $message];
    if ($data !== null) $resp["data"] = $data;
    echo json_encode($resp);
    exit();
}

if (!$conn) {
    api_response(false, 'Database connection failed');
}

$property_id = $_POST['property_id'] ?? '';
if (!is_numeric($property_id) || intval($property_id) <= 0) {
    api_response(false, 'Invalid property_id');
}
$property_id = intval($property_id);

// Check if property exists
$sql_check = "SELECT property_id FROM PROPERTY WHERE property_id = ?";
$stmt_check = $conn->prepare($sql_check);
if (!$stmt_check) {
    api_response(false, 'Prepare failed: ' . $conn->error);
}
$stmt_check->bind_param("i", $property_id);
$stmt_check->execute();
$stmt_check->store_result();
if ($stmt_check->num_rows === 0) {
    $stmt_check->close();
    api_response(false, 'Property not found');
}
$stmt_check->close();

$user_id = $_POST['user_id'] ?? '';
if (!is_numeric($user_id) || intval($user_id) <= 0) {
    api_response(false, 'Invalid user_id');
}
$user_id = intval($user_id);

$time = $_POST['time'] ?? null;
$note = $_POST['note'] ?? null;
if (!empty($_POST['visited_at'])) {
    $visited_at = date('Y-m-d H:i:s', strtotime($_POST['visited_at']));
} else {
    $visited_at = null;
}

// Check if user already visited this property
$sql_check_visit = "SELECT id FROM PROPERTY_VISITS WHERE user_id = ? AND property_id = ?";
$stmt_check_visit = $conn->prepare($sql_check_visit);
if (!$stmt_check_visit) {
    api_response(false, 'Prepare failed: ' . $conn->error);
}
$stmt_check_visit->bind_param("ii", $user_id, $property_id);
$stmt_check_visit->execute();
$stmt_check_visit->store_result();

if ($stmt_check_visit->num_rows > 0) {
    // Already visited, no increment
    $stmt_check_visit->close();
    api_response(true, 'User already visited, visit not counted again');
}
$stmt_check_visit->close();

// Record the new visit and increment the property visit count
$conn->begin_transaction();

try {
    // $sql_insert = "INSERT INTO PROPERTY_VISITS (user_id, property_id, time, note) VALUES (?, ?, ?, ?)";
    // $stmt_insert = $conn->prepare($sql_insert);
    // $stmt_insert->bind_param("iiss", $user_id, $property_id, $time, $note);
     $sql_insert = "INSERT INTO PROPERTY_VISITS (user_id, property_id, visited_at, time, note) VALUES (?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("iisss", $user_id, $property_id, $visited_at, $time, $note);
    $stmt_insert->execute();
    $stmt_insert->close();

    $sql_update = "UPDATE PROPERTY SET visits = visits + 1 WHERE property_id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("i", $property_id);
    $stmt_update->execute();
    $stmt_update->close();

    $conn->commit();
    api_response(true, 'Visit count incremented');
} catch (Exception $e) {
    $conn->rollback();
    api_response(false, 'Error: ' . $e->getMessage());
}
  
}

if (isset($_POST['action']) && $_POST['action'] === 'list') {

    $property_id = isset($_POST['property_id']) && is_numeric($_POST['property_id']) ? intval($_POST['property_id']) : null;
    $user_id     = isset($_POST['user_id'])     && is_numeric($_POST['user_id'])     ? intval($_POST['user_id'])     : null;

    if (!$property_id && !$user_id) {
        echo json_encode(['success' => false, 'message' => 'Provide property_id or user_id']);
        exit;
    }

    $page   = isset($_POST['page'])  ? (int)$_POST['page']  : 1;
    $limit  = isset($_POST['limit']) ? (int)$_POST['limit'] : 100;
    $offset = ($page - 1) * $limit;

    // Build WHERE clause dynamically
    $where  = [];
    $types  = '';
    $params = [];
    if ($property_id) { $where[] = 'PV.property_id = ?'; $types .= 'i'; $params[] = $property_id; }
    if ($user_id)     { $where[] = 'PV.user_id = ?';     $types .= 'i'; $params[] = $user_id; }
    $whereStr = 'WHERE ' . implode(' AND ', $where);

    // --- Get total count ---
    $totalcount = 0;
    $totalsql = "SELECT COUNT(id) AS totalcount FROM PROPERTY_VISITS PV $whereStr";
    $total = $conn->prepare($totalsql);
    if ($total) {
        $total->bind_param($types, ...$params);
        $total->execute();
        $row = $total->get_result()->fetch_assoc();
        $totalcount = $row['totalcount'] ?? 0;
        $total->close();
    }

    // --- Get paginated data ---
    $visitsql = "SELECT PV.*, U.name, P.name as property_name FROM PROPERTY_VISITS PV
    LEFT JOIN USERS U ON U.user_id = PV.user_id
    LEFT JOIN PROPERTY P ON P.property_id = PV.property_id
    $whereStr ORDER BY PV.id DESC LIMIT ? OFFSET ?";
    $visits = $conn->prepare($visitsql);

    if ($visits) {
        $visits->bind_param($types . 'ii', ...[...$params, $limit, $offset]);
        $visits->execute();
        $visits_data = $visits->get_result();

        $data = [];
        while ($row = $visits_data->fetch_assoc()) {
            $data[] = $row;
        }

        if (!empty($data)) {
            echo json_encode([
                'success' => true,
                'message' => 'Data fetched successfully',
                'count' => count($data),
                'totalcount' => $totalcount,
                'data' => $data
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No data found',
                'count' => 0,
                'totalcount' => $totalcount,
                'data' => []
            ]);
        }

        $visits->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare visits query']);
    }
}


?>