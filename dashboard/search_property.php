
<?php
// header("Content-Type: application/json");
// include '../dbconn.php';

// $query = isset($_POST['query']) ? $conn->real_escape_string($_POST['query']) : '';

// if (empty($query)) {
//     echo json_encode([
//         "success" => false,
//         "message" => "Search query is required."
//     ]);
//     exit;
// }

// // Columns to search
// $searchableColumns = [
//     'name',
//     'address',
//     'city',
//     'state',
//     'zip_code',
//     'property_type',
//     'property_category',
//     'property_sub_category',
//     'tenant_type',
//     'PG_type',
//     'Best_suited_for',
//     'furnished_type',
//     'room_type',
//     'description',
//     'amenities',
//     'rules'
// ];

// // Build dynamic WHERE clause
// $whereClauses = [];
// foreach ($searchableColumns as $column) {
//     $whereClauses[] = "$column LIKE '%$query%'";
// }

// $whereSQL = implode(' OR ', $whereClauses);

// // Final SQL
// $sql = "SELECT * FROM PROPERTY WHERE is_active = 1 AND is_verified = 0 AND ($whereSQL)";

// $result = $conn->query($sql);

// if ($result && $result->num_rows > 0) {
//     $properties = [];
//     while ($row = $result->fetch_assoc()) {
//         $properties[] = $row;
//     }
//     echo json_encode([
//         "success" => true,
//         "message" => "Properties found.",
//         "data" => $properties
//     ]);
// } else {
//     echo json_encode([
//         "success" => false,
//         "message" => "No properties found matching your search."
//     ]);
// }

// $conn->close();



//new 

header("Content-Type: application/json");
include '../dbconn.php';

$query = isset($_POST['query']) ? $conn->real_escape_string($_POST['query']) : '';
$property_type = isset($_POST['property_type']) ? $conn->real_escape_string($_POST['property_type']) : '';
$city = isset($_POST['city']) ? $conn->real_escape_string($_POST['city']) : '';
$min_price = isset($_POST['min_price']) ? floatval($_POST['min_price']) : null;
$max_price = isset($_POST['max_price']) ? floatval($_POST['max_price']) : null;

$latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
$longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
$radius_km = isset($_POST['radius_km']) ? floatval($_POST['radius_km']) : null;
$distance_by = isset($_POST['distance_by']) ? $_POST['distance_by'] : ''; // 'near' or 'far'

$whereClauses = ["is_active = 1", "is_verified = 0"];

// Search query filter
// if (!empty($query)) {
//     $searchableColumns = [
//         'name', 'address', 'city', 'state', 'zip_code', 'property_type',
//         'property_category', 'property_sub_category', 'tenant_type',
//         'PG_type', 'Best_suited_for', 'furnished_type', 'room_type',
//         'description', 'amenities', 'rules'
//     ];
    
//     $searchClauses = [];
//     foreach ($searchableColumns as $column) {
//         $searchClauses[] = "$column LIKE '%$query%'";
//     }
//     $whereClauses[] = "(" . implode(' OR ', $searchClauses) . ")";
// }

// Search query filter (secure way)
if (!empty($query)) {
    $whereClauses[] = "name LIKE '%$query%'";
}

// Property type filter
if (!empty($property_type)) {
    $whereClauses[] = "property_type = '$property_type'";
}

// City filter
if (!empty($city)) {
    $whereClauses[] = "city = '$city'";
}

// Price range filter
if ($min_price !== null) {
    $whereClauses[] = "rent_per_day >= $min_price";
}
if ($max_price !== null) {
    $whereClauses[] = "rent_per_day <= $max_price";
}

$whereSQL = implode(' AND ', $whereClauses);

// $sql = "SELECT * FROM PROPERTY WHERE $whereSQL";


// Build SQL with distance calculation
$selectSQL = "SELECT *, ";
$orderSQL = "";

if ($latitude !== null && $longitude !== null) {
    // Add distance calculation using Haversine formula
    $selectSQL .= "(6371 * acos(cos(radians($latitude)) * cos(radians(latitude)) * cos(radians(longitude) - radians($longitude)) + sin(radians($latitude)) * sin(radians(latitude)))) AS distance_km";
    
    // Add radius filter
    if ($radius_km !== null) {
        $whereClauses[] = "(6371 * acos(cos(radians($latitude)) * cos(radians(latitude)) * cos(radians(longitude) - radians($longitude)) + sin(radians($latitude)) * sin(radians(latitude)))) <= $radius_km";
        $whereSQL = implode(' AND ', $whereClauses);
    }
    
    // Add distance sorting
    if ($distance_by === 'near') {
        $orderSQL = " ORDER BY distance_km ASC";
    } elseif ($distance_by === 'far') {
        $orderSQL = " ORDER BY distance_km DESC";
    }
} else {
    $selectSQL .= "NULL AS distance_km";
}

$sql = "$selectSQL FROM PROPERTY WHERE $whereSQL$orderSQL";


$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $properties = [];
    while ($row = $result->fetch_assoc()) {
        $properties[] = $row;
    }
    echo json_encode([
        "success" => true,
        "message" => "Properties found.",
        "data" => $properties
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "No properties found matching your search."
    ]);
}

$conn->close();
?>
