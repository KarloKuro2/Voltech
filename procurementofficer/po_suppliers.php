<?php
    session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
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

// --- Change Password Backend Handler (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    header('Content-Type: application/json');
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
    echo json_encode($response);
    exit();
}

// User profile image fetch block (restored)
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

// Pagination variables (restored)
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
    <link rel="stylesheet" href="po_styles.css" />
    <title>Procurement Officer Suppliers</title>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-white" id="sidebar-wrapper">
        <div class="user text-center py-4">
                <img class="img img-fluid rounded-circle mb-2 sidebar-profile-img" src="<?php echo $userprofile; ?>" width="70" alt="User Profile">
                <h5 class="mb-1 text-white"><?php echo htmlspecialchars($user_name); ?></h5>
                <p class="text-white small mb-0"><?php echo htmlspecialchars($user_email); ?></p>
                <hr style="border-top: 1px solid #fff; opacity: 0.3; margin: 12px 0 0 0;">
            </div>
            <div class="list-group list-group-flush ">
                <a href="po_dashboard.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'po_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>Dashboard
                </a>
                <a href="po_orders.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'po_orders.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice"></i>Orders
                </a>
                <a class="list-group-item list-group-item-action bg-transparent second-text d-flex justify-content-between align-items-center <?php echo ($current_page == 'po_equipment.php' || $current_page == 'po_materials.php' || $current_page == 'po_warehouse_materials.php') ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#inventoryCollapse" role="button" aria-expanded="<?php echo ($current_page == 'po_equipment.php' || $current_page == 'po_materials.php' || $current_page == 'po_warehouse_materials.php') ? 'true' : 'false'; ?>" aria-controls="inventoryCollapse">
                    <span><i class="fas fa-boxes"></i>Inventory</span>
                    <i class="fas fa-caret-down"></i>
                </a>
                <div class="collapse <?php echo ($current_page == 'po_equipment.php' || $current_page == 'po_materials.php' || $current_page == 'po_warehouse_materials.php') ? 'show' : ''; ?>" id="inventoryCollapse">
                    <a href="po_equipment.php" class="list-group-item list-group-item-action bg-transparent second-text ps-5 <?php echo $current_page == 'po_equipment.php' ? 'active' : ''; ?>">
                        <i class="fas fa-wrench"></i> Equipment
                    </a>
                    <a href="po_materials.php" class="list-group-item list-group-item-action bg-transparent second-text ps-5 <?php echo $current_page == 'po_materials.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cubes"></i> Materials
                    </a>
                    <a href="po_warehouse_materials.php" class="list-group-item list-group-item-action bg-transparent second-text ps-5 <?php echo $current_page == 'po_warehouse_materials.php' ? 'active' : ''; ?>">
                        <i class="fas fa-warehouse"></i> Warehouse
                    </a>
                </div>
                <a href="po_suppliers.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'po_suppliers.php' ? 'active' : ''; ?>">
                    <i class="fas fa-truck"></i>Suppliers
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
                        <?php include 'po_notification.php'; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle second-text fw-bold" href="#" id="navbarDropdown"
                                role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php echo htmlspecialchars($user_name); ?>
                                <img src="<?php echo $userprofile; ?>" alt="User" class="rounded-circle" width="30" height="30" style="margin-left: 8px;">
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="po_profile.php">Profile</a></li>
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
                            <h4 class="mb-0">Supplier Management</h4>
                            <button type="button" class="btn btn-success ms-auto" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                <i class="fas fa-plus"></i> Add New Supplier
                            </button>
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
                                        <th class="text-center">Actions</th>
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
                                                <td class="text-center">
                                                    <div class="action-buttons">
                                                        <button type="button" class="btn btn-warning btn-sm text-dark edit-supplier-btn" 
                                                            data-id="<?php echo $row['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($row['supplier_name']); ?>"
                                                            data-person="<?php echo htmlspecialchars($row['contact_person']); ?>"
                                                            data-number="<?php echo htmlspecialchars($row['contact_number']); ?>"
                                                            data-email="<?php echo htmlspecialchars($row['email']); ?>"
                                                            data-address="<?php echo htmlspecialchars($row['address']); ?>"
                                                            data-status="<?php echo htmlspecialchars($row['status']); ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <a href="delete_supplier.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm text-white delete-supplier-btn" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['supplier_name']); ?>">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No suppliers found</td>
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
                <!-- Add Supplier Modal (same as before, but right-aligned buttons) -->
                <!-- Edit Supplier Modal (structure like Add, fields prefilled by JS) -->
                <div class="modal fade" id="editSupplierModal" tabindex="-1" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="editSupplierModalLabel">Edit Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <form action="update_supplier.php" method="POST">
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
          <form action="add_supplier.php" method="POST">
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
    <script src="po_suppliers.js"></script>
    <script>
    function showFeedbackModal(success, message, details, action) {
  var icon = document.getElementById('feedbackIcon');
  var title = document.getElementById('feedbackTitle');
  var msg = document.getElementById('feedbackMessage');
  if (success) {
            icon.innerHTML = '<i class="fas fa-check-circle" style="color:#28a745;"></i>';
    title.textContent = 'Success!';
    msg.textContent = message;
  } else {
            icon.innerHTML = '<i class="fas fa-times-circle" style="color:#dc3545;"></i>';
    title.textContent = 'Error!';
            msg.textContent = message;
  }
  var feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
  feedbackModal.show();
        // Remove query param from URL after showing
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    (function() {
      var params = new URLSearchParams(window.location.search);
      if (params.get('success') === '1') {
        showFeedbackModal(true, 'Supplier added successfully!', '', 'added');
      } else if (params.get('updated') === '1') {
        showFeedbackModal(true, 'Supplier updated successfully!', '', 'updated');
      } else if (params.get('deleted') === '1') {
        showFeedbackModal(true, 'Supplier deleted successfully!', '', 'deleted');
      }
    })();
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
      xhr.open('POST', 'change_password.php', true);
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
</body>

</html>