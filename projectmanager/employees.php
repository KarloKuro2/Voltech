<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
  session_start();
  $con = new mysqli("localhost", "root", "", "voltech2");
  if ($con->connect_error) {
      $response = ['success' => false, 'message' => 'Database connection failed.'];
      header('Content-Type: application/json');
      echo json_encode($response);
      exit();
  }
  $userid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
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

$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';

// Build filter for SQL
$filter_sql = '';
if ($search !== '') {
    $filter_sql = "AND (e.first_name LIKE '%$search%' OR e.last_name LIKE '%$search%' OR p.title LIKE '%$search%')";
}

// Count total employees for pagination
$count_query = "SELECT COUNT(*) as total FROM employees e JOIN positions p ON e.position_id = p.position_id WHERE e.user_id = '$userid' $filter_sql";
$count_result = mysqli_query($con, $count_query);
$total_employees = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_employees / $limit);

// Handle employee actions
if (isset($_POST['add_employee'])) {
    $first_name = mysqli_real_escape_string($con, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($con, $_POST['last_name']);
    $position_id = mysqli_real_escape_string($con, $_POST['position_id']);
    $contact_number = mysqli_real_escape_string($con, $_POST['contact_number']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    
    // Insert new employee
    $query = "INSERT INTO employees (user_id, first_name, last_name, position_id, contact_number, email) 
              VALUES ('$userid', '$first_name', '$last_name', '$position_id', '$contact_number', '$email')";
    if (mysqli_query($con, $query)) {
        header("Location: employees.php?success=add");
    } else {
        $err = urlencode(mysqli_error($con));
        header("Location: employees.php?error=$err");
    }
    exit();
}

if (isset($_POST['update_employee'])) {
    $employee_id = mysqli_real_escape_string($con, $_POST['employee_id']);
    $first_name = mysqli_real_escape_string($con, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($con, $_POST['last_name']);
    $position_id = mysqli_real_escape_string($con, $_POST['position_id']);
    $contact_number = mysqli_real_escape_string($con, $_POST['contact_number']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    
    // Update employee
    $query = "UPDATE employees SET first_name = '$first_name', last_name = '$last_name', 
              position_id = '$position_id', contact_number = '$contact_number', email = '$email' 
              WHERE employee_id = '$employee_id' AND user_id = '$userid'";
    if (mysqli_query($con, $query)) {
        header("Location: employees.php?success=edit");
    } else {
        $err = urlencode(mysqli_error($con));
        header("Location: employees.php?error=$err");
    }
    exit();
}

if (isset($_GET['delete'])) {
    $employee_id = mysqli_real_escape_string($con, $_GET['delete']);
    
    // Delete employee
    $query = "DELETE FROM employees WHERE employee_id = '$employee_id' AND user_id = '$userid'";
    if (mysqli_query($con, $query)) {
        header("Location: employees.php?success=delete");
    } else {
        $err = urlencode(mysqli_error($con));
        header("Location: employees.php?error=$err");
    }
    exit();
}

// Initialize edit variables
$edit_mode = false;
$edit_employee_id = '';
$edit_first_name = '';
$edit_last_name = '';
$edit_position_id = '';
$edit_contact_number = '';
$edit_email = '';

// If in edit mode, fetch employee details
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $edit_employee_id = mysqli_real_escape_string($con, $_GET['edit']);
    
    $edit_query = "SELECT * FROM employees WHERE employee_id = '$edit_employee_id' AND user_id = '$userid'";
    $edit_result = mysqli_query($con, $edit_query);
    
    if (mysqli_num_rows($edit_result) > 0) {
        $edit_data = mysqli_fetch_assoc($edit_result);
        $edit_first_name = $edit_data['first_name'];
        $edit_last_name = $edit_data['last_name'];
        $edit_position_id = $edit_data['position_id'];
        $edit_contact_number = $edit_data['contact_number'];
        $edit_email = $edit_data['email'];
    }
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
    <title>Project Manager Employees</title>
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
                    <h2 class="fs-2 m-0">Employees</h2>
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

            <div class="container-fluid px-4 py-4">
                <div class="card mb-5 shadow rounded-3">
                  <div class="card-body">
                    <div class="mb-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
                      <h4 class="mb-0">Employee Management</h4>
                      <button type="button" class="btn btn-success ms-auto" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                        <i class="fas fa-plus"></i> Add New Employee
                      </button>
                    </div>
                    <hr>
                    <form class="d-flex flex-grow-1 mb-3" method="get" action="" id="searchForm" style="min-width:260px; max-width:400px;">
                      <div class="input-group w-100">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" name="search" placeholder="Search name or position" value="<?php echo htmlspecialchars($search); ?>" id="searchInput" autocomplete="off">
                      </div>
                    </form>
                    <div class="table-responsive mb-0">
                      <table class="table table-bordered table-striped mb-0">
                        <thead class="thead-dark">
                          <tr>
                            <th>No.</th>
                            <th>Employee Name</th>
                            <th>Position</th>
                            <th>Contact</th>
                            <th class="text-center">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          // Fetch all employees with position details (with filter, limit, offset)
                          $query = "SELECT e.*, p.title as position_title, p.daily_rate FROM employees e JOIN positions p ON e.position_id = p.position_id WHERE e.user_id = '$userid' $filter_sql ORDER BY e.last_name ASC LIMIT $limit OFFSET $offset";
                          $result = mysqli_query($con, $query);
                          $no = $offset + 1;
                          if(mysqli_num_rows($result) > 0) {
                              while($row = mysqli_fetch_assoc($result)) {
                          ?>
                          <tr>
                            <td><?php echo $no++; ?></td>
                            <td class="emp-name"><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                            <td class="emp-position"><?php echo $row['position_title']; ?></td>
                            <td class="emp-contact" data-number="<?php echo $row['contact_number']; ?>" data-email="<?php echo $row['email']; ?>">
                              <?php 
                              if (!empty($row['contact_number'])) {
                                  echo '<i class="fas fa-phone-alt"></i> ' . $row['contact_number'] . '<br>';
                              }
                              if (!empty($row['email'])) {
                                  echo '<i class="fas fa-envelope"></i> ' . $row['email'];
                              }
                              ?>
                            </td>
                            <td class="text-center">
                              <div class="action-buttons">
                                <a href="#" class="btn btn-warning btn-sm text-dark edit-employee-btn" data-id="<?php echo $row['employee_id']; ?>" data-first="<?php echo htmlspecialchars($row['first_name']); ?>" data-last="<?php echo htmlspecialchars($row['last_name']); ?>" data-position="<?php echo $row['position_id']; ?>">
                                  <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="#" class="btn btn-danger btn-sm text-white delete-employee-btn" data-id="<?php echo $row['employee_id']; ?>" data-name="<?php echo $row['first_name'] . ' ' . $row['last_name']; ?>">
                                  <i class="fas fa-trash"></i> Delete
                                </a>
                              </div>
                            </td>
                          </tr>
                          <?php
                              }
                          } else {
                          ?>
                          <tr>
                            <td colspan="5" class="text-center">No employees found</td>
                          </tr>
                          <?php } ?>
                        </tbody>
                      </table>
                    </div>
                    <nav aria-label="Page navigation" class="mt-3 mb-3">
                      <ul class="pagination justify-content-center custom-pagination-green mb-0">
                        <li class="page-item<?php if($page <= 1) echo ' disabled'; ?>">
                          <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                        </li>
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item<?php if($i == $page) echo ' active'; ?>">
                          <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item<?php if($page >= $total_pages) echo ' disabled'; ?>">
                          <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                        </li>
                      </ul>
                    </nav>
                  </div>
                </div>
            </div>
            </div>
        </div>
    </div>
    <!-- /#page-content-wrapper -->
    </div>

    <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addEmployeeModalLabel">Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="" method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="modal_first_name"><b>First Name</b></label>
                                    <input type="text" class="form-control" id="modal_first_name" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="modal_last_name"><b>Last Name</b></label>
                                    <input type="text" class="form-control" id="modal_last_name" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="modal_position_id"><b>Position</b></label>
                            <select class="form-control" id="modal_position_id" name="position_id" required>
                                <option value="">Select Position</option>
                                <?php
                                // Fetch all positions
                                $positions_query = "SELECT * FROM positions ORDER BY title ASC";
                                $positions_result = mysqli_query($con, $positions_query);
                                
                                while($position = mysqli_fetch_assoc($positions_result)) {
                                    echo "<option value='{$position['position_id']}'>{$position['title']} (₱{$position['daily_rate']})</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="modal_contact_number"><b>Contact Number</b></label>
                                    <input type="text" class="form-control" id="modal_contact_number" name="contact_number">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="modal_email"><b>Email Address</b></label>
                                    <input type="email" class="form-control" id="modal_email" name="email" placeholder="example@gmail.com" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_employee" class="btn btn-success">Add Employee</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editEmployeeModalLabel">Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editEmployeeForm" action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="employee_id" id="edit_employee_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="edit_first_name"><b>First Name</b></label>
                                    <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="edit_last_name"><b>Last Name</b></label>
                                    <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="edit_position_id"><b>Position</b></label>
                            <select class="form-control" id="edit_position_id" name="position_id" required>
                                <option value="">Select Position</option>
                                <?php
                                $positions_query = "SELECT * FROM positions ORDER BY title ASC";
                                $positions_result = mysqli_query($con, $positions_query);
                                while($position = mysqli_fetch_assoc($positions_result)) {
                                    echo "<option value='{$position['position_id']}'>{$position['title']} (₱{$position['daily_rate']})</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="edit_contact_number"><b>Contact Number</b></label>
                                    <input type="text" class="form-control" id="edit_contact_number" name="contact_number">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="edit_email"><b>Email Address</b></label>
                                    <input type="email" class="form-control" id="edit_email" name="email">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_employee" class="btn btn-success">Update Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   
    <script>
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
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
  // Remove the query param after showing the modal
  if (paramToRemove) {
    removeQueryParam(paramToRemove);
  }
}
// Show feedback modal if redirected after add, update, delete, or error
<?php if (isset($_GET['success']) && $_GET['success'] === 'add'): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, 'Employee added successfully!', '', 'success');
});
<?php elseif (isset($_GET['success']) && $_GET['success'] === 'edit'): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, 'Employee updated successfully!', '', 'success');
});
<?php elseif (isset($_GET['success']) && $_GET['success'] === 'delete'): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, 'Employee deleted successfully!', '', 'success');
});
<?php elseif (isset($_GET['error'])): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(false, decodeURIComponent('<?php echo $_GET['error']; ?>'), '', 'error');
});
<?php endif; ?>
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.edit-employee-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      document.getElementById('edit_employee_id').value = this.getAttribute('data-id');
      document.getElementById('edit_first_name').value = this.getAttribute('data-first');
      document.getElementById('edit_last_name').value = this.getAttribute('data-last');
      document.getElementById('edit_position_id').value = this.getAttribute('data-position');
      // Optionally, fetch contact/email from row if you want to prefill those too
      var row = this.closest('tr');
      if (row) {
        var contactCell = row.querySelector('.emp-contact');
        if (contactCell) {
          document.getElementById('edit_contact_number').value = contactCell.getAttribute('data-number') || '';
          document.getElementById('edit_email').value = contactCell.getAttribute('data-email') || '';
        }
      }
      var modal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
      modal.show();
    });
  });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // --- Add & Edit Modal ---
  function filterFirstNameInput(e) {
    let value = e.target.value;
    value = value.replace(/[^A-Za-z ]+/g, '');
    value = value.replace(/^ +/, '');
    if (value.length > 30) value = value.slice(0, 30);
    e.target.value = value;
  }
  function filterLastNameInput(e) {
    let value = e.target.value;
    value = value.replace(/[^A-Za-z]+/g, '');
    if (value.length > 30) value = value.slice(0, 30);
    e.target.value = value;
  }
  // Contact Number: must start with 09, only numbers, exactly 11 digits
  function filterContactInput(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (!value.startsWith('09')) value = '09' + value.replace(/^0+/, '').replace(/^9+/, '');
    if (value.length > 11) value = value.slice(0, 11);
    e.target.value = value;
  }
  function enforce09OnFocus(e) {
    if (!e.target.value.startsWith('09')) e.target.value = '09';
  }
  // Email: must always end with @gmail.com
  function filterEmailInput(e) {
    let value = e.target.value;
    let atGmail = value.indexOf('@gmail.com');
    if (atGmail !== -1) value = value.substring(0, atGmail);
    value = value.replace(/[^A-Za-z0-9._-]/g, '');
    e.target.value = value + '@gmail.com';
  }
  function enforceGmailOnFocus(e) {
    let value = e.target.value;
    if (!value.endsWith('@gmail.com')) {
      value = value.split('@')[0];
      e.target.value = value + '@gmail.com';
    }
  }
  // --- Add Modal ---
  var addFirst = document.getElementById('modal_first_name');
  var addLast = document.getElementById('modal_last_name');
  var addContact = document.getElementById('modal_contact_number');
  var addEmail = document.getElementById('modal_email');
  var addForm = addFirst && addLast && addContact && addEmail ? addFirst.closest('form') : null;
  if (addFirst) addFirst.addEventListener('input', filterFirstNameInput);
  if (addLast) addLast.addEventListener('input', filterLastNameInput);
  if (addContact) {
    addContact.addEventListener('input', filterContactInput);
    addContact.addEventListener('focus', enforce09OnFocus);
  }
  if (addEmail) {
    addEmail.addEventListener('input', filterEmailInput);
    addEmail.addEventListener('focus', enforceGmailOnFocus);
    if (!addEmail.value.endsWith('@gmail.com')) addEmail.value = '@gmail.com';
  }
  if (addForm) {
    addForm.addEventListener('submit', function(e) {
      // First Name: must have at least 2 letters, no leading space, max 30 chars
      let firstVal = addFirst.value;
      let letterCount = (firstVal.match(/[A-Za-z]/g) || []).length;
      if (/^ /.test(firstVal) || letterCount < 2) {
        addFirst.setCustomValidity('First name must start with a letter and contain at least 2 letters.');
        addFirst.reportValidity();
        e.preventDefault();
        return;
      } else {
        addFirst.setCustomValidity('');
      }
      if (firstVal.length > 30) {
        addFirst.setCustomValidity('First name must be at most 30 characters.');
        addFirst.reportValidity();
        e.preventDefault();
        return;
      }
      // Last Name: must not be empty, only letters, max 30 chars
      if (!addLast.value) {
        addLast.setCustomValidity('Last name is required and must contain only letters.');
        addLast.reportValidity();
        e.preventDefault();
        return;
      } else {
        addLast.setCustomValidity('');
      }
      if (addLast.value.length > 30) {
        addLast.setCustomValidity('Last name must be at most 30 characters.');
        addLast.reportValidity();
        e.preventDefault();
        return;
      }
      // Contact: must be 11 digits and start with 09
      if (!/^09\d{9}$/.test(addContact.value)) {
        addContact.setCustomValidity('Contact number must start with 09 and be exactly 11 digits.');
        addContact.reportValidity();
        e.preventDefault();
        return;
      } else {
        addContact.setCustomValidity('');
      }
      // Email: must end with @gmail.com
      if (!/^([A-Za-z0-9._-]+)@gmail\.com$/.test(addEmail.value)) {
        addEmail.setCustomValidity('Email must be a valid Gmail address ending with @gmail.com.');
        addEmail.reportValidity();
        e.preventDefault();
        return;
      } else {
        addEmail.setCustomValidity('');
      }
    });
    [addFirst, addLast, addContact, addEmail].forEach(function(input) {
      if (input) input.addEventListener('input', function() { input.setCustomValidity(''); });
    });
  }
  // --- Edit Modal ---
  var editFirst = document.getElementById('edit_first_name');
  var editLast = document.getElementById('edit_last_name');
  var editContact = document.getElementById('edit_contact_number');
  var editEmail = document.getElementById('edit_email');
  var editForm = editFirst && editLast && editContact && editEmail ? editFirst.closest('form') : null;
  if (editFirst) editFirst.addEventListener('input', filterFirstNameInput);
  if (editLast) editLast.addEventListener('input', filterLastNameInput);
  if (editContact) {
    editContact.addEventListener('input', filterContactInput);
    editContact.addEventListener('focus', enforce09OnFocus);
  }
  if (editEmail) {
    editEmail.addEventListener('input', filterEmailInput);
    editEmail.addEventListener('focus', enforceGmailOnFocus);
    if (!editEmail.value.endsWith('@gmail.com')) editEmail.value = '@gmail.com';
  }
  if (editForm) {
    editForm.addEventListener('submit', function(e) {
      // First Name: must have at least 2 letters, no leading space, max 30 chars
      let firstVal = editFirst.value;
      let letterCount = (firstVal.match(/[A-Za-z]/g) || []).length;
      if (/^ /.test(firstVal) || letterCount < 2) {
        editFirst.setCustomValidity('First name must start with a letter and contain at least 2 letters.');
        editFirst.reportValidity();
        e.preventDefault();
        return;
      } else {
        editFirst.setCustomValidity('');
      }
      if (firstVal.length > 30) {
        editFirst.setCustomValidity('First name must be at most 30 characters.');
        editFirst.reportValidity();
        e.preventDefault();
        return;
      }
      // Last Name: must not be empty, only letters, max 30 chars
      if (!editLast.value) {
        editLast.setCustomValidity('Last name is required and must contain only letters.');
        editLast.reportValidity();
        e.preventDefault();
        return;
      } else {
        editLast.setCustomValidity('');
      }
      if (editLast.value.length > 30) {
        editLast.setCustomValidity('Last name must be at most 30 characters.');
        editLast.reportValidity();
        e.preventDefault();
        return;
      }
      // Contact: must be 11 digits and start with 09
      if (!/^09\d{9}$/.test(editContact.value)) {
        editContact.setCustomValidity('Contact number must start with 09 and be exactly 11 digits.');
        editContact.reportValidity();
        e.preventDefault();
        return;
      } else {
        editContact.setCustomValidity('');
      }
      // Email: must end with @gmail.com
      if (!/^([A-Za-z0-9._-]+)@gmail\.com$/.test(editEmail.value)) {
        editEmail.setCustomValidity('Email must be a valid Gmail address ending with @gmail.com.');
        editEmail.reportValidity();
        e.preventDefault();
        return;
      } else {
        editEmail.setCustomValidity('');
      }
    });
    [editFirst, editLast, editContact, editEmail].forEach(function(input) {
      if (input) input.addEventListener('input', function() { input.setCustomValidity(''); });
    });
  }
});
</script>
<div class="modal fade" id="deleteEmployeeModal" tabindex="-1" aria-labelledby="deleteEmployeeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteEmployeeModalLabel">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete <strong id="employeeName"></strong>?</p>
        <p class="text-danger">This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="confirmDeleteEmployee" class="btn btn-danger">Delete</a>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.delete-employee-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var empId = this.getAttribute('data-id');
      var empName = this.getAttribute('data-name');
      document.getElementById('employeeName').textContent = empName;
      var confirmDelete = document.getElementById('confirmDeleteEmployee');
      confirmDelete.setAttribute('href', '?delete=' + empId);
      var modal = new bootstrap.Modal(document.getElementById('deleteEmployeeModal'));
      modal.show();
    });
  });
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
</body>

</html>