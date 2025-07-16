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

// Before deleting a project, delete all related project_divisions
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Delete child rows first
    mysqli_query($con, "DELETE FROM project_divisions WHERE project_id='$id'");
    // Now delete the project
    mysqli_query($con, "DELETE FROM projects WHERE project_id='$id'");
    header("Location: project_archived.php?deleted=1");
    exit();
}
// Handle restore (update archived=0)
if (isset($_GET['restore'])) {
    $restore_id = intval($_GET['restore']);
    mysqli_query($con, "UPDATE projects SET archived=0 WHERE project_id='$restore_id' AND user_id='$userid'");
    header("Location: project_archived.php?restored=1");
    exit();
}

// --- FILTER LOGIC ---
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$where = "user_id='$userid' AND archived=1";
if ($filter === 'month') {
    $where .= " AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
} elseif ($filter === 'year') {
    $where .= " AND YEAR(created_at) = YEAR(CURDATE())";
} elseif ($filter === 'custom' && $start_date && $end_date) {
    $where .= " AND DATE(created_at) BETWEEN '" . mysqli_real_escape_string($con, $start_date) . "' AND '" . mysqli_real_escape_string($con, $end_date) . "'";
}
// Fetch archived projects with filter
$query = mysqli_query($con, "SELECT * FROM projects WHERE $where");

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
    <title>Projects Archived</title>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-white" id="sidebar-wrapper">
        <div class="user text-center py-4">
                <img class="img img-fluid rounded-circle mb-2 sidebar-profile-img" src="<?php echo isset($userprofile) ? $userprofile : (isset($_SESSION['userprofile']) ? $_SESSION['userprofile'] : '../uploads/default_profile.png'); ?>" width="70" alt="User Profile">
                <h5 class="mb-1 text-white"><?php echo htmlspecialchars($user_name); ?></h5>
                <p class="text-white small mb-0"><?php echo htmlspecialchars($user_email); ?></p>
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
                    <h2 class="fs-2 m-0">Archived Projects</h2>
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

            <div class="container-fluid px-2 px-md-4 py-3">
                <div class="row">
                    <div class="col-12">
                        <div class="card mb-5 shadow rounded-3">
                            <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="mb-0">List of Archived Projects</h4>
                                    <a href="projects.php" class="btn btn-secondary ms-2">Back to Project List</a>
            </div>
                                <hr>
                                <form class="d-flex align-items-center gap-2 mb-3 flex-wrap" method="get" id="filterForm" style="gap: 8px;">
                                    <select name="filter" id="filter" class="form-control" style="width: 140px; min-width: 100px; max-width: 180px;">
                    <option value="all" <?php echo ($filter === 'all') ? 'selected' : ''; ?>>All</option>
                    <option value="month" <?php echo ($filter === 'month') ? 'selected' : ''; ?>>This Month</option>
                    <option value="year" <?php echo ($filter === 'year') ? 'selected' : ''; ?>>This Year</option>
                </select>
                                    <div class="d-flex align-items-center" style="min-width: 180px;">
                                        <label for="start_date" class="mb-0 me-1" style="font-weight:normal; font-size:0.95em;">Start:</label>
                                        <input type="date" name="start_date" id="start_date" class="form-control" style="width: 130px;" value="<?php echo htmlspecialchars($start_date); ?>">
                                    </div>
                                    <div class="d-flex align-items-center" style="min-width: 180px;">
                                        <label for="end_date" class="mb-0 me-1" style="font-weight:normal; font-size:0.95em;">End:</label>
                                        <input type="date" name="end_date" id="end_date" class="form-control" style="width: 130px;" value="<?php echo htmlspecialchars($end_date); ?>">
                                    </div>
                                    <!-- No Apply Filter button -->
            </form>
                                <div class="table-responsive mb-0">
                                    <table class="table table-bordered table-striped mb-0">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Project</th>
                        <th>Location</th>
                        <th>Deadline</th>
                        <th>Foreman</th>
                        <th>Category</th>
                        <th>Billings</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                $modals = '';
                if (mysqli_num_rows($query) > 0) {
                    mysqli_data_seek($query, 0);
                    while ($row = mysqli_fetch_assoc($query)) {
                        $pid = $row['project_id'];
                        // Employees
                        $emps = [];
                        $emp_total = 0;
                        $emp_query = mysqli_query($con, "SELECT pae.*, e.first_name, e.last_name FROM project_add_employee pae LEFT JOIN employees e ON pae.employee_id = e.employee_id WHERE pae.project_id = '$pid'");
                        while ($erow = mysqli_fetch_assoc($emp_query)) {
                            $emps[] = $erow;
                            $emp_total += floatval($erow['total']);
                        }
                        // MaterialsF
                        $mats = [];
                        $mat_total = 0;
                        $mat_query = mysqli_query($con, "SELECT * FROM project_add_materials WHERE project_id = '$pid'");
                        while ($mrow = mysqli_fetch_assoc($mat_query)) {
                            $mats[] = $mrow;
                            $mat_total += floatval($mrow['total']);
                        }
                        $grand_total = $emp_total + $mat_total;
?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($row['project']); ?></td>
                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                        <td><?php echo htmlspecialchars($row['deadline']); ?></td>
                        <td><?php echo htmlspecialchars($row['foreman']); ?></td>
                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                        <td><?php echo htmlspecialchars($row['billings']); ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm view-project" data-project-id="<?php echo $pid; ?>">
                                <i data-feather="eye"></i> View
                            </button>
                        </td>
                    </tr>
<?php
// Collect modal HTML for output after the table
$modals .= '<div class="modal fade" id="viewProjectModal' . $pid . '" tabindex="-1" role="dialog">'
    . '<div class="modal-dialog modal-lg" role="document">'
    . '<div class="modal-content">'
    . '<div class="modal-header bg-primary text-white">'
    . '<h5 class="modal-title"><i data-feather="folder" class="mr-2"></i>Project Details</h5>'
    . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
    . '</div>'
    . '<div class="modal-body">'
    . '<div class="card mb-4">'
    . '<div class="card-header bg-light">'
    . '<h6 class="mb-0"><i data-feather="info" class="mr-2"></i>Project Information</h6>'
    . '</div>'
    . '<div class="card-body">'
    . '<div class="row">'
    . '<div class="col-md-6">'
    . '<div class="mb-3">'
    . '<strong class="text-primary">Project Name:</strong><br>'
    . '<span class="h6">' . htmlspecialchars($row['project']) . '</span>'
    . '</div>'
    . '<div class="mb-3">'
    . '<strong class="text-primary">Location:</strong><br>'
    . '<span>' . htmlspecialchars($row['location']) . '</span>'
    . '</div>'
    . '<div class="mb-3">'
    . '<strong class="text-primary">Category:</strong><br>'
    . '<span class="badge badge-secondary">' . htmlspecialchars($row['category']) . '</span>'
    . '</div>'
    . '</div>'
    . '<div class="col-md-6">'
    . '<div class="mb-3">'
    . '<strong class="text-primary">Deadline:</strong><br>'
    . '<span class="text-danger">' . date("F d, Y", strtotime($row['deadline'])) . '</span>'
    . '</div>'
    . '<div class="mb-3">'
    . '<strong class="text-primary">Foreman:</strong><br>'
    . '<span>' . htmlspecialchars($row['foreman']) . '</span>'
    . '</div>'
    . '<div class="mb-3">'
    . '<strong class="text-primary">Billings:</strong><br>'
    . '<span class="h6 text-success">₱' . number_format(floatval($row['billings']), 2) . '</span>'
    . '</div>'
    . '</div>'
    . '</div>'
    . '</div>'
    . '</div>'
    . '<div class="row">'
    . '<div class="col-md-6">'
    . '<div class="card">'
    . '<div class="card-header bg-light">'
    . '<h6 class="mb-0"><i data-feather="users" class="mr-2"></i>Employees</h6>'
    . '</div>'
    . '<div class="card-body">';
if (count($emps) > 0) {
    $modals .= '<ul class="list-group list-group-flush">';
    foreach ($emps as $emp) {
        $modals .= '<li class="list-group-item d-flex justify-content-between align-items-center border-0 px-0" style="background: #fff !important; color: #222; font-weight: bold;">'
            . '<div style="font-weight:bold;color:#222">'
            . '<i data-feather="user" class="mr-2 text-muted" style="width: 16px; height: 16px;"></i>'
            . htmlspecialchars(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))
            . '</div>'
            . '<span style="font-weight:bold;color:#222">₱' . number_format($emp['total'], 2) . '</span>'
            . '</li>';
    }
    $modals .= '</ul>';
} else {
    $modals .= '<div class="text-muted text-center py-3"><i data-feather="users" class="mr-2"></i>No employees added</div>';
}
$modals .= '<div class="text-right mt-3 pt-2 border-top">'
    . '<span style="font-weight:bold;color:#222">Total: ₱' . number_format($emp_total, 2) . '</span>'
    . '</div>'
    . '</div>'
    . '</div>'
    . '</div>'
    . '<div class="col-md-6">'
    . '<div class="card">'
    . '<div class="card-header bg-light">'
    . '<h6 class="mb-0"><i data-feather="package" class="mr-2"></i>Materials</h6>'
    . '</div>'
    . '<div class="card-body">';
if (count($mats) > 0) {
    $modals .= '<ul class="list-group list-group-flush">';
    foreach ($mats as $mat) {
        $modals .= '<li class="list-group-item d-flex justify-content-between align-items-center border-0 px-0" style="background: #fff !important; color: #222; font-weight: bold;">'
            . '<div style="font-weight:bold;color:#222">'
            . '<i data-feather="box" class="mr-2 text-muted" style="width: 16px; height: 16px;"></i>'
            . htmlspecialchars($mat['material_name'])
            . '</div>'
            . '<span style="font-weight:bold;color:#222">₱' . number_format($mat['total'], 2) . '</span>'
            . '</li>';
    }
    $modals .= '</ul>';
} else {
    $modals .= '<div class="text-muted text-center py-3"><i data-feather="package" class="mr-2"></i>No materials added</div>';
}
$modals .= '<div class="text-right mt-3 pt-2 border-top">'
    . '<span style="font-weight:bold;color:#222">Total: ₱' . number_format($mat_total, 2) . '</span>'
    . '</div>'
    . '</div>'
    . '</div>'
    . '</div>'
    . '</div>'
    . '<div class="text-center mt-3 mb-0" style="font-size:1.2em; font-weight:bold; color:#222;">'
    . '<span style="font-size:1.3em; vertical-align:middle; margin-right:4px; font-weight:bold; color:#222;">₱</span> Grand Total: <span style="font-weight:bold;color:#222">₱' . number_format($grand_total, 2) . '</span>'
    . '</div>'
    . '</div>';
$modals .= '<div class="modal-footer">'
    . '<button class="btn btn-success restore-project" data-project-id="' . $pid . '"><i data-feather="refresh-cw" class="mr-1"></i>Restore</button>'
    . '<button class="btn btn-danger delete-project" data-project-id="' . $pid . '"><i data-feather="trash-2" class="mr-1"></i>Delete</button>'
    . '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i data-feather="x" class="mr-1"></i>Close</button>'
    . '</div></div></div></div>';
    }
} else {
    echo '<tr><td colspan="8" class="text-center">No archived projects found.</td></tr>';
}
?>
</tbody>
</table>
<?php echo $modals; ?>
<!-- Confirmation Modal for Restore/Delete -->
<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-labelledby="confirmActionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmActionModalLabel">Confirm Action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="confirmActionText"></p>
        <p class="text-danger" id="confirmActionWarning" style="display:none;">This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="confirmActionBtn" class="btn">Confirm</a>
      </div>
    </div>
  </div>
</div>
<!-- View Project Modal -->
<div class="modal fade" id="viewProjectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Project Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="projectDetailsBody">
                <!-- Project details will be loaded here by JS -->
            </div>
        </div>
    </div>
</div>
<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModalMain" tabindex="-1" aria-labelledby="changePasswordModalLabelMain" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="changePasswordModalLabelMain">Change Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="changePasswordFormMain">
          <div class="mb-3">
            <label for="current_password_main" class="form-label">Current Password</label>
            <input type="password" class="form-control" id="current_password_main" name="current_password" required>
          </div>
          <div class="mb-3">
            <label for="new_password_main" class="form-label">New Password</label>
            <input type="password" class="form-control" id="new_password_main" name="new_password" required>
          </div>
          <div class="mb-3">
            <label for="confirm_password_main" class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="confirm_password_main" name="confirm_password" required>
          </div>
          <div id="changePasswordFeedbackMain" class="mb-2"></div>
          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-success">Change Password</button>
          </div>
        </form>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script>
    var el = document.getElementById("wrapper");
    var toggleButton = document.getElementById("menu-toggle");

    toggleButton.onclick = function () {
        el.classList.toggle("toggled");
    };
    </script>
    <script>
    function fetchProjectDetails(project_id) {
        $.ajax({
            url: 'fetch_archived_project_details.php',
            type: 'GET',
            data: { id: project_id },
            success: function(data) {
                $('#projectDetailsBody').html(data);
                $('#viewProjectModal').modal('show');
            }
        });
    }
    document.addEventListener('DOMContentLoaded', function() {
        var filterSelect = document.getElementById('filter');
        var startDate = document.getElementById('start_date');
        var endDate = document.getElementById('end_date');
        var filterForm = document.getElementById('filterForm');
        if (filterSelect) {
            filterSelect.addEventListener('change', function() {
                filterForm.submit();
            });
        }
        if (startDate) {
            startDate.addEventListener('change', function() {
                filterForm.submit();
            });
        }
        if (endDate) {
            endDate.addEventListener('change', function() {
                filterForm.submit();
            });
        }

        // Fix view modal for Bootstrap 5
        document.querySelectorAll('.view-project').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var pid = this.getAttribute('data-project-id');
                var modal = document.getElementById('viewProjectModal' + pid);
                if (modal) {
                    var bsModal = new bootstrap.Modal(modal);
                    bsModal.show();
                }
            });
        });

        // Confirmation modal for restore/delete
        let actionType = '';
        let actionUrl = '';
        let currentProjectModal = null;
        document.querySelectorAll('.restore-project').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                actionType = 'restore';
                const pid = this.getAttribute('data-project-id');
                actionUrl = 'project_archived.php?restore=' + pid;
                document.getElementById('confirmActionModalLabel').textContent = 'Confirm Restore';
                document.getElementById('confirmActionText').textContent = 'Are you sure you want to restore this project?';
                document.getElementById('confirmActionWarning').style.display = 'none';
                const confirmBtn = document.getElementById('confirmActionBtn');
                confirmBtn.className = 'btn btn-success';
                confirmBtn.textContent = 'Restore';
                // Close the current project modal before showing confirm
                currentProjectModal = this.closest('.modal');
                if (currentProjectModal) {
                    var bsModal = bootstrap.Modal.getInstance(currentProjectModal);
                    if (bsModal) bsModal.hide();
                }
                setTimeout(function() {
                    var modal = new bootstrap.Modal(document.getElementById('confirmActionModal'));
                    modal.show();
                    confirmBtn.onclick = function() { window.location.href = actionUrl; };
                }, 300);
            });
        });
        document.querySelectorAll('.delete-project').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                actionType = 'delete';
                const pid = this.getAttribute('data-project-id');
                actionUrl = 'project_archived.php?delete=' + pid;
                document.getElementById('confirmActionModalLabel').textContent = 'Confirm Delete';
                document.getElementById('confirmActionText').textContent = 'Are you sure you want to permanently delete this project?';
                document.getElementById('confirmActionWarning').style.display = '';
                const confirmBtn = document.getElementById('confirmActionBtn');
                confirmBtn.className = 'btn btn-danger';
                confirmBtn.textContent = 'Delete';
                // Close the current project modal before showing confirm
                currentProjectModal = this.closest('.modal');
                if (currentProjectModal) {
                    var bsModal = bootstrap.Modal.getInstance(currentProjectModal);
                    if (bsModal) bsModal.hide();
                }
                setTimeout(function() {
                    var modal = new bootstrap.Modal(document.getElementById('confirmActionModal'));
                    modal.show();
                    confirmBtn.onclick = function() { window.location.href = actionUrl; };
                }, 300);
            });
        });
        // Fix close buttons in all modals for Bootstrap 5
        document.querySelectorAll('.modal .btn-close, .modal .close').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var modal = btn.closest('.modal');
                if (modal) {
                    var bsModal = bootstrap.Modal.getInstance(modal);
                    if (bsModal) bsModal.hide();
                }
            });
        });
        // Refresh feather icons after modals are shown
        document.querySelectorAll('.modal').forEach(function(modalEl) {
            modalEl.addEventListener('shown.bs.modal', function() {
                if (window.feather) feather.replace();
            });
        });
    });
    </script>
    <!-- Feedback Modal (copied from projects.php) -->
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
    function removeQueryParam(param) {
        const url = new URL(window.location);
        url.searchParams.delete(param);
        window.history.replaceState({}, document.title, url.pathname + url.search);
    }
    function showFeedbackModal(success, message, reason = '', paramToRemove = null) {
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
            msg.textContent = message + (reason ? ' Reason: ' + reason : '');
        }
        var feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
        feedbackModal.show();
        if (paramToRemove) {
            removeQueryParam(paramToRemove);
        }
    }
    <?php if (isset($_GET['restored'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showFeedbackModal(true, 'Project restored successfully.', '', 'restored');
    });
    <?php elseif (isset($_GET['deleted'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showFeedbackModal(true, 'Project deleted successfully.', '', 'deleted');
    });
    <?php endif; ?>
    </script>
<style>
.custom-pagination-green .page-item.active .page-link,
.custom-pagination-green .page-item .page-link:hover {
    background-color: #009d63;
    border-color: #009d63;
    color: #fff;
}
.custom-pagination-green .page-link {
    color: #009d63;
}
</style>
</body>
</html>