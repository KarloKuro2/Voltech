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
    $quantity = intval($_POST['quantity']);
    $price = floatval($_POST['price']);
    $total = $quantity * $price;
    $project_id = intval($_GET['id']);
    mysqli_query($con, "UPDATE project_add_materials SET quantity='$quantity', total='$total' WHERE id='$row_id' AND project_id='$project_id'");
    header("Location: project_details.php?id=$project_id");
    exit();
} 