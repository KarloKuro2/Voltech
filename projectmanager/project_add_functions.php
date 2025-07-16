<?php
// Add Equipment to Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project_equipment'])) {
  $equipment_id = intval($_POST['equipment_id']);
  $category = mysqli_real_escape_string($con, $_POST['category']);
  $days_used = isset($_POST['days_used']) ? intval($_POST['days_used']) : 0;
  // Fetch price, rental_fee, and depreciation from equipment table
  $eq_res = mysqli_query($con, "SELECT equipment_price, rental_fee, depreciation FROM equipment WHERE id='$equipment_id' LIMIT 1");
  $eq = mysqli_fetch_assoc($eq_res);
  if (strtolower($category) === 'rental' || strtolower($category) === 'rent') {
    $price = $eq['rental_fee'];
    $depreciation = 'None';
  } else {
    $price = $eq['equipment_price'];
    $depreciation = is_numeric($eq['depreciation']) ? intval($eq['depreciation']) : $eq['depreciation'];
  }
  // Straight-line depreciation per day (for total)
  $depreciation_per_day = (is_numeric($depreciation) && $depreciation > 0) ? $price / ($depreciation * 365) : 0;
  $total = $depreciation_per_day * $days_used;
  $project_id = intval($_GET['id']);
  $status = 'Pending';
  $now = date('Y-m-d H:i:s');
  mysqli_query($con, "INSERT INTO project_add_equipment (project_id, equipment_id, category, days_used, total, depreciation, status, price) VALUES ('$project_id', '$equipment_id', '$category', '$days_used', '$total', '$depreciation', '$status', '$price')");
  // Update equipment table status to 'Pending' and set borrow_time
  mysqli_query($con, "UPDATE equipment SET status = 'Pending', borrow_time = '$now' WHERE id = '$equipment_id'");
  header("Location: project_details.php?id=$project_id");
  exit();
}
// Add Employee to Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project_employee'])) {
    $employee_id = intval($_POST['employeeName']);
    $position = mysqli_real_escape_string($con, $_POST['employeePosition']);
    $daily_rate = floatval($_POST['employeeRate']);
    $days = intval($_POST['employeeDays']);
    $schedule = intval($_POST['employeeSchedule']);
    $total = $daily_rate * $schedule;
    $project_id = intval($_GET['id']);
    $sql = "INSERT INTO project_add_employee (project_id, employee_id, position, daily_rate, days, schedule, total) VALUES ('$project_id', '$employee_id', '$position', '$daily_rate', '$days', '$schedule', '$total')";
    mysqli_query($con, $sql);
    header("Location: project_details.php?id=$project_id&addemp=1");
    exit();
}
// Add Material to Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project_material'])) {
    $material_id = intval($_POST['materialName']);
    $material_name = mysqli_real_escape_string($con, $_POST['materialNameText']);
    $unit = mysqli_real_escape_string($con, $_POST['materialUnit']);
    $material_price = floatval($_POST['materialPrice']);
    $quantity = intval($_POST['materialQty']);
    $total = $material_price * $quantity;
    $project_id = intval($_GET['id']);
    $sql = "INSERT INTO project_add_materials (project_id, material_id, material_name, unit, material_price, quantity, total) VALUES ('$project_id', '$material_id', '$material_name', '$unit', '$material_price', '$quantity', '$total')";
    mysqli_query($con, $sql);
    // Subtract the quantity from the main materials table
    mysqli_query($con, "UPDATE materials SET quantity = quantity - $quantity WHERE id = '$material_id'");
    header("Location: project_details.php?id=$project_id&addmat=1");
    exit();
} 