<?php
// Update Equipment Days Used
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project_equipment_days'])) {
    $row_id = intval($_POST['row_id']);
    $days_used = intval($_POST['days_used']);
    $project_id = intval($_GET['id']);
    // Get price and depreciation from equipment
    $query = mysqli_query($con, "SELECT e.equipment_price, e.depreciation FROM project_add_equipment pae LEFT JOIN equipment e ON pae.equipment_id = e.id WHERE pae.id='$row_id' AND pae.project_id='$project_id'");
    $data = mysqli_fetch_assoc($query);
    $price = floatval($data['equipment_price']);
    $depreciation = floatval($data['depreciation']);
    $depreciation_per_day = ($depreciation > 0) ? $price / ($depreciation * 365) : 0;
    $total = $depreciation_per_day * $days_used;
    mysqli_query($con, "UPDATE project_add_equipment SET days_used='$days_used', total='$total' WHERE id='$row_id' AND project_id='$project_id'");
    header("Location: project_details.php?id=$project_id");
    exit();
}
// Update Employee Quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project_employee_qty'])) {
    $row_id = intval($_POST['row_id']);
    $quantity = intval($_POST['quantity']);
    $rate = floatval($_POST['rate']);
    $total = $quantity * $rate;
    $project_id = intval($_GET['id']);
    mysqli_query($con, "UPDATE project_add_employee SET quantity='$quantity', total='$total' WHERE id='$row_id' AND project_id='$project_id'");
    header("Location: project_details.php?id=$project_id");
    exit();
}
// Update Employee Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project_employee_schedule'])) {
    $row_id = intval($_POST['row_id']);
    $schedule = intval($_POST['schedule']);
    $rate = floatval($_POST['rate']);
    $total = $schedule * $rate;
    $project_id = intval($_GET['id']);
    mysqli_query($con, "UPDATE project_add_employee SET schedule='$schedule', total='$total' WHERE id='$row_id' AND project_id='$project_id'");
    header("Location: project_details.php?id=$project_id");
    exit();
}
// Update Employee Days
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project_employee_days'])) {
    $row_id = intval($_POST['row_id']);
    $new_days = intval($_POST['days']);
    $project_id = intval($_GET['id']);
    // Get current values
    $current_query = mysqli_query($con, "SELECT days, schedule, daily_rate FROM project_add_employee WHERE id='$row_id' AND project_id='$project_id'");
    $current_data = mysqli_fetch_assoc($current_query);
    $current_days = intval($current_data['days']);
    $current_schedule = intval($current_data['schedule']);
    $daily_rate = floatval($current_data['daily_rate']);
    // Calculate the difference
    $days_difference = $new_days - $current_days;
    $new_schedule = $current_schedule + $days_difference;
    // Update both days and schedule
    $new_total = $daily_rate * $new_schedule;
    mysqli_query($con, "UPDATE project_add_employee SET days='$new_days', schedule='$new_schedule', total='$new_total' WHERE id='$row_id' AND project_id='$project_id'");
    header("Location: project_details.php?id=$project_id");
    exit();
}
// Update Material Quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project_material_qty'])) {
    $row_id = intval($_POST['row_id']);
    $new_quantity = intval($_POST['quantity']);
    $price = floatval($_POST['price']);
    $project_id = intval($_GET['id']);

    // 1. Get old quantity and material_id
    $res = mysqli_query($con, "SELECT quantity, material_id FROM project_add_materials WHERE id='$row_id' AND project_id='$project_id' LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    $old_quantity = intval($row['quantity']);
    $material_id = intval($row['material_id']);

    // 2. Compute difference
    $diff = $new_quantity - $old_quantity;
    if ($diff != 0) {
        // 3. Update main materials table
        // If diff > 0, subtract from inventory; if diff < 0, add to inventory
        mysqli_query($con, "UPDATE materials SET quantity = quantity - ($diff) WHERE id = '$material_id'");
        // 4. Update used_slots in the warehouse
        $loc_res = mysqli_query($con, "SELECT location FROM materials WHERE id = '$material_id' LIMIT 1");
        $loc_row = mysqli_fetch_assoc($loc_res);
        $warehouse = mysqli_real_escape_string($con, $loc_row['location']);
        if ($diff > 0) {
            // Tinaasan: bawas sa used_slots
            mysqli_query($con, "UPDATE warehouses SET used_slots = used_slots - $diff WHERE warehouse = '$warehouse'");
            error_log("[WAREHOUSE LOG] Increased project qty for material_id=$material_id, diff=$diff, warehouse='$warehouse' (used_slots -$diff)");
        } else {
            // Binawasan: dagdag sa used_slots
            $add_back = abs($diff);
            mysqli_query($con, "UPDATE warehouses SET used_slots = used_slots + $add_back WHERE warehouse = '$warehouse'");
            error_log("[WAREHOUSE LOG] Decreased project qty for material_id=$material_id, diff=$diff, warehouse='$warehouse' (used_slots +$add_back)");
        }
    }

    // 5. Update project_add_materials
    $total = $new_quantity * $price;
    mysqli_query($con, "UPDATE project_add_materials SET quantity='$new_quantity', total='$total' WHERE id='$row_id' AND project_id='$project_id'");

    header("Location: project_details.php?id=$project_id");
    exit();
} 