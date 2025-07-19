<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header("Location: ../login.php");
    exit();
}
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}
$userid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$user_firstname = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : '';
$user_lastname = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : '';
$user_name = trim($user_firstname . ' ' . $user_lastname);
$current_page = basename($_SERVER['PHP_SELF']);
// Handle AJAX password change (like pm_profile.php) - MUST BE BEFORE ANY OUTPUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $response = ['success' => false, 'message' => ''];
    $current = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    if (!$current || !$new || !$confirm) {
        $response['message'] = 'All fields are required.';
    } elseif ($new !== $confirm) {
        $response['message'] = 'New passwords do not match.';
    } elseif (strlen($new) < 6) {
        $response['message'] = 'New password must be at least 6 characters.';
    } else {
        $user_row = $con->query("SELECT password FROM users WHERE id = '$userid'");
        if ($user_row && $user_row->num_rows > 0) {
            $user_data = $user_row->fetch_assoc();
            if (password_verify($current, $user_data['password'])) {
                $hashed = password_hash($new, PASSWORD_DEFAULT);
                $update = $con->query("UPDATE users SET password = '$hashed' WHERE id = '$userid'");
                if ($update) {
                    $response['success'] = true;
                    $response['message'] = 'Password changed successfully!';
                } else {
                    $response['message'] = 'Failed to update password.';
                }
            } else {
                $response['message'] = 'Current password is incorrect.';
            }
        } else {
            $response['message'] = 'User not found.';
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

require_once 'project_add_functions.php';
require_once 'projects_update.php';
require_once 'projects_remove.php';

if (!isset($_GET['id'])) {
    header("Location: project_list.php");
    exit();
}

$project_id = $_GET['id'];

// Fetch project details
$project_query = mysqli_query($con, "SELECT * FROM projects WHERE project_id='$project_id' AND user_id='$userid'");

// If project not found or doesn't belong to user, redirect
if (mysqli_num_rows($project_query) == 0) {
    header("Location: project_list.php");
    exit();
}

$project = mysqli_fetch_assoc($project_query);

// Handle project update
if (isset($_POST['update_project'])) {
    $projectname = $_POST['projectname'];
    $projectlocation = $_POST['projectlocation'];
    $projectdeadline = $_POST['projectdeadline'];
    $projectstatus = $_POST['projectstatus'];
    
    // Update project
    $update_query = "UPDATE projects SET project='$projectname', location='$projectlocation', 
                    deadline='$projectdeadline', io='$projectstatus' 
                    WHERE project_id='$project_id' AND user_id='$userid'";
    
    mysqli_query($con, $update_query) or die(mysqli_error($con));
    
    // If status is set to Finished (2), insert into expenses
    if ($projectstatus == '2') {
        // Calculate grand total for this project
        $emp_total = 0;
        $emp_query = mysqli_query($con, "SELECT total FROM project_add_employee WHERE project_id='$project_id'");
        while ($erow = mysqli_fetch_assoc($emp_query)) {
            $emp_total += floatval($erow['total']);
        }
        $mat_total = 0;
        $mat_query = mysqli_query($con, "SELECT total FROM project_add_materials WHERE project_id='$project_id'");
        while ($mrow = mysqli_fetch_assoc($mat_query)) {
            $mat_total += floatval($mrow['total']);
        }
        $equip_total = 0;
        $equip_query = mysqli_query($con, "SELECT total FROM project_add_equipment WHERE project_id='$project_id'");
        while ($eqrow = mysqli_fetch_assoc($equip_query)) {
            $equip_total += floatval($eqrow['total']);
        }
        $grand_total = $emp_total + $mat_total + $equip_total;
        $today = date('Y-m-d');
        $expense_sql = "INSERT INTO expenses (user_id, expense, expensedate, expensecategory, project_name, description) VALUES ('$userid', '$grand_total', '$today', 'Project', '$projectname', 'finished ang project')";
        mysqli_query($con, $expense_sql);
    }
    // Refresh the page to show updated data
    header("Location: project_details.php?id=$project_id&updated=1");
    exit();
}

// Handle project deletion
if (isset($_GET['delete'])) {
    // Delete the project
    mysqli_query($con, "DELETE FROM projects WHERE project_id='$project_id' AND user_id='$userid'") 
        or die(mysqli_error($con));
    
    // Redirect to project list
    header("Location: project_list.php?deleted=1");
    exit();
}

// Status labels
$status_labels = [
    '1' => '<span class="badge bg-success">On going</span>',
    '2' => '<span class="badge bg-secondary">Finished</span>',
    '3' => '<span class="badge bg-danger">Canceled</span>',
    '4' => '<span class="badge bg-warning text-dark">Pending</span>'
];

// Fetch positions for dropdown
$positions_result = mysqli_query($con, "SELECT position_id, title FROM positions ORDER BY title ASC");
$positions = [];
while ($row = mysqli_fetch_assoc($positions_result)) {
    $positions[] = $row;
}
// Fetch unique units for dropdown
$units_result = mysqli_query($con, "SELECT DISTINCT unit FROM materials WHERE unit IS NOT NULL AND unit != '' ORDER BY unit ASC");
$units = [];
while ($row = mysqli_fetch_assoc($units_result)) {
    $units[] = $row['unit'];
}
// Fetch employees for dropdown (for this user, exclude Foreman)
$employees_result = mysqli_query($con, "SELECT e.employee_id, e.first_name, e.last_name, e.contact_number, p.title as position_title, p.daily_rate FROM employees e LEFT JOIN positions p ON e.position_id = p.position_id WHERE e.user_id='$userid' ORDER BY e.last_name, e.first_name");
$employees = [];
while ($row = mysqli_fetch_assoc($employees_result)) {
    $employees[] = $row;
}
// Fetch materials for dropdown
$materials_result = mysqli_query($con, "SELECT * FROM materials WHERE status = 'Available' ORDER BY material_name ASC");
$materials = [];
while ($row = mysqli_fetch_assoc($materials_result)) {
    $materials[] = $row;
}
// Fetch project employees
$proj_emps = [];
$emp_total = 0;
$emp_query = mysqli_query($con, "SELECT pae.*, e.first_name, e.last_name FROM project_add_employee pae LEFT JOIN employees e ON pae.employee_id = e.employee_id WHERE pae.project_id = '$project_id'");
while ($row = mysqli_fetch_assoc($emp_query)) {
    $proj_emps[] = $row;
    $emp_total += floatval($row['total']);
}
// Fetch project materials
$proj_mats = [];
$mat_total = 0;
$mat_query = mysqli_query($con, "SELECT pam.*, m.supplier_name, m.material_price, m.labor_other, m.unit, m.material_name FROM project_add_materials pam LEFT JOIN materials m ON pam.material_id = m.id WHERE pam.project_id = '$project_id'");
while ($row = mysqli_fetch_assoc($mat_query)) {
    $proj_mats[] = $row;
    $mat_total += floatval($row['total']);
}
// Fetch project equipments
$proj_equipments = [];
$equip_total = 0;
$equip_query = mysqli_query($con, "SELECT pae.*, e.equipment_name, e.equipment_price AS price, e.depreciation, e.rental_fee, pae.status FROM project_add_equipment pae LEFT JOIN equipment e ON pae.equipment_id = e.id WHERE pae.project_id = '$project_id'");
while ($row = mysqli_fetch_assoc($equip_query)) {
    if ($row['status'] !== 'Pending') {
        $equip_total += floatval($row['total']); // Only add if not Pending
    }
    if ($row['status'] === 'In Use') {
        $proj_equipments[] = $row; // Only show in table if in use
    }
}
$grand_total = $emp_total + $mat_total + $equip_total;

// Fetch division progress for chart
$div_chart_labels = [];
$div_chart_data = [];
$div_chart_query = mysqli_query($con, "SELECT division_name, progress FROM project_divisions WHERE project_id='$project_id'");
while ($row = mysqli_fetch_assoc($div_chart_query)) {
    $div_chart_labels[] = $row['division_name'];
    $div_chart_data[] = (int)$row['progress'];
}
// Calculate overall project progress (average of all divisions)
$overall_progress = 0;
if (count($div_chart_data) > 0) {
    $avg = array_sum($div_chart_data) / count($div_chart_data);
    $all_full = (min($div_chart_data) == 100);
    $overall_progress = $all_full ? 100 : floor($avg);
}

$user = null;
$userprofile = '../uploads/default_profile.png';
if ($userid) {
    $result = $con->query("SELECT * FROM users WHERE id = '$userid'");
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_firstname = $user['firstname'];
        $user_lastname = $user['lastname'];
        $user_email = $user['email'];
        $userprofile = isset($user['profile_path']) && $user['profile_path'] ? '../uploads/' . $user['profile_path'] : '../uploads/default_profile.png';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="style.css" />
    <title>Project Details</title>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-white" id="sidebar-wrapper">
        <div class="user text-center py-4">
                <img class="img img-fluid rounded-circle mb-2 sidebar-profile-img" src="<?php echo isset($userprofile) ? $userprofile : (isset($_SESSION['userprofile']) ? $_SESSION['userprofile'] : '../uploads/default_profile.png'); ?>" width="70" alt="User Profile">
                <h5 class="mb-1 text-white"><?php echo htmlspecialchars($user_name); ?></h5>
                <p class="text-white small mb-0 text wh"><?php echo htmlspecialchars($user_email); ?></p>
                <hr style="border-top: 1px solid #fff; opacity: 0.3; margin: 12px 0 0 0;">
            </div>
            <div class="list-group list-group-flush ">
                <a href="pm_dashboard.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'pm_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>Dashboard
                </a>
                <a href="projects.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'projects.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>Projects
                </a>
                <a href="expenses.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'expenses.php' ? 'active' : ''; ?>">
                    <i class="fas fa-wallet"></i>Expenses
                </a>
                <a href="materials.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'materials.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cubes"></i>Materials
                </a>
                <a href="equipment.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'equipment.php' ? 'active' : ''; ?>">
                    <i class="fas fa-wrench"></i>Equipment
                </a>
                <a href="suppliers.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'suppliers.php' ? 'active' : ''; ?>">
                    <i class="fas fa-truck"></i>Suppliers
                </a>
                <a href="employees.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'employees.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-friends"></i>Employees
                </a>
                <a href="positions.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'positions.php' ? 'active' : ''; ?>">
                    <i class="fas fa-briefcase"></i>Position
                </a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">Project Details</h2>
                </div>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php include 'pm_notification.php'; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle second-text fw-bold" href="#" id="navbarDropdown"
                                role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php echo htmlspecialchars($user_name); ?>
                                <img src="<?php echo $userprofile; ?>" alt="User" class="rounded-circle" width="30" height="30" style="margin-left: 8px;">
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="pm_profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change Password</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="container-fluid px-4">
            <!-- START CARD WRAPPER -->
            <div class="card shadow-sm mb-4">
              <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Project Details<?php
                    $status_text = strip_tags($status_labels[$project['io']]);
                    echo ' (' . strtoupper($status_text) . ')';
                ?></h4>
                <div class="d-flex gap-2">
                  <a href="projects.php" class="btn btn-light btn-sm">
                    <i class="fa fa-arrow-left"></i> Back to Projects
                  </a>
                  <a href="export_project_pdf.php?id=<?php echo $project_id; ?>" class="btn btn-danger btn-sm" target="_blank">
                    <i class="fas fa-file-export"></i> Generate
                  </a>
                </div>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6">
                    <!-- Project Information Card -->
                    <div class="card mb-4 shadow-sm">
                      <div class="card-header bg-success text-white d-flex align-items-center">
                        <h5 class="mb-0 flex-grow-1">Project Information</h5>
                        <button type="button" class="btn btn-light btn-sm ml-auto" data-bs-toggle="modal" data-bs-target="#editProjectModal"><i class="fas fa-edit me-1"></i> Edit Project</button>
                        <button type="button" class="btn btn-light btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#viewPermitsModal"><i class="fas fa-file-alt me-1"></i> View Permits</button>
                        <button type="button" class="btn btn-light btn-sm ms-2 upload-files-btn" data-project-id="<?php echo $project_id; ?>" data-bs-toggle="modal" data-bs-target="#uploadFilesModal"><i class="fas fa-upload me-1"></i> Upload</button>
                      </div>
                      <div class="card-body">
                        <div class="row">
                          <div class="col-md-6 mb-2">
                            <div class="mb-2"><strong>Project Name:</strong> <?php echo htmlspecialchars($project['project']); ?></div>
                            <div class="mb-2"><strong>Location:</strong> <?php echo htmlspecialchars($project['location']); ?></div>
                            <div class="mb-2"><strong>Deadline:</strong> <span class="text-danger"><?php echo date("F d, Y", strtotime($project['deadline'])); ?></span></div>
                          </div>
                          <div class="col-md-6 mb-2">
                            <div class="mb-2"><strong>Foreman:</strong> <?php echo htmlspecialchars($project['foreman'] ?? ''); ?></div>
                            <div class="mb-2"><strong>Created:</strong> <?php echo date("F d, Y", strtotime($project['created_at'])); ?></div>
                          </div>
                        </div>
                        <div class="mb-2"><strong>Category:</strong> <?php echo htmlspecialchars($project['category']); ?></div>
                        <hr>
                        <div class="text-end font-weight-bold mt-3" style="font-size:1.3em; color:#222;">
                          <span style="font-size:1.3em; vertical-align:middle; margin-right:4px; font-weight:bold; color:#222;"></span> Grand Total (Employees + Materials + Equipment): <span style="font-weight:bold;color:#222">₱<?php echo number_format($grand_total, 2); ?></span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <!-- Project Progress Card -->
                    <div class="card mb-4 shadow-sm">
                      <div class="card-header bg-success text-white d-flex align-items-center">
                        <h5 class="mb-0 flex-grow-1">Project Progress</h5>
                        <a href="project_progress.php?id=<?php echo $project_id; ?>" class="btn btn-light btn-sm ml-auto"><i class="fas fa-angle-double-right me-1"></i> Show more</a>
                      </div>
                      <div class="card-body">
                        <!-- Overall project progress bar -->
                        <div class="progress mb-3" style="height: 28px;">
                          <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $overall_progress; ?>%; font-size:1.1em;" aria-valuenow="<?php echo $overall_progress; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo $overall_progress; ?>%
                          </div>
                        </div>
                        <!-- Per-division chart -->
                        <div class="mb-3">
                          <canvas id="divisionProgressChart" height="180"></canvas>
                        </div>
                        <p><strong>Note:</strong> The progress bar shows the overall project progress (average of all divisions). The chart shows the progress of each division for this project.</p>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Tabs for Employees, Materials, Equipments -->
                <ul class="nav nav-tabs mt-4" id="projectTabs" role="tablist">
                  <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="employees-tab" data-bs-toggle="tab" data-bs-target="#employees" type="button" role="tab" aria-controls="employees" aria-selected="true">Project Employees</button>
                  </li>
                  <li class="nav-item" role="presentation">
                    <button class="nav-link" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button" role="tab" aria-controls="materials" aria-selected="false">Project Materials</button>
                  </li>
                  <li class="nav-item" role="presentation">
                    <button class="nav-link" id="equipments-tab" data-bs-toggle="tab" data-bs-target="#equipments" type="button" role="tab" aria-controls="equipments" aria-selected="false">Project Equipments</button>
                  </li>
                </ul>
                <div class="tab-content" id="projectTabsContent">
                  <div class="tab-pane fade show active" id="employees" role="tabpanel" aria-labelledby="employees-tab">
                    <div class="card mb-3 shadow-sm mt-3">
                      <div class="card-header bg-success text-white d-flex align-items-center">
                        <span class="flex-grow-1">Project Employees</span>
                        <?php if ($project['io'] == '1' || $project['io'] == '4'): ?>
                        <button class="btn btn-light btn-sm ml-auto" data-bs-toggle="modal" data-bs-target="#addEmployeeModal"><i class="fas fa-user-plus me-1"></i> Add Employee</button>
                        <?php endif; ?>
                      </div>
                      <div class="card-body p-0">
                        <div class="table-responsive">
                          <table class="table table-bordered mb-0">
                            <thead class="thead-light">
                              <tr>
                                <th>No.</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Daily Rate</th>
                                <th>Project Days</th>
                                <th>Total</th>
                                <th>Action</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php
                                // Compute project days once
                                $start_date = $project['start_date'];
                                $end_date = $project['deadline'];
                                $start = new DateTime($start_date);
                                $end = new DateTime($end_date);
                                $interval = $start->diff($end);
                                $project_days = $interval->days + 1;
                              ?>
                              <?php if (count($proj_emps) > 0): $i = 1; foreach ($proj_emps as $emp): ?>
                              <tr>
                                <td><?php echo $i++; ?></td>
                                <td style="font-weight:bold;color:#222;"><?php echo htmlspecialchars(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($emp['position']); ?></td>
                                <td><?php echo number_format($emp['daily_rate'], 2); ?></td>
                                <td><?php echo $project_days; ?></td>
                                <td style="font-weight:bold;color:#222;">₱<?php echo number_format($emp['daily_rate'] * $project_days, 2); ?></td>
                                <td>
                                  <form method="post" style="display:inline;">
                                    <input type="hidden" name="row_id" value="<?php echo $emp['id']; ?>">
                                    <button type="submit" name="remove_project_employee" class="btn btn-sm btn-danger" onclick="return confirm('Remove this employee?')" <?php if ($project['io'] == '2') echo 'disabled'; ?>><i class="fas fa-trash"></i> Remove</button>
                                  </form>
                                </td>
                              </tr>
                              <?php endforeach; else: ?>
                              <tr><td colspan="7" class="text-center">No employees added</td></tr>
                              <?php endif; ?>
                            </tbody>
                            <tfoot>
                              <tr>
                                <th colspan="5" class="text-right">Total</th>
                                <th colspan="2" style="font-weight:bold;color:#222;">₱<?php
                                  $emp_total = 0;
                                  foreach ($proj_emps as $emp) {
                                    $emp_total += $emp['daily_rate'] * $project_days;
                                  }
                                  echo number_format($emp_total, 2);
                                ?></th>
                              </tr>
                            </tfoot>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade" id="materials" role="tabpanel" aria-labelledby="materials-tab">
                    <div class="card mb-3 shadow-sm mt-3">
                      <div class="card-header bg-success text-white d-flex align-items-center">
                        <span class="flex-grow-1">Project Materials</span>
                        <?php if ($project['io'] == '1' || $project['io'] == '4'): ?>
                        <button class="btn btn-light btn-sm ml-auto" data-bs-toggle="modal" data-bs-target="#addMaterialsModal"><i class="fas fa-plus-square me-1"></i> Add Materials</button>
                        <?php endif; ?>
                      </div>
                      <div class="card-body p-0">
                        <div class="table-responsive">
                          <table class="table table-bordered mb-0">
                            <thead class="table-secondary">
                              <tr>
                                <th>No.</th>
                                <th>Name</th>
                                <th>Unit</th>
                                <th>Material Price</th>
                                <th>Labor/Other</th>
                                <th>Quantity</th>
                                <th>Supplier</th>
                                <th>Total</th>
                                <th>Action</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php if (count($proj_mats) > 0): $i = 1; foreach ($proj_mats as $mat): ?>
                              <tr>
                                <td><?php echo $i++; ?></td>
                                <td style="font-weight:bold;color:#222;"><?php echo htmlspecialchars($mat['material_name']); ?></td>
                                <td><?php echo htmlspecialchars($mat['unit']); ?></td>
                                <td><?php echo number_format($mat['material_price'], 2); ?></td>
                                <td><?php echo number_format($mat['labor_other'], 2); ?></td>
                                <td><?php echo $mat['quantity']; ?></td>
                                <td><?php echo isset($mat['supplier_name']) && $mat['supplier_name'] ? htmlspecialchars($mat['supplier_name']) : 'N/A'; ?></td>
                                <td style="font-weight:bold;color:#222;">₱<?php echo number_format(($mat['material_price'] + $mat['labor_other']) * $mat['quantity'], 2); ?></td>
                                <td>
                                  <form method="post" style="display:inline;">
                                    <input type="hidden" name="row_id" value="<?php echo $mat['id']; ?>">
                                    <button type="submit" name="remove_project_material" class="btn btn-sm btn-danger" onclick="return confirm('Remove this material?')" <?php if ($project['io'] == '2') echo 'disabled'; ?>><i class="fas fa-trash"></i> Remove</button>
                                  </form>
                                  <form method="post" style="display:inline;">
                                    <input type="hidden" name="row_id" value="<?php echo $mat['id']; ?>">
                                    <button type="submit" name="return_project_material" class="btn btn-sm btn-warning" onclick="return confirm('Return this material to inventory?')" <?php if ($project['io'] == '2') echo 'disabled'; ?>><i class="fas fa-undo"></i> Return</button>
                                  </form>
                                </td>
                              </tr>
                              <?php endforeach; else: ?>
                              <tr><td colspan="9" class="text-center">No materials added</td></tr>
                              <?php endif; ?>
                            </tbody>
                            <tfoot>
                              <tr>
                                <th colspan="7" class="text-right">Total</th>
                                <th colspan="2" style="font-weight:bold;color:#222;">₱<?php
                                  $mat_total = 0;
                                  foreach ($proj_mats as $mat) {
                                    $mat_total += ($mat['material_price'] + $mat['labor_other']) * $mat['quantity'];
                                  }
                                  echo number_format($mat_total, 2);
                                ?></th>
                              </tr>
                            </tfoot>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade" id="equipments" role="tabpanel" aria-labelledby="equipments-tab">
                    <div class="card mb-3 shadow-sm mt-3">
                      <div class="card-header bg-success text-white d-flex align-items-center">
                        <span class="flex-grow-1">Project Equipments</span>
                        <?php if ($project['io'] == '1' || $project['io'] == '4'): ?>
                        <button class="btn btn-light btn-sm ml-auto" data-bs-toggle="modal" data-bs-target="#addEquipmentModal"><i class="fas fa-truck-loading me-1"></i> Add Equipment</button>
                        <?php endif; ?>
                      </div>
                      <div class="card-body p-0">
                        <div class="table-responsive">
                          <table class="table table-bordered mb-0">
                            <thead class="table-secondary">
                              <tr>
                                <th>No.</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Depreciation</th>
                                <th>Category</th>
                                <th>Project Days</th>
                                <th>Total</th>
                                <th>Action</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php
                                // Compute project days once (already computed above, reuse if possible)
                                $start_date = $project['start_date'];
                                $end_date = $project['deadline'];
                                $start = new DateTime($start_date);
                                $end = new DateTime($end_date);
                                $interval = $start->diff($end);
                                $project_days = $interval->days + 1;
                              ?>
                              <?php if (count($proj_equipments) > 0): $i = 1; foreach ($proj_equipments as $eq): ?>
                              <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($eq['equipment_name']); ?></td>
                                <td><?php
                                    if (isset($eq['rental_fee']) && $eq['rental_fee'] !== null && $eq['rental_fee'] !== '' && $eq['rental_fee'] > 0) {
                                      echo number_format($eq['rental_fee'], 2);
                                    } elseif (isset($eq['price']) && $eq['price'] !== null && $eq['price'] !== '' && $eq['price'] > 0) {
                                      echo number_format($eq['price'], 2);
                                    } else {
                                      echo '-';
                                    }
                                  ?></td>
                                <td>
                                  <?php
                                    if (is_numeric($eq['depreciation'])) {
                                      echo intval($eq['depreciation']) . ' years';
                                    } elseif (!empty($eq['depreciation'])) {
                                      echo htmlspecialchars($eq['depreciation']);
                                    } else {
                                      echo '-';
                                    }
                                  ?>
                                </td>
                                <td><?php echo htmlspecialchars($eq['category']); ?></td>
                                <td><?php echo $project_days; ?></td>
                                <td>
                                  <?php
                                      if (isset($eq['category']) && (strtolower($eq['category']) == 'rental' || strtolower($eq['category']) == 'rent')) {
                                          $rental_fee = isset($eq['rental_fee']) && $eq['rental_fee'] !== null && $eq['rental_fee'] !== '' ? floatval($eq['rental_fee']) : null;
                                          
                                          if ($rental_fee !== null) {
                                              $total = $rental_fee * $project_days;
                                          } else {
                                              // fallback to depreciation if rental fee is missing
                                              $price = isset($eq['price']) ? $eq['price'] : 0;
                                              $depr = isset($eq['depreciation']) && is_numeric($eq['depreciation']) && $eq['depreciation'] > 0 ? $eq['depreciation'] : 0;
                                              if ($depr > 0) {
                                                  $depr_per_day = $price / ($depr * 365);
                                                  $total = $depr_per_day * $project_days;
                                              } else {
                                                  $total = 0;
                                              }
                                          }
                                      } else {
                                          // non-rental, company-owned logic
                                          $price = isset($eq['price']) ? $eq['price'] : 0;
                                          $depr = isset($eq['depreciation']) && is_numeric($eq['depreciation']) && $eq['depreciation'] > 0 ? $eq['depreciation'] : 0;
                                          if ($depr > 0) {
                                              $depr_per_day = $price / ($depr * 365);
                                              $total = $depr_per_day * $project_days;
                                          } else {
                                              $total = 0;
                                          }
                                      }

                                    
                                    echo number_format($total, 2);
                                  ?>
                                </td>
                                <td>
                                  <?php if ($eq['status'] == 'In Use'): ?>
                                    <form method="post" style="display:inline;">
                                      <input type="hidden" name="report_equipment" value="1">
                                      <input type="hidden" name="report_row_id" value="<?php echo $eq['id']; ?>">
                                      <input type="hidden" name="report_remarks" value="Damage Equipment">
                                      <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Mark this equipment as damaged?')" <?php if ($project['io'] == '2') echo 'disabled'; ?>><i class="fas fa-exclamation-triangle"></i> Report Damage</button>
                                    </form>
                                    <form method="post" style="display:inline;">
                                      <input type="hidden" name="row_id" value="<?php echo $eq['id']; ?>">
                                      <button type="submit" name="return_project_equipment" class="btn btn-sm btn-warning" onclick="return confirm('Mark this equipment as returned?')" <?php if ($project['io'] == '2') echo 'disabled'; ?>><i class="fas fa-undo"></i> Return</button>
                                    </form>
                                  <?php else: ?>
                                    <span class="badge bg-success">Returned</span>
                                  <?php endif; ?>
                                </td>
                              </tr>
                              <?php endforeach; else: ?>
                              <tr><td colspan="8" class="text-center">No equipment added</td></tr>
                              <?php endif; ?>
                            </tbody>
                            <tfoot>
                              <tr>
                                <th colspan="6" class="text-right">Total</th>
                                <th colspan="2" style="font-weight:bold;color:#222;">₱<?php
                                  $equip_total = 0;
                                  foreach ($proj_equipments as $eq) {
                                    if (isset($eq['category']) && (strtolower($eq['category']) === 'rental' || strtolower($eq['category']) === 'rent')) {
                                      $price = isset($eq['rental_fee']) ? $eq['rental_fee'] : 0;
                                      $equip_total += $price * $project_days;
                                    } else {
                                      $price = isset($eq['price']) ? $eq['price'] : 0;
                                      $depr = isset($eq['depreciation']) && is_numeric($eq['depreciation']) && $eq['depreciation'] > 0 ? $eq['depreciation'] : 0;
                                      if ($depr > 0) {
                                        $depr_per_day = $price / ($depr * 365);
                                        $equip_total += $depr_per_day * $project_days;
                                      } else {
                                        $equip_total += 0;
                                      }
                                    }
                                  }
                                  echo number_format($equip_total, 2);
                                ?></th>
                              </tr>
                            </tfoot>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- END CARD WRAPPER -->

            </div>
        </div>
    </div>
    <!-- /#page-content-wrapper -->
    </div>

<!-- Modals moved outside the card/container for proper Bootstrap modal functionality -->
<div class="modal fade" id="editProjectModal" tabindex="-1" role="dialog" aria-labelledby="editProjectModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editProjectModalLabel">Edit Project</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="">
        <div class="modal-body">
          <div class="form-group">
            <label for="projectname">Project Name</label>
            <input type="text" class="form-control" id="projectname" name="projectname" value="<?php echo $project['project']; ?>" required>
          </div>
          <div class="form-group">
            <label for="projectlocation">Location</label>
            <input type="text" class="form-control" id="projectlocation" name="projectlocation" value="<?php echo $project['location']; ?>" required>
          </div>
          <div class="form-group">
            <label for="projectstartdate">Start Date</label>
            <input type="date" class="form-control" id="projectstartdate" name="projectstartdate" value="<?php echo $project['start_date']; ?>" required>
          </div>
          <div class="form-group">
            <label for="projectdeadline">Deadline</label>
            <input type="date" class="form-control" id="projectdeadline" name="projectdeadline" value="<?php echo $project['deadline']; ?>" required>
          </div>
          <div class="form-group">
            <label for="projectstatus">Status</label>
            <select class="form-control" id="projectstatus" name="projectstatus" required>
              <option value="1" <?php if($project['io'] == '1') echo 'selected'; ?>>On going</option>
              <option value="2" <?php if($project['io'] == '2') echo 'selected'; ?>>Finished</option>
              <option value="3" <?php if($project['io'] == '3') echo 'selected'; ?>>Canceled</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="update_project" class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" role="dialog" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addEmployeeModalLabel">Add Employee</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="add_project_employee" value="1">
          <div class="form-group">
            <label for="employeeName">Employee Name</label>
            <select class="form-control" id="employeeName" name="employeeName" required>
              <option value="" disabled selected>Select Employee</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>"
                  data-position="<?php echo htmlspecialchars($emp['position_title']); ?>"
                  data-contact="<?php echo htmlspecialchars($emp['contact_number']); ?>"
                  data-rate="<?php echo htmlspecialchars($emp['daily_rate']); ?>"
                ><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="employeePosition">Position</label>
            <input type="text" class="form-control" id="employeePosition" name="employeePosition" readonly>
          </div>
          <div class="form-group">
            <label for="employeeContact">Contact Number</label>
            <input type="text" class="form-control" id="employeeContact" name="employeeContact" readonly>
          </div>
          <div class="form-group">
            <label for="employeeRate">Daily Rate</label>
            <input type="text" class="form-control" id="employeeRate" name="employeeRate" readonly>
          </div>
          <div class="form-group">
            <label for="employeeTotal">Total</label>
            <input type="text" class="form-control" id="employeeTotal" name="employeeTotal" readonly>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Add Employee</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Materials Modal -->
<div class="modal fade" id="addMaterialsModal" tabindex="-1" role="dialog" aria-labelledby="addMaterialsModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addMaterialsModalLabel">Add Materials</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="add_project_material" value="1">
          <div class="form-group">
            <!-- Category Dropdown -->
            <label for="materialCategory">Category</label>
            <select class="form-control mb-2" id="materialCategory" name="materialCategory">
              <option value="" disabled selected>Select Category</option>
              <?php
                $categories = array();
                foreach ($materials as $mat) {
                  if (!empty($mat['category']) && (int)$mat['quantity'] > 0) {
                    $categories[$mat['category']] = true;
                  }
                }
                foreach (array_keys($categories) as $cat): ?>
                  <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
              <?php endforeach; ?>
            </select>
            <!-- Material Name Dropdown -->
            <label for="materialName">Material Name</label>
            <select class="form-control" id="materialName" name="materialName" required>
              <option value="" disabled selected>Select Material</option>
              <?php foreach ($materials as $mat): ?>
                <option value="<?php echo htmlspecialchars($mat['id']); ?>"
                  data-category="<?php echo htmlspecialchars($mat['category']); ?>"
                  data-unit="<?php echo htmlspecialchars($mat['unit']); ?>"
                  data-price="<?php echo htmlspecialchars($mat['material_price']); ?>"
                  data-name="<?php echo htmlspecialchars($mat['material_name']); ?>"
                  data-qty="<?php echo isset($mat['quantity']) ? (int)$mat['quantity'] : 0; ?>"
                  <?php echo (isset($mat['quantity']) && (int)$mat['quantity'] === 0) ? 'disabled style="color:#aaa;"' : ''; ?>
                ><?php echo htmlspecialchars($mat['material_name']); ?><?php if(isset($mat['quantity']) && (int)$mat['quantity'] === 0) echo ' (Out of Stock)'; ?></option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" id="materialNameText" name="materialNameText">
          </div>
          <div class="form-group">
            <label for="materialQty">Quantity</label>
            <input type="number" class="form-control" id="materialQty" name="materialQty" required>
          </div>
          <div class="form-group">
            <label for="materialQtyLeft">Remaining Quantity</label>
            <input type="text" class="form-control" id="materialQtyLeft" name="materialQtyLeft" readonly>
          </div>
          <div class="form-group">
            <label for="materialUnit">Unit</label>
            <input type="text" class="form-control" id="materialUnit" name="materialUnit" readonly>
          </div>
          <div class="form-group">
            <label for="materialPrice">Material Price</label>
            <input type="text" class="form-control" id="materialPrice" name="materialPrice" readonly>
          </div>
          <div class="form-group">
            <label for="materialTotal">Total Price</label>
            <input type="text" class="form-control" id="materialTotal" name="materialTotal" readonly>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Add Material</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Equipment Modal -->
<div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-labelledby="addEquipmentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addEquipmentModalLabel">Add Equipment to Project</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="add_project_equipment" value="1">
          
          <script>
            // Store all equipment data in JS for filtering
            var allEquipment = <?php
              $all_equipment = mysqli_query($con, "SELECT * FROM equipment WHERE status = 'Available' AND approval = 'Approved' ORDER BY equipment_name ASC");
              $equipment_js = [];
              while ($eq = mysqli_fetch_assoc($all_equipment)) {
                $equipment_js[] = [
                  'id' => $eq['id'],
                  'name' => $eq['equipment_name'],
                  'category' => $eq['category'],
                  'price' => $eq['equipment_price'],
                  'depreciation' => $eq['depreciation'],
                  'rental_fee' => $eq['rental_fee']
                ];
              }
              echo json_encode($equipment_js);
            ?>;
          </script>
          <div class="form-group mb-2">
            <label for="categorySelect">Category</label>
            <select class="form-control" id="categorySelect" name="category" required>
              <option value="" disabled selected>Select Category</option>
              <?php 
              $equipment_categories_result = mysqli_query($con, "SELECT DISTINCT category FROM equipment WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
              $equipment_categories = [];
              while ($row = mysqli_fetch_assoc($equipment_categories_result)) {
                  $equipment_categories[] = $row['category'];
              }
              foreach ($equipment_categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-2">
            <label for="equipmentSelect">Equipment</label>
            <select class="form-control" id="equipmentSelect" name="equipment_id" required>
              <option value="" disabled selected>Select Equipment</option>
            </select>
          </div>
          <div class="form-group mb-2" id="rentGroup" style="display:none;">
            <label for="rentInput">Rent</label>
            <input type="number" step="0.01" min="0" class="form-control" id="rentInput" name="rent">
          </div>
          <div class="form-group mb-2">
            <label>Equipment Price</label>
            <input type="text" class="form-control" id="equipmentPriceInput" readonly>
          </div>
          <div class="form-group mb-2">
            <label>Depreciation</label>
            <input type="text" class="form-control" id="depreciationInput" readonly>
          </div>
          <div class="form-group mb-2">
            <label for="daysUsedInput">Days Used</label>
            <input type="number" min="0" class="form-control" id="daysUsedInput" name="days_used" value="0" required>
          </div>
          <div class="form-group mb-2">
            <label>Total</label>
            <input type="text" class="form-control" id="totalInput" name="total" readonly>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Add Equipment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="changePasswordForm">
          <div class="mb-3">
            <label for="current_password" class="form-label">Current Password</label>
            <input type="password" class="form-control" id="current_password" name="current_password" required>
          </div>
          <div class="mb-3">
            <label for="new_password" class="form-label">New Password</label>
            <input type="password" class="form-control" id="new_password" name="new_password" required>
          </div>
          <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
          </div>
          <div id="changePasswordFeedback" class="mb-2"></div>
          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-success">Change Password</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- View Permits Modal -->
<div class="modal fade" id="viewPermitsModal" tabindex="-1" aria-labelledby="viewPermitsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewPermitsModalLabel">Project Permits & Clearances</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-md-6 col-lg-3 text-center">
            <div class="mb-2 fw-bold">LGU Permit</div>
            <?php if (!empty($project['file_photo_lgu'])): ?>
              <img src="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_lgu']); ?>" class="img-fluid rounded border mb-2 permit-thumb" style="width:200px; height:200px; object-fit:cover; cursor:pointer;" data-bs-toggle="modal" data-bs-target="#permitImageModal" data-img="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_lgu']); ?>">
            <?php else: ?>
              <div class="text-muted">Not uploaded</div>
            <?php endif; ?>
          </div>
          <div class="col-md-6 col-lg-3 text-center">
            <div class="mb-2 fw-bold">Barangay Clearance</div>
            <?php if (!empty($project['file_photo_barangay'])): ?>
              <img src="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_barangay']); ?>" class="img-fluid rounded border mb-2 permit-thumb" style="width:200px; height:200px; object-fit:cover; cursor:pointer;" data-bs-toggle="modal" data-bs-target="#permitImageModal" data-img="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_barangay']); ?>">
            <?php else: ?>
              <div class="text-muted">Not uploaded</div>
            <?php endif; ?>
          </div>
          <div class="col-md-6 col-lg-3 text-center">
            <div class="mb-2 fw-bold">Fire Clearance</div>
            <?php if (!empty($project['file_photo_fire'])): ?>
              <img src="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_fire']); ?>" class="img-fluid rounded border mb-2 permit-thumb" style="width:200px; height:200px; object-fit:cover; cursor:pointer;" data-bs-toggle="modal" data-bs-target="#permitImageModal" data-img="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_fire']); ?>">
            <?php else: ?>
              <div class="text-muted">Not uploaded</div>
            <?php endif; ?>
          </div>
          <div class="col-md-6 col-lg-3 text-center">
            <div class="mb-2 fw-bold">Occupancy Permit</div>
            <?php if (!empty($project['file_photo_occupancy'])): ?>
              <img src="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_occupancy']); ?>" class="img-fluid rounded border mb-2 permit-thumb" style="width:200px; height:200px; object-fit:cover; cursor:pointer;" data-bs-toggle="modal" data-bs-target="#permitImageModal" data-img="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_occupancy']); ?>">
            <?php else: ?>
              <div class="text-muted">Not uploaded</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Permit Image Preview Modal -->
<div class="modal fade" id="permitImageModal" tabindex="-1" aria-labelledby="permitImageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="permitImageModalLabel">Permit Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img id="permitImageModalImg" src="" alt="Permit Preview" style="max-width:100%; max-height:80vh; border-radius:8px;">
      </div>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('.permit-thumb').forEach(function(img) {
  img.addEventListener('click', function() {
    var modalImg = document.getElementById('permitImageModalImg');
    modalImg.src = this.getAttribute('data-img');
  });
});
</script>

<!-- Add a modal for reporting equipment -->
<div class="modal fade" id="reportEquipmentModal" tabindex="-1" aria-labelledby="reportEquipmentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="reportEquipmentModalLabel">Report Equipment Issue</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="report_equipment" value="1">
          <input type="hidden" id="report_row_id" name="report_row_id">
          <div class="mb-3">
            <label for="report_message" class="form-label">Message (reason for report):</label>
            <textarea class="form-control" id="report_message" name="report_message" rows="3" required></textarea>
          </div>
          <div class="mb-3">
            <label for="report_remarks" class="form-label">Remarks (optional):</label>
            <textarea class="form-control" id="report_remarks" name="report_remarks" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Submit Report</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
// Fill report modal with row_id when Report button is clicked
$(document).on('click', '.report-btn', function() {
  var rowId = $(this).data('row-id');
  $('#report_row_id').val(rowId);
});
</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
    </script>
     <script>
        feather.replace()
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
  // Filter Material Name dropdown by selected category
  var materialCategory = document.getElementById('materialCategory');
  var materialNameSelect = document.getElementById('materialName');
  var allMaterialOptions = [];
  if (materialNameSelect) {
    for (var i = 0; i < materialNameSelect.options.length; i++) {
      var opt = materialNameSelect.options[i];
      if (opt.value) {
        allMaterialOptions.push({
          value: opt.value,
          text: opt.text,
          category: opt.getAttribute('data-category'),
          unit: opt.getAttribute('data-unit'),
          price: opt.getAttribute('data-price'),
          name: opt.getAttribute('data-name'),
          qty: opt.getAttribute('data-qty'),
          disabled: opt.disabled
        });
      }
    }
  }

  if (materialCategory && materialNameSelect) {
    materialCategory.addEventListener('change', function() {
      var selectedCat = materialCategory.value;
      // Remove all except the placeholder
      materialNameSelect.innerHTML = '<option value="" disabled selected>Select Material</option>';
      allMaterialOptions.forEach(function(opt) {
        if (opt.category === selectedCat && opt.qty > 0) {
          var o = document.createElement('option');
          o.value = opt.value;
          o.textContent = opt.text;
          o.setAttribute('data-category', opt.category);
          o.setAttribute('data-unit', opt.unit);
          o.setAttribute('data-price', opt.price);
          o.setAttribute('data-name', opt.name);
          o.setAttribute('data-qty', opt.qty);
          materialNameSelect.appendChild(o);
        }
      });
      // Reset dependent fields
      if (materialUnit) materialUnit.value = '';
      if (materialPrice) materialPrice.value = '';
      if (materialNameText) materialNameText.value = '';
      if (materialQtyLeft) materialQtyLeft.value = '';
      if (materialQty) materialQty.value = '';
      if (materialTotal) materialTotal.value = '';
    });
  }
  // Employee auto-fill and total
  var employeeName = document.getElementById('employeeName');
  var employeePosition = document.getElementById('employeePosition');
  var employeeContact = document.getElementById('employeeContact');
  var employeeRate = document.getElementById('employeeRate');
  var employeeDays = document.getElementById('employeeDays');
  var employeeTotal = document.getElementById('employeeTotal');

  function updateEmployeeTotal() {
    var rate = parseFloat(employeeRate.value) || 0;
    var days = parseInt(employeeDays.value) || 0;
    var total = rate * days;
    employeeTotal.value = total > 0 ? total.toFixed(2) : '';
  }

  if (employeeName) {
    employeeName.addEventListener('change', function() {
      var selected = employeeName.options[employeeName.selectedIndex];
      employeePosition.value = selected.getAttribute('data-position') || '';
      employeeContact.value = selected.getAttribute('data-contact') || '';
      employeeRate.value = selected.getAttribute('data-rate') || '';
      updateEmployeeTotal();
    });
  }
  if (employeeDays) {
    employeeDays.addEventListener('input', updateEmployeeTotal);
  }

  // Material auto-fill and total
  var materialName = document.getElementById('materialName');
  var materialUnit = document.getElementById('materialUnit');
  var materialPrice = document.getElementById('materialPrice');
  var materialNameText = document.getElementById('materialNameText');
  var materialQty = document.getElementById('materialQty');
  var materialTotal = document.getElementById('materialTotal');
  var materialQtyLeft = document.getElementById('materialQtyLeft');

  // Build a map of material id to quantity
  var materialQtyMap = {};
  <?php foreach ($materials as $mat): ?>
    materialQtyMap['<?php echo $mat['id']; ?>'] = <?php echo isset($mat['quantity']) ? (int)$mat['quantity'] : 0; ?>;
  <?php endforeach; ?>

  function updateMaterialTotal() {
    var qty = parseFloat(materialQty.value) || 0;
    var price = parseFloat(materialPrice.value) || 0;
    var total = qty * price;
    materialTotal.value = total > 0 ? total.toFixed(2) : '';
  }

  if (materialName) {
    materialName.addEventListener('change', function() {
      var selected = materialName.options[materialName.selectedIndex];
      materialUnit.value = selected.getAttribute('data-unit') || '';
      materialPrice.value = selected.getAttribute('data-price') || '';
      materialNameText.value = selected.getAttribute('data-name') || '';
      // Show remaining quantity in its own field
      var matId = selected.value;
      if (materialQtyLeft) {
        var qty = materialQtyMap[matId] !== undefined ? materialQtyMap[matId] : '';
        materialQtyLeft.value = qty !== '' ? qty : '';
      }
      updateMaterialTotal();
    });
  }
  if (materialQty) {
    materialQty.addEventListener('input', updateMaterialTotal);
  }
});
</script>
<script>
// Change Password AJAX (like pm_profile.php)
document.addEventListener('DOMContentLoaded', function() {
  var changePasswordForm = document.getElementById('changePasswordForm');
  var feedbackDiv = document.getElementById('changePasswordFeedback');
  if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', function(e) {
      e.preventDefault();
      feedbackDiv.innerHTML = '';
      var formData = new FormData(changePasswordForm);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '', true);
      xhr.onload = function() {
        try {
          var res = JSON.parse(xhr.responseText);
          if (res.success) {
            feedbackDiv.innerHTML = '<div class="alert alert-success">' + res.message + '</div>';
            changePasswordForm.reset();
            setTimeout(function() {
              var modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
              if (modal) modal.hide();
            }, 1200);
          } else {
            feedbackDiv.innerHTML = '<div class="alert alert-danger">' + res.message + '</div>';
          }
        } catch (err) {
          feedbackDiv.innerHTML = '<div class="alert alert-danger">Unexpected error. Please try again.</div>';
        }
      };
      formData.append('change_password', '1');
      xhr.send(formData);
    });
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var ctx = document.getElementById('divisionProgressChart').getContext('2d');
  var divisionLabels = <?php echo json_encode($div_chart_labels); ?>;
  var divisionData = <?php echo json_encode($div_chart_data); ?>;
  var chart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: divisionLabels,
      datasets: [{
        label: 'Progress (%)',
        data: divisionData,
        backgroundColor: [
          '#e57373', '#64b5f6', '#ffd54f', '#4dd0e1', '#9575cd', '#81c784', '#f06292', '#ba68c8', '#ffb74d', '#aed581'
        ],
        borderRadius: 6,
        maxBarThickness: 40
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          title: {
            display: true,
            text: 'Project Progress (%)'
          }
        },
        x: {
          title: {
            display: true,
            text: 'Divisions'
          }
        }
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(context) {
              return context.parsed.y + '%';
            }
          }
        }
      }
    }
  });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var categorySelect = document.getElementById('categorySelect');
  var equipmentSelect = document.getElementById('equipmentSelect');
  var priceInput = document.getElementById('equipmentPriceInput');
  var depreciationInput = document.getElementById('depreciationInput');
  var rentalFeeGroup = document.getElementById('rentalFeeGroup') || document.getElementById('rentGroup');
  var rentalFeeInput = document.getElementById('rentalFeeInput') || document.getElementById('rentInput');
  var daysUsedInput = document.getElementById('daysUsedInput');
  var totalInput = document.getElementById('totalInput');

  function filterEquipmentOptions() {
    var selectedCategory = categorySelect.value;
    // Clear all options except the placeholder
    equipmentSelect.innerHTML = '<option value="" disabled selected>Select Equipment</option>';
    if (!selectedCategory) return;
    allEquipment.forEach(function(eq) {
      if (eq.category === selectedCategory) {
        var opt = document.createElement('option');
        opt.value = eq.id;
        opt.textContent = eq.name;
        opt.setAttribute('data-category', eq.category);
        opt.setAttribute('data-price', eq.price);
        opt.setAttribute('data-depreciation', eq.depreciation);
        opt.setAttribute('data-rental_fee', eq.rental_fee);
        equipmentSelect.appendChild(opt);
      }
    });
    // Reset fields
    priceInput.value = '';
    depreciationInput.value = '';
    if (rentalFeeInput) rentalFeeInput.value = '';
    totalInput.value = '';
    showFields();
  }

  function showFields() {
    var selectedCategory = categorySelect.value;
    if (selectedCategory === 'Company') {
      priceInput.parentElement.style.display = '';
      depreciationInput.parentElement.style.display = '';
      if (rentalFeeGroup) rentalFeeGroup.style.display = 'none';
    } else if (selectedCategory === 'Rent' || selectedCategory === 'Rental') {
      priceInput.parentElement.style.display = 'none';
      depreciationInput.parentElement.style.display = 'none';
      if (rentalFeeGroup) rentalFeeGroup.style.display = '';
    } else {
      priceInput.parentElement.style.display = 'none';
      depreciationInput.parentElement.style.display = 'none';
      if (rentalFeeGroup) rentalFeeGroup.style.display = 'none';
    }
  }

  function updateFields() {
    var selected = equipmentSelect.options[equipmentSelect.selectedIndex];
    if (!selected) return;
    var price = parseFloat(selected.getAttribute('data-price')) || 0;
    var depreciation = selected.getAttribute('data-depreciation') || '';
    var rentalFee = parseFloat(selected.getAttribute('data-rental_fee')) || 0;
    var days = parseInt(daysUsedInput.value) || 0;
    var selectedCategory = categorySelect.value;

    if (selectedCategory === 'Company') {
      priceInput.value = price.toFixed(2);
      // Remove decimal for depreciation if numeric
      if (depreciation && !isNaN(depreciation)) {
        depreciationInput.value = parseInt(depreciation);
      } else {
        depreciationInput.value = depreciation;
      }
      totalInput.value = (price * days).toFixed(2);
    } else if (selectedCategory === 'Rent' || selectedCategory === 'Rental') {
      if (rentalFeeInput) rentalFeeInput.value = rentalFee.toFixed(2);
      totalInput.value = (rentalFee * days).toFixed(2);
    }
  }

  if (categorySelect) categorySelect.addEventListener('change', filterEquipmentOptions);
  if (equipmentSelect) equipmentSelect.addEventListener('change', updateFields);
  if (daysUsedInput) daysUsedInput.addEventListener('input', updateFields);

  // Initial hide/show
  showFields();
});
</script>
<!-- Feedback Modal (Unified for Success/Error) -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body">
        <span id="feedbackIcon" style="font-size: 3rem;"></span>
        <h4 id="feedbackTitle"></h4>
        <p id="feedbackMessage"></p>
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>
<script>
function showFeedbackModal(success, message, error_code = '', query_param = '') {
  var icon = document.getElementById('feedbackIcon');
  var title = document.getElementById('feedbackTitle');
  var msg = document.getElementById('feedbackMessage');
  if (success) {
    icon.innerHTML = '<i class="fas fa-check-circle" style="color:#28a745"></i>';
    title.textContent = 'Success!';
    msg.textContent = message;
  } else {
    icon.innerHTML = '<i class="fas fa-times-circle" style="color:#dc3545"></i>';
    title.textContent = 'Error!';
    msg.textContent = message;
  }
  var feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
  feedbackModal.show();
  // Remove the query param after showing the modal
  window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/([&?](addmat|removemat|returnmat|error)=[^&]*)/, ''));
}
(function() {
  var params = new URLSearchParams(window.location.search);
  if (params.get('addemp') === '1') {
    showFeedbackModal(true, 'Employee added successfully!');
    // Remove the param after showing
    params.delete('addemp');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  } else if (params.get('addmat') === '1') {
    showFeedbackModal(true, 'Material added successfully!');
    params.delete('addmat');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  } else if (params.get('removemat') === '1') {
    showFeedbackModal(true, 'Material removed successfully!');
    params.delete('removemat');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  } else if (params.get('returnmat') === '1') {
    showFeedbackModal(true, 'Material returned to inventory!');
    params.delete('returnmat');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  } else if (params.get('addequip') === '1') {
    showFeedbackModal(true, 'Equipment added successfully!');
    params.delete('addequip');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  } else if (params.get('removeemp') === '1') {
    showFeedbackModal(true, 'Employee removed successfully!');
    params.delete('removeemp');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  } else if (params.get('error') === 'material_exists') {
    showFeedbackModal(false, 'Material already added to this project!');
    params.delete('error');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  } else if (params.get('error') === 'insufficient_stock') {
    var left = params.get('left');
    var msg = 'Insufficient stock. Not enough material available.';
    if (left !== null) msg += ' Only ' + left + ' left.';
    showFeedbackModal(false, msg);
    params.delete('error');
    params.delete('left');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  } else if (params.get('error') === 'insufficient_slots') {
    var left = params.get('left');
    var msg = 'Insufficient warehouse slots. Not enough space in the warehouse.';
    if (left !== null) msg += ' Only ' + left + ' slots left.';
    showFeedbackModal(false, msg);
    params.delete('error');
    params.delete('left');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  }
})();
</script>
<?php if (isset($_GET['error']) && $_GET['error'] === 'material_exists'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  alert('Material already added to this project!');
});
</script>
<?php endif; ?>
<script>
// Set project_id for all forms in the modal when opening
const uploadFilesModal = document.getElementById('uploadFilesModal');
uploadFilesModal.addEventListener('show.bs.modal', function (event) {
  const triggerBtn = event.relatedTarget;
  let projectId = null;
  if (triggerBtn && triggerBtn.getAttribute('data-project-id')) {
    projectId = triggerBtn.getAttribute('data-project-id');
  } else {
    // fallback: try to get from a global or selection
    projectId = document.getElementById('modal_project_id')?.value || '';
  }
  document.getElementById('modal_project_id_lgu').value = projectId;
  document.getElementById('modal_project_id_barangay').value = projectId;
  document.getElementById('modal_project_id_fire').value = projectId;
  document.getElementById('modal_project_id_occupancy').value = projectId;
});
// Image preview for each file input
const previewMap = {
  'lgu': 'preview_file_photo_lgu',
  'barangay': 'preview_file_photo_barangay',
  'fire': 'preview_file_photo_fire',
  'occupancy': 'preview_file_photo_occupancy'
};
document.querySelectorAll('.single-upload-form').forEach(function(form) {
  const fileInput = form.querySelector('input[type="file"]');
  const formId = form.getAttribute('action').replace('upload_', '').replace('.php', '');
  const previewImg = document.getElementById('preview_file_photo_' + formId);
  fileInput.addEventListener('change', function() {
    if (fileInput.files && fileInput.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        previewImg.src = e.target.result;
        previewImg.classList.remove('d-none');
      };
      reader.readAsDataURL(fileInput.files[0]);
    } else {
      previewImg.classList.add('d-none');
      previewImg.src = '';
    }
  });
});
</script>
<?php if (isset($_GET['upload_success'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    showFeedbackModal(true, 'Files uploaded successfully.', '', 'upload_success');
});
</script>
<?php endif; ?>
<?php if (isset($_GET['upload_error'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    showFeedbackModal(false, 'Failed to upload files.', <?php echo json_encode(strip_tags($_GET['upload_error'])); ?>, 'upload_error');
});
</script>
<?php endif; ?>
<script>
document.querySelectorAll('.single-upload-form').forEach(function(form) {
  const fileInput = form.querySelector('input[type="file"]');
  const invalidFeedback = fileInput.nextElementSibling; // the .invalid-feedback div

  form.addEventListener('submit', function(e) {
    if (!fileInput.files || !fileInput.files[0]) {
      e.preventDefault();
      fileInput.classList.add('is-invalid');
      if (invalidFeedback) invalidFeedback.style.display = 'block';
      fileInput.focus();
    } else {
      fileInput.classList.remove('is-invalid');
      if (invalidFeedback) invalidFeedback.style.display = 'none';
    }
  });

  // Hide error when user selects a file
  fileInput.addEventListener('change', function() {
    if (fileInput.files && fileInput.files[0]) {
      fileInput.classList.remove('is-invalid');
      if (invalidFeedback) invalidFeedback.style.display = 'none';
    }
  });
});
</script>

<!-- Upload/Download Files Modal -->
<div class="modal fade" id="uploadFilesModal" tabindex="-1" aria-labelledby="uploadFilesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="uploadFilesModalLabel">Upload Project Permits & Clearances</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2 mb-3" style="font-size: 0.97rem;">
          Upload images for the following required permits and clearances for this project:
          <ul class="mb-0 ps-3">
            <li>LGU Permit</li>
            <li>Barangay Clearance</li>
            <li>Fire Clearance</li>
            <li>Occupancy Permit</li>
          </ul>
        </div>
        <form class="mb-3 single-upload-form" method="POST" action="upload_lgu.php" enctype="multipart/form-data">
          <input type="hidden" name="project_id" id="modal_project_id_lgu" value="<?php echo $project_id; ?>">
          <img id="preview_file_photo_lgu" class="img-thumbnail mb-2 d-none" style="max-width: 220px; max-height: 220px; display:block; margin:auto;" />
          <label class="form-label">LGU Permit</label>
          <input type="file" class="form-control file-input-preview" name="file_photo" accept="image/*">
          <div class="invalid-feedback">Please select a photo before uploading.</div>
          <button type="submit" class="btn btn-success mt-2">Upload LGU Permit</button>
        </form>
        <form class="mb-3 single-upload-form" method="POST" action="upload_barangay.php" enctype="multipart/form-data">
          <input type="hidden" name="project_id" id="modal_project_id_barangay" value="<?php echo $project_id; ?>">
          <img id="preview_file_photo_barangay" class="img-thumbnail mb-2 d-none" style="max-width: 220px; max-height: 220px; display:block; margin:auto;" />
          <label class="form-label">Barangay Clearance</label>
          <input type="file" class="form-control file-input-preview" name="file_photo" accept="image/*">
          <div class="invalid-feedback">Please select a photo before uploading.</div>
          <button type="submit" class="btn btn-success mt-2">Upload Barangay Clearance</button>
        </form>
        <form class="mb-3 single-upload-form" method="POST" action="upload_fire.php" enctype="multipart/form-data">
          <input type="hidden" name="project_id" id="modal_project_id_fire" value="<?php echo $project_id; ?>">
          <img id="preview_file_photo_fire" class="img-thumbnail mb-2 d-none" style="max-width: 220px; max-height: 220px; display:block; margin:auto;" />
          <label class="form-label">Fire Clearance</label>
          <input type="file" class="form-control file-input-preview" name="file_photo" accept="image/*">
          <div class="invalid-feedback">Please select a photo before uploading.</div>
          <button type="submit" class="btn btn-success mt-2">Upload Fire Clearance</button>
        </form>
        <form class="mb-3 single-upload-form" method="POST" action="upload_occupancy.php" enctype="multipart/form-data">
          <input type="hidden" name="project_id" id="modal_project_id_occupancy" value="<?php echo $project_id; ?>">
          <img id="preview_file_photo_occupancy" class="img-thumbnail mb-2 d-none" style="max-width: 220px; max-height: 220px; display:block; margin:auto;" />
          <label class="form-label">Occupancy Permit</label>
          <input type="file" class="form-control file-input-preview" name="file_photo" accept="image/*">
          <div class="invalid-feedback">Please select a photo before uploading.</div>
          <button type="submit" class="btn btn-success mt-2">Upload Occupancy Permit</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
// Set project_id for all forms in the modal when opening
const uploadFilesModal = document.getElementById('uploadFilesModal');
uploadFilesModal.addEventListener('show.bs.modal', function (event) {
  const triggerBtn = event.relatedTarget;
  let projectId = null;
  if (triggerBtn && triggerBtn.getAttribute('data-project-id')) {
    projectId = triggerBtn.getAttribute('data-project-id');
  } else {
    // fallback: try to get from a global or selection
    projectId = document.getElementById('modal_project_id')?.value || '';
  }
  document.getElementById('modal_project_id_lgu').value = projectId;
  document.getElementById('modal_project_id_barangay').value = projectId;
  document.getElementById('modal_project_id_fire').value = projectId;
  document.getElementById('modal_project_id_occupancy').value = projectId;
});
// Image preview for each file input
const previewMap = {
  'lgu': 'preview_file_photo_lgu',
  'barangay': 'preview_file_photo_barangay',
  'fire': 'preview_file_photo_fire',
  'occupancy': 'preview_file_photo_occupancy'
};
document.querySelectorAll('.single-upload-form').forEach(function(form) {
  const fileInput = form.querySelector('input[type="file"]');
  const formId = form.getAttribute('action').replace('upload_', '').replace('.php', '');
  const previewImg = document.getElementById('preview_file_photo_' + formId);
  fileInput.addEventListener('change', function() {
    if (fileInput.files && fileInput.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        previewImg.src = e.target.result;
        previewImg.classList.remove('d-none');
      };
      reader.readAsDataURL(fileInput.files[0]);
    } else {
      previewImg.classList.add('d-none');
      previewImg.src = '';
    }
  });
});
</script>

<?php if (isset($_GET['upload_success'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    showFeedbackModal(true, 'Files uploaded successfully.', '', 'upload_success');
});
</script>
<?php endif; ?>
<?php if (isset($_GET['upload_error'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    showFeedbackModal(false, 'Failed to upload files.', <?php echo json_encode(strip_tags($_GET['upload_error'])); ?>, 'upload_error');
});
</script>
<?php endif; ?>

<!-- Add JS for client-side validation of file input before upload -->
<script>
document.querySelectorAll('.single-upload-form').forEach(function(form) {
  const fileInput = form.querySelector('input[type="file"]');
  const invalidFeedback = fileInput.nextElementSibling; // the .invalid-feedback div

  form.addEventListener('submit', function(e) {
    if (!fileInput.files || !fileInput.files[0]) {
      e.preventDefault();
      fileInput.classList.add('is-invalid');
      if (invalidFeedback) invalidFeedback.style.display = 'block';
      fileInput.focus();
    } else {
      fileInput.classList.remove('is-invalid');
      if (invalidFeedback) invalidFeedback.style.display = 'none';
    }
  });

  // Hide error when user selects a file
  fileInput.addEventListener('change', function() {
    if (fileInput.files && fileInput.files[0]) {
      fileInput.classList.remove('is-invalid');
      if (invalidFeedback) invalidFeedback.style.display = 'none';
    }
  });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (window.location.search.includes('upload_success=1')) {
    setTimeout(function() {
      // Close the modal if open
      var modal = bootstrap.Modal.getInstance(document.getElementById('feedbackModal'));
      if (modal) modal.hide();
      // Refresh the page and remove the query param
      var url = new URL(window.location.href);
      url.searchParams.delete('upload_success');
      window.location.href = url.pathname + url.search;
    }, 1500);
  }
});
</script>
</body>

</html>