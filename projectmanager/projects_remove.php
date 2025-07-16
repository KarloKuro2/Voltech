<?php
// Remove Employee from Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_project_employee'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);
    mysqli_query($con, "DELETE FROM project_add_employee WHERE id='$row_id' AND project_id='$project_id'");
    header("Location: project_details.php?id=$project_id&empdeleted=1");
    exit();
}
// Remove Material from Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_project_material'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);
    mysqli_query($con, "DELETE FROM project_add_materials WHERE id='$row_id' AND project_id='$project_id'");
    header("Location: project_details.php?id=$project_id&matdeleted=1");
    exit();
}
// Return Material from Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_project_material'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);
    // Get the material_id and quantity from project_add_materials
    $mat_query = mysqli_query($con, "SELECT material_id, quantity FROM project_add_materials WHERE id='$row_id' AND project_id='$project_id'");
    if ($mat_row = mysqli_fetch_assoc($mat_query)) {
        $material_id = intval($mat_row['material_id']);
        $quantity = intval($mat_row['quantity']);
        // Add back the quantity to the main materials table
        mysqli_query($con, "UPDATE materials SET quantity = quantity + $quantity WHERE id = '$material_id'");
    }
    // Remove the material from the project
    mysqli_query($con, "DELETE FROM project_add_materials WHERE id='$row_id' AND project_id='$project_id'");
    header("Location: project_details.php?id=$project_id&matreturned=1");
    exit();
}
// Return Equipment from Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_project_equipment'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);
    // Get equipment_id from project_add_equipment
    $result = mysqli_query($con, "SELECT equipment_id FROM project_add_equipment WHERE id='$row_id' AND project_id='$project_id'");
    $row = mysqli_fetch_assoc($result);
    $equipment_id = $row ? intval($row['equipment_id']) : 0;
    // Set status in project_add_equipment to 'returned'
    mysqli_query($con, "UPDATE project_add_equipment SET status='returned' WHERE id='$row_id' AND project_id='$project_id'");
    // Set status in equipment to 'Available' and set return_time
    $now = date('Y-m-d H:i:s');
    if ($equipment_id) {
        mysqli_query($con, "UPDATE equipment SET status='Available', return_time='$now' WHERE id='$equipment_id'");
    }
    header("Location: project_details.php?id=$project_id&equipreturned=1");
    exit();
}
// Mark Equipment as Damaged from Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_equipment'])) {
    $row_id = intval($_POST['report_row_id']);
    $project_id = intval($_GET['id']);
    // Get equipment_id from project_add_equipment
    $result = mysqli_query($con, "SELECT equipment_id FROM project_add_equipment WHERE id='$row_id' AND project_id='$project_id'");
    $row = mysqli_fetch_assoc($result);
    $equipment_id = $row ? intval($row['equipment_id']) : 0;
    // Set status in project_add_equipment to 'damage'
    mysqli_query($con, "UPDATE project_add_equipment SET status='damage' WHERE id='$row_id' AND project_id='$project_id'");
    // Set status in equipment to 'Damage' and set return_time
    $now = date('Y-m-d H:i:s');
    if ($equipment_id) {
        mysqli_query($con, "UPDATE equipment SET status='Damage', return_time='$now' WHERE id='$equipment_id'");
        // Insert into equipment_reports
        $remarks = 'Damage Equipment';
        $report_time = $now;
        mysqli_query(
            $con,
            "INSERT INTO equipment_reports (equipment_id, project_id, remarks, report_time)
             VALUES ('$equipment_id', '$project_id', '$remarks', '$report_time')"
        );
    }
    header("Location: project_details.php?id=$project_id&equipdamaged=1");
    exit();
} 