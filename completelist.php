<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'dbconn.php'; 

try {
    if (isset($_POST['action']) && $_POST['action'] == 'state') {
        $getState = $conn->prepare("SELECT ID as id, STATE_OR_UT_CODE as code, STATE_OR_UT_NAME as name 
                                    FROM STATES_AND_UT_ENUM 
                                    ORDER BY STATE_OR_UT_NAME");
        $getState->execute();
        $result = $getState->get_result();

        if ($result && $result->num_rows > 0) {
            $state_data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode([
                'success' => true,
                'message' => 'State data fetched Successfully',
                'data' => $state_data
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'No data found'
            ]);
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] == 'district') {
        $getState = $conn->prepare("select ID as id,DISTRICT_NAME as name from DISTRICTS_DATA order by DISTRICT_NAME");
        $getState->execute();
        $result = $getState->get_result();

        if ($result && $result->num_rows > 0) {
            $state_data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode([
                'success' => true,
                'message' => 'District data fetched Successfully',
                'data' => $state_data
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'No data found'
            ]);
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] == 'district_by_state') {
    $getState = $conn->prepare("SELECT ID as id, DISTRICT_NAME as name 
                                FROM DISTRICTS_DATA 
                                WHERE STATE_CODE = ? 
                                ORDER BY DISTRICT_NAME");
    $getState->bind_param("i", $_POST['state_code']);
    $getState->execute();
    $result = $getState->get_result();

    if ($result && $result->num_rows > 0) {
        $state_data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode([
            'success' => true,
            'message' => 'District data fetched Successfully',
            'data' => $state_data
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'No data found'
        ]);
    }
}

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
