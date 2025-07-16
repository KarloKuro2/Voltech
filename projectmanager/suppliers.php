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

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    // Verify CSRF token if available
    if (isset($_SESSION['csrf_token']) && isset($_GET['csrf_token']) && 
        hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        
        $id = (int)$_GET['delete'];
        $sql = "DELETE FROM suppliers WHERE id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            header('Location: suppliers.php?success=delete');
        } else {
            $err = urlencode('Error deleting supplier: ' . $con->error);
            header('Location: suppliers.php?error=' . $err);
        }
        $stmt->close();
    } else {
        $err = urlencode('Invalid request. Please try again.');
        header('Location: suppliers.php?error=' . $err);
    }
    // Clear CSRF token after use
    unset($_SESSION['csrf_token']);
    exit();
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

// Handle add supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    $supplier_name = mysqli_real_escape_string($con, $_POST['supplier_name']);
    $contact_person = mysqli_real_escape_string($con, $_POST['contact_person']);
    $contact_number = mysqli_real_escape_string($con, $_POST['contact_number']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $address = mysqli_real_escape_string($con, $_POST['address']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $now = date('Y-m-d H:i:s');
    $insert_sql = "INSERT INTO suppliers (supplier_name, contact_person, contact_number, email, address, status, created_at, updated_at) VALUES ('$supplier_name', '$contact_person', '$contact_number', '$email', '$address', '$status', '$now', '$now')";
    if ($con->query($insert_sql)) {
        header('Location: suppliers.php?success=add');
    } else {
        $err = urlencode('Error adding supplier: ' . $con->error);
        header('Location: suppliers.php?error=' . $err);
    }
    exit();
}
// Handle edit supplier (update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_supplier'])) {
    $id = (int)$_POST['edit_supplier_id'];
    $supplier_name = mysqli_real_escape_string($con, $_POST['supplier_name']);
    $contact_person = mysqli_real_escape_string($con, $_POST['contact_person']);
    $contact_number = mysqli_real_escape_string($con, $_POST['contact_number']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $address = mysqli_real_escape_string($con, $_POST['address']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $now = date('Y-m-d H:i:s');
    $update_sql = "UPDATE suppliers SET supplier_name='$supplier_name', contact_person='$contact_person', contact_number='$contact_number', email='$email', address='$address', status='$status', updated_at='$now' WHERE id=$id";
    if ($con->query($update_sql)) {
        header('Location: suppliers.php?success=edit');
    } else {
        $err = urlencode('Error updating supplier: ' . $con->error);
        header('Location: suppliers.php?error=' . $err);
    }
    exit();
}

// Pagination
$results_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $results_per_page;

// Get total number of records
$sql = "SELECT COUNT(id) AS total FROM suppliers";
$result = $con->query($sql);
$row = $result->fetch_assoc();
$total_records = $row['total'];
$total_pages = ceil($total_records / $results_per_page);

// Fetch suppliers with pagination
$sql = "SELECT * FROM suppliers WHERE approval = 'Approved' ORDER BY id DESC LIMIT $start_from, $results_per_page";
$result = $con->query($sql);


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
    <title>Project Manager Suppliers</title>
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
                    <h2 class="fs-2 m-0">Suppliers</h2>
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
                        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
                            <h4 class="mb-0">Supplier Lists</h4>
                        </div>
                        <hr>
                        <form class="mb-3" method="get" action="" id="searchForm" style="max-width:400px;">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" name="search" placeholder="Search supplier, contact, or email" value="<?php echo htmlspecialchars(isset($_GET['search']) ? $_GET['search'] : ''); ?>" id="searchInput" autocomplete="off">
                            </div>
                        </form>
                       
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="bg-success text-white">
                                    <tr>
                                        <th>No.</th>
                                        <th>Supplier Name</th>
                                        <th>Contact Person</th>
                                        <th>Email</th>
                                        <th>Contact Number</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = $start_from + 1; ?>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['contact_person']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No suppliers found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                              <ul class="pagination justify-content-center">
                                <li class="page-item<?php if($page <= 1) echo ' disabled'; ?>">
                                  <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode(isset($_GET['search']) ? $_GET['search'] : ''); ?>">Previous</a>
                                </li>
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                  <li class="page-item<?php if($i == $page) echo ' active'; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode(isset($_GET['search']) ? $_GET['search'] : ''); ?>"><?php echo $i; ?></a>
                                  </li>
                                <?php endfor; ?>
                                <li class="page-item<?php if($page >= $total_pages) echo ' disabled'; ?>">
                                  <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode(isset($_GET['search']) ? $_GET['search'] : ''); ?>">Next</a>
                                </li>
                              </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Add Supplier Modal -->
                <!-- Edit Supplier Modal (structure like Add, fields prefilled by JS) -->
                <div class="modal fade" id="editSupplierModal" tabindex="-1" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="editSupplierModalLabel">Edit Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <form action="suppliers.php" method="POST">
                        <input type="hidden" name="edit_supplier_id" id="edit_supplier_id">
                        <div class="modal-body">
                          <div class="row">
                            <div class="col-md-6">
                              <div class="form-group mb-3">
                                <label>Supplier Name *</label>
                                <input type="text" class="form-control" name="supplier_name" id="edit_supplier_name" required>
                              </div>
                              <div class="form-group mb-3">
                                <label>Contact Person</label>
                                <input type="text" class="form-control" name="contact_person" id="edit_contact_person">
                              </div>
                              <div class="form-group mb-3">
                                <label>Contact Number</label>
                                <input type="text" class="form-control" name="contact_number" id="edit_contact_number">
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-group mb-3">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email">
                              </div>
                              <div class="form-group mb-3">
                                <label>Address</label>
                                <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                              </div>
                              <div class="form-group mb-3">
                                <label>Status *</label>
                                <select class="form-control" name="status" id="edit_status" required>
                                  <option value="Active">Active</option>
                                  <option value="Inactive">Inactive</option>
                                </select>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="modal-footer justify-content-end">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" name="edit_supplier" class="btn btn-success">Update Supplier</button>
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
            </div>
        </div>
    </div>

    <!-- Add Supplier Modal -->
    <div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addSupplierModalLabel">Add New Supplier</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="suppliers.php" method="POST">
            <div class="modal-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label>Supplier Name *</label>
                    <input type="text" class="form-control" name="supplier_name" required>
                  </div>
                  <div class="form-group mb-3">
                    <label>Contact Person</label>
                    <input type="text" class="form-control" name="contact_person">
                  </div>
                  <div class="form-group mb-3">
                    <label>Contact Number</label>
                    <input type="text" class="form-control" name="contact_number">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label>Email</label>
                    <input type="email" class="form-control" name="email">
                  </div>
                  <div class="form-group mb-3">
                    <label>Address</label>
                    <div class="row g-2">
                      <div class="col-12 mb-2">
                        <select class="form-select" id="add_region" required><option value="">Select Region</option></select>
                      </div>
                      <div class="col-12 mb-2">
                        <select class="form-select" id="add_province" required disabled><option value="">Select Province</option></select>
                      </div>
                      <div class="col-12 mb-2">
                        <select class="form-select" id="add_city" required disabled><option value="">Select City/Municipality</option></select>
                      </div>
                      <div class="col-12 mb-2">
                        <select class="form-select" id="add_barangay" required disabled><option value="">Select Barangay</option></select>
                      </div>
                    </div>
                    <input type="hidden" name="address" id="add_address_hidden">
                  </div>
                  <div class="form-group mb-3">
                    <label>Status *</label>
                    <select class="form-control" name="status" required disabled>
                      <option value="Active" selected>Active</option>
                    </select>
                    <input type="hidden" name="status" value="Active">
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" name="add_supplier" class="btn btn-success">Add Supplier</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="supplierName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>
        </div>
    </div>
    <!-- /#page-content-wrapper -->
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
    <script>
    </script>
    <script>
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
    </script>
    <script>
// Edit Supplier Modal fill (vanilla JS)
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.edit-supplier-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.getElementById('edit_supplier_id').value = this.getAttribute('data-id');
      document.getElementById('edit_supplier_name').value = this.getAttribute('data-name');
      document.getElementById('edit_contact_person').value = this.getAttribute('data-person');
      document.getElementById('edit_contact_number').value = this.getAttribute('data-number');
      document.getElementById('edit_email').value = this.getAttribute('data-email');
      document.getElementById('edit_address').value = this.getAttribute('data-address');
      document.getElementById('edit_status').value = this.getAttribute('data-status');
      var modal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
      modal.show();
    });
  });

  // Delete Supplier Modal logic
  document.querySelectorAll('.delete-supplier-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var supplierId = this.getAttribute('data-id');
      var supplierName = this.getAttribute('data-name');
      document.getElementById('supplierName').textContent = supplierName;
      var confirmDelete = document.getElementById('confirmDelete');
      // Add CSRF token to delete link
      var csrf = '<?php if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } echo $_SESSION['csrf_token']; ?>';
      confirmDelete.setAttribute('href', '?delete=' + supplierId + '&csrf_token=' + csrf);
      var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
      modal.show();
    });
  });
});
// Feedback Modal logic (like employee_list.php)
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
<?php if (isset($_GET['success']) && $_GET['success'] === 'add'): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, 'Supplier added successfully!', '', 'success');
});
<?php elseif (isset($_GET['success']) && $_GET['success'] === 'edit'): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, 'Supplier updated successfully!', '', 'success');
});
<?php elseif (isset($_GET['success']) && $_GET['success'] === 'delete'): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, 'Supplier deleted successfully!', '', 'success');
});
<?php elseif (isset($_GET['error'])): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(false, decodeURIComponent('<?php echo $_GET['error']; ?>'), '', 'error');
});
<?php endif; ?>
// Search debounce (vanilla JS)
document.addEventListener('DOMContentLoaded', function() {
  var searchInput = document.getElementById('searchInput');
  var searchForm = document.getElementById('searchForm');
  if (searchInput && searchForm) {
    var searchTimeout;
    searchInput.addEventListener('input', function() {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(function() {
        searchForm.submit();
      }, 400);
    });
  }
});
document.addEventListener('DOMContentLoaded', function() {
  var feedbackModalEl = document.getElementById('feedbackModal');
  if (feedbackModalEl) {
    feedbackModalEl.addEventListener('hidden.bs.modal', function () {
      // Move focus to the Add Supplier button if available, else to body
      var addBtn = document.querySelector('.btn-success[data-bs-target="#addSupplierModal"]');
      if (addBtn) {
        addBtn.focus();
      } else {
        document.body.focus();
      }
    });
  }
});
</script>
<script>
// --- Supplier Modal Validation ---
function filterSupplierNameInput(e) {
  let value = e.target.value;
  // Allow letters, numbers, spaces, . , - & ()
  value = value.replace(/[^A-Za-z0-9 .,'\-()]/g, '');
  if (value.length > 50) value = value.slice(0, 50);
  e.target.value = value;
}
function filterContactPersonInput(e) {
  let value = e.target.value;
  value = value.replace(/[^A-Za-z ]+/g, '');
  if (value.length > 50) value = value.slice(0, 50);
  e.target.value = value;
}
function filterContactNumberInput(e) {
  let value = e.target.value.replace(/\D/g, '');
  if (value.length > 11) value = value.slice(0, 11);
  e.target.value = value;
}
function filterEmailInput(e) {
  let value = e.target.value;
  if (value.length > 50) value = value.slice(0, 50);
  e.target.value = value;
}
function filterAddressInput(e) {
  let value = e.target.value;
  if (value.length > 100) value = value.slice(0, 100);
  e.target.value = value;
}
// Add Modal
var addForm = document.querySelector('#addSupplierModal form');
if (addForm) {
  var addName = addForm.querySelector('input[name="supplier_name"]');
  var addPerson = addForm.querySelector('input[name="contact_person"]');
  var addNumber = addForm.querySelector('input[name="contact_number"]');
  var addEmail = addForm.querySelector('input[name="email"]');
  var addAddress = addForm.querySelector('textarea[name="address"]');
  var addStatus = addForm.querySelector('select[name="status"]');
  if (addName) addName.addEventListener('input', filterSupplierNameInput);
  if (addPerson) addPerson.addEventListener('input', filterContactPersonInput);
  if (addNumber) addNumber.addEventListener('input', filterContactNumberInput);
  if (addEmail) addEmail.addEventListener('input', filterEmailInput);
  if (addAddress) addAddress.addEventListener('input', filterAddressInput);
  addForm.addEventListener('submit', function(e) {
    // Supplier Name: required, max 50, allowed chars
    if (!addName.value.trim()) {
      addName.setCustomValidity('Supplier Name is required.');
      addName.reportValidity();
      e.preventDefault(); return;
    } else if (addName.value.length > 50) {
      addName.setCustomValidity('Supplier Name must be at most 50 characters.');
      addName.reportValidity();
      e.preventDefault(); return;
    } else {
      addName.setCustomValidity('');
    }
    // Contact Person: optional, only letters/spaces, max 50
    if (addPerson.value && !/^[A-Za-z ]+$/.test(addPerson.value)) {
      addPerson.setCustomValidity('Contact Person must only contain letters and spaces.');
      addPerson.reportValidity();
      e.preventDefault(); return;
    } else if (addPerson.value.length > 50) {
      addPerson.setCustomValidity('Contact Person must be at most 50 characters.');
      addPerson.reportValidity();
      e.preventDefault(); return;
    } else {
      addPerson.setCustomValidity('');
    }
    // Contact Number: optional, if filled must be 11 digits and start with 09
    if (addNumber.value && !/^09\d{9}$/.test(addNumber.value)) {
      addNumber.setCustomValidity('Contact Number must start with 09 and be exactly 11 digits.');
      addNumber.reportValidity();
      e.preventDefault(); return;
    } else {
      addNumber.setCustomValidity('');
    }
    // Email: optional, if filled must be valid
    if (addEmail.value && !/^([A-Za-z0-9._%+-]+)@([A-Za-z0-9.-]+)\.([A-Za-z]{2,})$/.test(addEmail.value)) {
      addEmail.setCustomValidity('Email must be a valid email address.');
      addEmail.reportValidity();
      e.preventDefault(); return;
    } else {
      addEmail.setCustomValidity('');
    }
    // Address: max 100
    if (addAddress.value.length > 100) {
      addAddress.setCustomValidity('Address must be at most 100 characters.');
      addAddress.reportValidity();
      e.preventDefault(); return;
    } else {
      addAddress.setCustomValidity('');
    }
    // Status: required
    if (!addStatus.value) {
      addStatus.setCustomValidity('Status is required.');
      addStatus.reportValidity();
      e.preventDefault(); return;
    } else {
      addStatus.setCustomValidity('');
    }
  });
  [addName, addPerson, addNumber, addEmail, addAddress, addStatus].forEach(function(input) {
    if (input) input.addEventListener('input', function() { input.setCustomValidity(''); });
  });
}
// Edit Modal
var editForm = document.querySelector('#editSupplierModal form');
if (editForm) {
  var editName = editForm.querySelector('input[name="supplier_name"]');
  var editPerson = editForm.querySelector('input[name="contact_person"]');
  var editNumber = editForm.querySelector('input[name="contact_number"]');
  var editEmail = editForm.querySelector('input[name="email"]');
  var editAddress = editForm.querySelector('textarea[name="address"]');
  var editStatus = editForm.querySelector('select[name="status"]');
  if (editName) editName.addEventListener('input', filterSupplierNameInput);
  if (editPerson) editPerson.addEventListener('input', filterContactPersonInput);
  if (editNumber) editNumber.addEventListener('input', filterContactNumberInput);
  if (editEmail) editEmail.addEventListener('input', filterEmailInput);
  if (editAddress) editAddress.addEventListener('input', filterAddressInput);
  editForm.addEventListener('submit', function(e) {
    // Supplier Name: required, max 50, allowed chars
    if (!editName.value.trim()) {
      editName.setCustomValidity('Supplier Name is required.');
      editName.reportValidity();
      e.preventDefault(); return;
    } else if (editName.value.length > 50) {
      editName.setCustomValidity('Supplier Name must be at most 50 characters.');
      editName.reportValidity();
      e.preventDefault(); return;
    } else {
      editName.setCustomValidity('');
    }
    // Contact Person: optional, only letters/spaces, max 50
    if (editPerson.value && !/^[A-Za-z ]+$/.test(editPerson.value)) {
      editPerson.setCustomValidity('Contact Person must only contain letters and spaces.');
      editPerson.reportValidity();
      e.preventDefault(); return;
    } else if (editPerson.value.length > 50) {
      editPerson.setCustomValidity('Contact Person must be at most 50 characters.');
      editPerson.reportValidity();
      e.preventDefault(); return;
    } else {
      editPerson.setCustomValidity('');
    }
    // Contact Number: optional, if filled must be 11 digits and start with 09
    if (editNumber.value && !/^09\d{9}$/.test(editNumber.value)) {
      editNumber.setCustomValidity('Contact Number must start with 09 and be exactly 11 digits.');
      editNumber.reportValidity();
      e.preventDefault(); return;
    } else {
      editNumber.setCustomValidity('');
    }
    // Email: optional, if filled must be valid
    if (editEmail.value && !/^([A-Za-z0-9._%+-]+)@([A-Za-z0-9.-]+)\.([A-Za-z]{2,})$/.test(editEmail.value)) {
      editEmail.setCustomValidity('Email must be a valid email address.');
      editEmail.reportValidity();
      e.preventDefault(); return;
    } else {
      editEmail.setCustomValidity('');
    }
    // Address: max 100
    if (editAddress.value.length > 100) {
      editAddress.setCustomValidity('Address must be at most 100 characters.');
      editAddress.reportValidity();
      e.preventDefault(); return;
    } else {
      editAddress.setCustomValidity('');
    }
    // Status: required
    if (!editStatus.value) {
      editStatus.setCustomValidity('Status is required.');
      editStatus.reportValidity();
      e.preventDefault(); return;
    } else {
      editStatus.setCustomValidity('');
    }
  });
  [editName, editPerson, editNumber, editEmail, editAddress, editStatus].forEach(function(input) {
    if (input) input.addEventListener('input', function() { input.setCustomValidity(''); });
  });
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // --- Contact Number and Email Auto-fill for Add Modal ---
  var addNumber = document.querySelector('#addSupplierModal input[name="contact_number"]');
  var addEmail = document.querySelector('#addSupplierModal input[name="email"]');
  var addSupplierModal = document.getElementById('addSupplierModal');
  if (addSupplierModal) {
    addSupplierModal.addEventListener('show.bs.modal', function() {
      if (addNumber && !addNumber.value) addNumber.value = '09';
      if (addEmail && !addEmail.value) addEmail.value = '@gmail.com';
    });
  }
  if (addNumber) {
    addNumber.addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');
      if (!value.startsWith('09')) value = '09' + value.replace(/^0+/, '').replace(/^9+/, '');
      if (value.length > 11) value = value.slice(0, 11);
      e.target.value = value;
    });
    addNumber.addEventListener('focus', function(e) {
      if (!e.target.value.startsWith('09')) e.target.value = '09';
    });
  }
  if (addEmail) {
    addEmail.addEventListener('input', function(e) {
      let value = e.target.value;
      let atGmail = value.indexOf('@gmail.com');
      if (atGmail !== -1) value = value.substring(0, atGmail);
      value = value.replace(/[^A-Za-z0-9._-]/g, '');
      e.target.value = value + '@gmail.com';
    });
    addEmail.addEventListener('focus', function(e) {
      let value = e.target.value;
      if (!value.endsWith('@gmail.com')) {
        value = value.split('@')[0];
        e.target.value = value + '@gmail.com';
      }
    });
  }

  // --- Contact Number and Email Auto-fill for Edit Modal ---
  var editNumber = document.querySelector('#editSupplierModal input[name="contact_number"]');
  var editEmail = document.querySelector('#editSupplierModal input[name="email"]');
  var editSupplierModal = document.getElementById('editSupplierModal');
  if (editSupplierModal) {
    editSupplierModal.addEventListener('show.bs.modal', function() {
      if (editNumber && !editNumber.value) editNumber.value = '09';
      if (editEmail && !editEmail.value) editEmail.value = '@gmail.com';
    });
  }
  if (editNumber) {
    editNumber.addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');
      if (!value.startsWith('09')) value = '09' + value.replace(/^0+/, '').replace(/^9+/, '');
      if (value.length > 11) value = value.slice(0, 11);
      e.target.value = value;
    });
    editNumber.addEventListener('focus', function(e) {
      if (!e.target.value.startsWith('09')) e.target.value = '09';
    });
  }
  if (editEmail) {
    editEmail.addEventListener('input', function(e) {
      let value = e.target.value;
      let atGmail = value.indexOf('@gmail.com');
      if (atGmail !== -1) value = value.substring(0, atGmail);
      value = value.replace(/[^A-Za-z0-9._-]/g, '');
      e.target.value = value + '@gmail.com';
    });
    editEmail.addEventListener('focus', function(e) {
      let value = e.target.value;
      if (!value.endsWith('@gmail.com')) {
        value = value.split('@')[0];
        e.target.value = value + '@gmail.com';
      }
    });
  }
});
</script>
<script>
async function loadPhilippinesJSON() {
  const response = await fetch('philippines.json');
  return await response.json();
}
function setDropdownOptions(select, options, placeholder) {
  select.innerHTML = `<option value="">${placeholder}</option>`;
  options.forEach(opt => {
    const option = document.createElement('option');
    option.value = opt;
    option.textContent = opt;
    select.appendChild(option);
  });
}
function setDropdownDisabled(select, disabled) {
  select.disabled = disabled;
  if (disabled) select.value = '';
}
function updateAddressHidden(region, province, city, barangay, hiddenInput) {
  if (region && province && city && barangay) {
    hiddenInput.value = `${barangay}, ${city}, ${province}, ${region}`;
  } else {
    hiddenInput.value = '';
  }
}
document.addEventListener('DOMContentLoaded', function() {
  loadPhilippinesJSON().then(data => {
    // --- Add Modal ---
    const addRegion = document.getElementById('add_region');
    const addProvince = document.getElementById('add_province');
    const addCity = document.getElementById('add_city');
    const addBarangay = document.getElementById('add_barangay');
    const addAddressHidden = document.getElementById('add_address_hidden');
    // Populate regions
    setDropdownOptions(addRegion, Object.values(data).map(r => r.region_name), 'Select Region');
    addRegion.addEventListener('change', function() {
      setDropdownDisabled(addProvince, true);
      setDropdownDisabled(addCity, true);
      setDropdownDisabled(addBarangay, true);
      addProvince.innerHTML = '<option value="">Select Province</option>';
      addCity.innerHTML = '<option value="">Select City/Municipality</option>';
      addBarangay.innerHTML = '<option value="">Select Barangay</option>';
      if (!this.value) return;
      // Find region code
      let regionCode = Object.keys(data).find(code => data[code].region_name === this.value);
      let provinces = Object.keys(data[regionCode].province_list);
      setDropdownOptions(addProvince, provinces, 'Select Province');
      setDropdownDisabled(addProvince, false);
    });
    addProvince.addEventListener('change', function() {
      setDropdownDisabled(addCity, true);
      setDropdownDisabled(addBarangay, true);
      addCity.innerHTML = '<option value="">Select City/Municipality</option>';
      addBarangay.innerHTML = '<option value="">Select Barangay</option>';
      if (!this.value || !addRegion.value) return;
      let regionCode = Object.keys(data).find(code => data[code].region_name === addRegion.value);
      let cities = Object.keys(data[regionCode].province_list[this.value].municipality_list);
      setDropdownOptions(addCity, cities, 'Select City/Municipality');
      setDropdownDisabled(addCity, false);
    });
    addCity.addEventListener('change', function() {
      setDropdownDisabled(addBarangay, true);
      addBarangay.innerHTML = '<option value="">Select Barangay</option>';
      if (!this.value || !addRegion.value || !addProvince.value) return;
      let regionCode = Object.keys(data).find(code => data[code].region_name === addRegion.value);
      let barangays = data[regionCode].province_list[addProvince.value].municipality_list[this.value].barangay_list;
      setDropdownOptions(addBarangay, barangays, 'Select Barangay');
      setDropdownDisabled(addBarangay, false);
    });
    [addRegion, addProvince, addCity, addBarangay].forEach(sel => {
      sel.addEventListener('change', function() {
        updateAddressHidden(addRegion.value, addProvince.value, addCity.value, addBarangay.value, addAddressHidden);
      });
    });
    // --- Edit Modal ---
    const editRegion = document.getElementById('edit_region');
    const editProvince = document.getElementById('edit_province');
    const editCity = document.getElementById('edit_city');
    const editBarangay = document.getElementById('edit_barangay');
    const editAddressHidden = document.getElementById('edit_address_hidden');
    setDropdownOptions(editRegion, Object.values(data).map(r => r.region_name), 'Select Region');
    editRegion.addEventListener('change', function() {
      setDropdownDisabled(editProvince, true);
      setDropdownDisabled(editCity, true);
      setDropdownDisabled(editBarangay, true);
      editProvince.innerHTML = '<option value="">Select Province</option>';
      editCity.innerHTML = '<option value="">Select City/Municipality</option>';
      editBarangay.innerHTML = '<option value="">Select Barangay</option>';
      if (!this.value) return;
      let regionCode = Object.keys(data).find(code => data[code].region_name === this.value);
      let provinces = Object.keys(data[regionCode].province_list);
      setDropdownOptions(editProvince, provinces, 'Select Province');
      setDropdownDisabled(editProvince, false);
    });
    editProvince.addEventListener('change', function() {
      setDropdownDisabled(editCity, true);
      setDropdownDisabled(editBarangay, true);
      editCity.innerHTML = '<option value="">Select City/Municipality</option>';
      editBarangay.innerHTML = '<option value="">Select Barangay</option>';
      if (!this.value || !editRegion.value) return;
      let regionCode = Object.keys(data).find(code => data[code].region_name === editRegion.value);
      let cities = Object.keys(data[regionCode].province_list[this.value].municipality_list);
      setDropdownOptions(editCity, cities, 'Select City/Municipality');
      setDropdownDisabled(editCity, false);
    });
    editCity.addEventListener('change', function() {
      setDropdownDisabled(editBarangay, true);
      editBarangay.innerHTML = '<option value="">Select Barangay</option>';
      if (!this.value || !editRegion.value || !editProvince.value) return;
      let regionCode = Object.keys(data).find(code => data[code].region_name === editRegion.value);
      let barangays = data[regionCode].province_list[editProvince.value].municipality_list[this.value].barangay_list;
      setDropdownOptions(editBarangay, barangays, 'Select Barangay');
      setDropdownDisabled(editBarangay, false);
    });
    [editRegion, editProvince, editCity, editBarangay].forEach(sel => {
      sel.addEventListener('change', function() {
        updateAddressHidden(editRegion.value, editProvince.value, editCity.value, editBarangay.value, editAddressHidden);
      });
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