<?php
include '../dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $admin_id = $_GET['admin_id'];

    // Fetch total expenses
    $expense_result = $conn->query("SELECT SUM(amount) AS total FROM split_expenses WHERE admin_id = $admin_id");

    // Fetch number of mates
    $mate_result = $conn->query("SELECT COUNT(*) AS count FROM split_mates WHERE admin_id = $admin_id");

    // Fetch all mates list
    $mates_list_result = $conn->query("SELECT id, name, phone FROM split_mates WHERE admin_id = $admin_id");

    // Fetch all expenses list
    $expenses_list_result = $conn->query("SELECT id, type, description, amount, date FROM split_expenses WHERE admin_id = $admin_id");

    if ($expense_result && $mate_result && $mates_list_result && $expenses_list_result) {
        $expense_data = $expense_result->fetch_assoc();
        $mate_data = $mate_result->fetch_assoc();

        $total = $expense_data['total'] ?? 0;
        $count = $mate_data['count'] ?? 0;
        $share = $count > 0 ? round($total / $count, 2) : 0;

        // Format mates list
        $mates = [];
        while ($row = $mates_list_result->fetch_assoc()) {
            $mates[] = $row;
        }

        // Format expenses list
        $expenses = [];
        while ($row = $expenses_list_result->fetch_assoc()) {
            $expenses[] = $row;
        }

        echo json_encode([
            "success" => true,
            "total_expense" => $total,
            "number_of_mates" => $count,
            "split_per_mate" => $share,
            "mates" => $mates,
            "expenses" => $expenses
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Could not calculate split."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>
