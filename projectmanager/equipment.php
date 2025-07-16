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

// Handle add equipment
// Handle edit equipment
// Handle delete action


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
// Remove add equipment logic
// Remove edit equipment logic
// Remove delete equipment logic

// Get filter values
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : '';

// Build WHERE clause
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(equipment_name LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $where_conditions[] = "status = '$status_filter'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Get total number of records with filters
$total_query = "SELECT COUNT(*) as total FROM equipment $where_clause";
$total_result = $con->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_items = $total_row['total'];
$total_pages = ceil($total_items / $items_per_page);

// Add summary card queries
$total_equipment_query = $con->query("SELECT COUNT(*) as total FROM equipment");
$total_equipment = $total_equipment_query->fetch_assoc();

$available_query = $con->query("SELECT COUNT(*) as total FROM equipment WHERE status = 'Available'");
$available = $available_query->fetch_assoc();

$inuse_query = $con->query("SELECT COUNT(*) as total FROM equipment WHERE status = 'In Use'");
$inuse = $inuse_query->fetch_assoc();

$maintenance_query = $con->query("SELECT COUNT(*) as total FROM equipment WHERE status = 'Maintenance'");
$maintenance = $maintenance_query->fetch_assoc();

// Get distinct status values for filter
$statuses_query = "SELECT DISTINCT status FROM equipment ORDER BY status";
$statuses = $con->query($statuses_query);

// Fetch equipment with pagination and filters
if (!empty($where_clause)) {
    $sql = "SELECT * FROM equipment $where_clause AND approval = 'Approved' ORDER BY id DESC LIMIT $offset, $items_per_page";
} else {
    $sql = "SELECT * FROM equipment WHERE approval = 'Approved' ORDER BY id DESC LIMIT $offset, $items_per_page";
}
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
    <title>Project Manager Equipments</title>
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
                    <h2 class="fs-2 m-0">Equipment</h2>
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
                <!-- Equipment Summary Cards -->
                <!-- End Equipment Summary Cards -->
                <div class="card mb-5 shadow rounded-3">
                    <div class="card-body">
                        <div class="mb-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
                            <h4 class="mb-0">Equipment List</h4>
                        </div>
                        <hr>
                        <form class="d-flex flex-wrap gap-2 mb-3" method="get" action="" id="searchForm" style="min-width:260px; max-width:600px;">
                            <div class="input-group" style="min-width:220px; max-width:320px;">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" name="search" placeholder="Search equipment name" value="<?php echo htmlspecialchars($search); ?>" id="searchInput" autocomplete="off">
                                </div>
                            <select name="status" class="form-control" style="max-width:180px;" id="statusFilter">
                                        <option value="">All Status</option>
                                        <?php 
                                        $statuses->data_seek(0); // Reset the pointer
                                        while ($status = $statuses->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($status['status']); ?>" <?php echo ($status_filter == $status['status']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($status['status']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                        </form>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var searchInput = document.getElementById('searchInput');
                            var statusFilter = document.getElementById('statusFilter');
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
                            if (statusFilter && searchForm) {
                                statusFilter.addEventListener('change', function() {
                                    searchForm.submit();
                                });
                            }
                        });
                        </script>
                        <div class="table-responsive mb-0">
                            <table class="table table-bordered table-striped mb-0">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>No.</th>
                                        <th>Equipment Name</th>
                                        <th>Equipment Price</th>
                                        <th>Depreciation</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = $offset + 1;
                                    if ($result->num_rows > 0): 
                                        while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($row['equipment_name']); ?></td>
                                                <td><?php echo isset($row['equipment_price']) ? number_format($row['equipment_price'], 2) : ''; ?></td>
                                                <td><?php echo isset($row['depreciation']) ? htmlspecialchars($row['depreciation']) . ' years' : ''; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo ($row['status'] == 'Available') ? 'success' : 'warning'; ?>">
                                                        <?php echo htmlspecialchars($row['status']); ?>
                                                    </span>
                                                </td>
                                        <td class="text-center">
                                            <div class="action-buttons">
                                                <a href="#" class="btn btn-sm btn-primary text-white font-weight-bold" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['id']; ?>">
                                                    <i class="fas fa-eye"></i> View More
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No equipment found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <nav aria-label="Page navigation" class="mt-3 mb-3">
                            <ul class="pagination justify-content-center custom-pagination-green mb-0">
                                <li class="page-item<?php if($page <= 1) echo ' disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>">Previous</a>
                                                </li>
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item<?php if($i == $page) echo ' active'; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                <li class="page-item<?php if($page >= $total_pages) echo ' disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>">Next</a>
                                                </li>
                                        </ul>
                                    </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View and Edit Modals -->
    <?php 
    // Reset the result pointer
    $result->data_seek(0);
    
    while ($row = $result->fetch_assoc()): 
    ?>
    <!-- Redesign the View Equipment Modal to match the card-based layout in materials.php -->
    <div class="modal fade" id="viewModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalLabel<?php echo $row['id']; ?>">Equipment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container-fluid">
                        <div class="row mb-3">
                            <div class="col-md-6 mb-2">
                                <h4 class="fw-bold mb-0 text-primary"><i class="fas fa-wrench me-2"></i><?php echo htmlspecialchars($row['equipment_name']); ?></h4>
                            </div>
                            <div class="col-md-6 mb-2 text-md-end">
                                <span class="fw-bold text-secondary"><i class="fas fa-info-circle me-1"></i>Status:</span> <span class="badge bg-<?php echo ($row['status'] == 'Available') ? 'success' : (($row['status'] == 'In Use') ? 'warning' : (($row['status'] == 'Maintenance') ? 'info' : 'secondary')); ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6 mb-2">
                                <span class="fw-bold text-secondary"><i class="fas fa-hourglass-half me-1"></i>Depreciation:</span> <?php echo isset($row['depreciation']) ? htmlspecialchars($row['depreciation']) . ' years' : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="row justify-content-center">
                            <div class="col-12 col-md-10">
                                <div class="card shadow-sm border-0 mb-2">
                                    <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
                                        <div class="mb-2 flex-fill">
                                            <span class="fw-bold text-muted"><i class="fas fa-coins me-1"></i>Equipment Price:</span> ₱ <?php echo isset($row['equipment_price']) ? number_format($row['equipment_price'], 2) : 'N/A'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteEquipmentModal" tabindex="-1" aria-labelledby="deleteEquipmentModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteEquipmentModalLabel">Confirm Delete</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete <strong id="equipmentName"></strong>?</p>
            <p class="text-danger">This action cannot be undone.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <a href="#" id="confirmDeleteEquipment" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>
            </div>
        </div>
    </div>
    <!-- /#page-content-wrapper -->
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
      showFeedbackModal(true, 'Equipment added successfully!', '', 'success');
    });
    <?php elseif (isset($_GET['success']) && $_GET['success'] === 'edit'): ?>
    document.addEventListener('DOMContentLoaded', function() {
      showFeedbackModal(true, 'Equipment updated successfully!', '', 'success');
    });
    <?php elseif (isset($_GET['success']) && $_GET['success'] === 'delete'): ?>
    document.addEventListener('DOMContentLoaded', function() {
      showFeedbackModal(true, 'Equipment deleted successfully!', '', 'success');
    });
    <?php elseif (isset($_GET['error'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
      showFeedbackModal(false, decodeURIComponent('<?php echo $_GET['error']; ?>'), '', 'error');
    });
    <?php endif; ?>
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    const expensesCtx = document.getElementById('expensesChart').getContext('2d');
    const expensesChart = new Chart(expensesCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Total Expenses',
                data: [12000, 15000, 10000, 18000, 20000, 17000],
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true } }
        }
    });

    const projectCostCtx = document.getElementById('projectCostChart').getContext('2d');
    const projectCostChart = new Chart(projectCostCtx, {
        type: 'bar',
        data: {
            labels: ['House 1', 'House 2', 'House 3'],
            datasets: [
                {
                    label: 'Estimated Cost ($)',
                    data: [80000, 60000, 90000],
                    backgroundColor: 'rgba(54, 162, 235, 0.7)'
                },
                {
                    label: 'Previous Cost ($)',
                    data: [95000, 70000, 100000],
                    backgroundColor: 'rgba(255, 99, 132, 0.7)'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true } }
        }
    });
    </script>
    <script>
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.delete-equipment-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var eqId = this.getAttribute('data-id');
      var eqName = this.getAttribute('data-name');
      document.getElementById('equipmentName').textContent = eqName;
      var confirmDelete = document.getElementById('confirmDeleteEquipment');
      confirmDelete.setAttribute('href', 'equipment.php?delete=' + eqId);
      var modal = new bootstrap.Modal(document.getElementById('deleteEquipmentModal'));
      modal.show();
    });
  });
});
</script>
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