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
    $filter_sql = "WHERE title LIKE '%$search%'";
}

// Count total positions for pagination
$count_query = "SELECT COUNT(*) as total FROM positions $filter_sql";
$count_result = mysqli_query($con, $count_query);
$total_positions = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_positions / $limit);

// Handle position actions
if (isset($_POST['add_position'])) {
    $title = mysqli_real_escape_string($con, $_POST['title']);
    $daily_rate = mysqli_real_escape_string($con, $_POST['daily_rate']);
    
    // Insert new position
    $query = "INSERT INTO positions (title, daily_rate) VALUES ('$title', '$daily_rate')";
    mysqli_query($con, $query) or die(mysqli_error($con));
    header("Location: positions.php?success=1");
    exit();
}

if (isset($_POST['update_position'])) {
    $position_id = mysqli_real_escape_string($con, $_POST['position_id']);
    $title = mysqli_real_escape_string($con, $_POST['title']);
    $daily_rate = mysqli_real_escape_string($con, $_POST['daily_rate']);
    
    // Update position
    $query = "UPDATE positions SET title = '$title', daily_rate = '$daily_rate' WHERE position_id = '$position_id'";
    mysqli_query($con, $query) or die(mysqli_error($con));
    header("Location: positions.php?updated=1");
    exit();
}

if (isset($_GET['delete'])) {
    $position_id = mysqli_real_escape_string($con, $_GET['delete']);
    
    // Check if position is used by any employee
    $check_query = "SELECT COUNT(*) as count FROM employees WHERE position_id = '$position_id'";
    $check_result = mysqli_query($con, $check_query);
    $check_data = mysqli_fetch_assoc($check_result);
    
    if ($check_data['count'] > 0) {
        // Position is in use, show error
        header("Location: positions.php?error=1");
        exit();
    }
    
    // Delete position
    $query = "DELETE FROM positions WHERE position_id = '$position_id'";
    mysqli_query($con, $query) or die(mysqli_error($con));
    header("Location: positions.php?deleted=1");
    exit();
}

// Initialize edit variables
$edit_mode = false;
$edit_position_id = '';
$edit_title = '';
$edit_daily_rate = '';

// If in edit mode, fetch position details
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $edit_position_id = mysqli_real_escape_string($con, $_GET['edit']);
    
    $edit_query = "SELECT * FROM positions WHERE position_id = '$edit_position_id'";
    $edit_result = mysqli_query($con, $edit_query);
    
    if (mysqli_num_rows($edit_result) > 0) {
        $edit_data = mysqli_fetch_assoc($edit_result);
        $edit_title = $edit_data['title'];
        $edit_daily_rate = $edit_data['daily_rate'];
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
    <title>Project Manager Positions</title>
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
                    <h2 class="fs-2 m-0">Positions</h2>
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
                
                            
                            <!-- Alert Messages -->
                            <?php if(isset($_GET['success'])): ?>
                            <!-- REMOVE success alert -->
                            <?php endif; ?>
                            
                            <?php if(isset($_GET['updated'])): ?>
                            <!-- REMOVE updated alert -->
                            <?php endif; ?>
                            
                            <?php if(isset($_GET['deleted'])): ?>
                            <!-- REMOVE deleted alert -->
                            <?php endif; ?>
                            
                            <?php if(isset($_GET['error'])): ?>
                            <!-- REMOVE error alert -->
                            <?php endif; ?>
                            
                            <?php if($edit_mode): ?>
                            <!-- Edit Position Form -->
                            <div class="card mb-4">
                                <div class="card-body">
                                   <div class="d-flex justify-content-between align-items-center mb-3">
                                       <h4 class="mb-0">Edit Position</h4>
                                   </div>
                                    <form action="" method="POST">
                                        <input type="hidden" name="position_id" value="<?php echo $edit_position_id; ?>">
                                        <div class="form-group">
                                            <label for="title"><b>Position Title</b></label>
                                            <input type="text" class="form-control" id="title" name="title" value="<?php echo $edit_title; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="daily_rate"><b>Daily Rate (₱)</b></label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="daily_rate" name="daily_rate" value="<?php echo $edit_daily_rate; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <button type="submit" name="update_position" class="btn btn-success">Update Position</button>
                                            <a href="positions.php" class="btn btn-secondary">Cancel</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- Positions Table -->
                            <div class="card mb-5 shadow rounded-3">
                                <div class="card-body">
                                   <div class="mb-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
                                       <h4 class="mb-0">Position Management</h4>
                                       <button type="button" class="btn btn-success ms-auto" data-bs-toggle="modal" data-bs-target="#addPositionModal">
                                           <i class="fas fa-plus"></i> Add New Position
                                       </button>
                                   </div>
                                   <hr>
                                   <form class="d-flex flex-grow-1 mb-3" method="get" action="" id="searchForm" style="min-width:260px; max-width:400px;">
                                       <div class="input-group w-100">
                                           <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                           <input type="text" class="form-control border-start-0" name="search" placeholder="Search position/title" value="<?php echo htmlspecialchars($search); ?>" id="searchInput" autocomplete="off">
                                       </div>
                                   </form>
                                    <div class="table-responsive mb-0">
                                        <table class="table table-bordered table-striped mb-0">
                                            <thead class="thead-dark">
                                                <tr>
                                                    <th>No.</th>
                                                    <th>Position</th>
                                                    <th>Daily Rate</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                // Fetch all positions (with filter, limit, offset)
                                                $query = "SELECT * FROM positions $filter_sql ORDER BY title ASC LIMIT $limit OFFSET $offset";
                                                $result = mysqli_query($con, $query);
                                                $no = $offset + 1;
                                                if(mysqli_num_rows($result) > 0) {
                                                    while($row = mysqli_fetch_assoc($result)) {
                                                ?>
                                                <tr>
                                                    <td><?php echo $no++; ?></td>
                                                    <td><?php echo $row['title']; ?></td>
                                                    <td>₱<?php echo number_format($row['daily_rate'], 2); ?></td>
                                                    <td class="text-center">
                                                        <div class="action-buttons">
                                                            <button type="button" class="btn btn-warning btn-sm text-dark new-edit-btn" 
                                                                data-id="<?php echo $row['position_id']; ?>" 
                                                                data-title="<?php echo htmlspecialchars($row['title']); ?>" 
                                                                data-rate="<?php echo $row['daily_rate']; ?>">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                            <a href="#" class="btn btn-danger btn-sm text-white delete-position-btn" data-id="<?php echo $row['position_id']; ?>" data-title="<?php echo htmlspecialchars($row['title']); ?>">
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
                                                    <td colspan="4" class="text-center">No positions found</td>
                                                </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
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
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Add Position Modal -->
                <div class="modal fade" id="addPositionModal" tabindex="-1" aria-labelledby="addPositionModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addPositionModalLabel">Add New Position</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form action="" method="POST">
                                    <div class="form-group">
                                        <label for="modal_title"><b>Position Title</b></label>
                                        <input type="text" class="form-control" id="modal_title" name="title" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="modal_daily_rate"><b>Daily Rate (₱)</b></label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="modal_daily_rate" name="daily_rate" required>
                                    </div>
                                    <div class="modal-footer justify-content-end">
                                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="add_position" class="btn btn-success">Add Position</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- New Edit Position Modal -->
                <div class="modal fade" id="newEditPositionModal" tabindex="-1" aria-labelledby="newEditPositionModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="newEditPositionModalLabel">Edit Position</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <form action="" method="POST" id="newEditPositionForm">
                        <div class="modal-body">
                          <input type="hidden" name="position_id" id="new_edit_position_id">
                          <div class="form-group mb-3">
                            <label for="new_edit_title"><b>Position Title</b></label>
                            <input type="text" class="form-control" id="new_edit_title" name="title" required>
                          </div>
                          <div class="form-group mb-3">
                            <label for="new_edit_daily_rate"><b>Daily Rate (₱)</b></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="new_edit_daily_rate" name="daily_rate" required>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" name="update_position" class="btn btn-success">Update Position</button>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script>
      if (typeof feather !== 'undefined') { feather.replace(); }
    </script>
   
    <script>
        feather.replace();
        
        // Manual dropdown handling for sidebar (vanilla JS)
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Toggle the current dropdown
                    var menu = this.nextElementSibling;
                    if (menu) menu.classList.toggle('show');
                    // Close other dropdowns
                    document.querySelectorAll('.dropdown-toggle').forEach(function(other) {
                        if (other !== toggle) {
                            var otherMenu = other.nextElementSibling;
                            if (otherMenu) otherMenu.classList.remove('show');
                        }
                    });
                });
            });
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                        menu.classList.remove('show');
                    });
                }
            });
            // Toggle sidebar
            var menuToggle = document.getElementById('menu-toggle');
            if (menuToggle) {
                menuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.getElementById('wrapper').classList.toggle('toggled');
                });
            }
            // Search input debounce
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
<?php if (isset($_GET['success'])): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, 'Position added successfully.', '', 'success');
});
<?php elseif (isset($_GET['updated'])): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, 'Position updated successfully.', '', 'updated');
});
<?php elseif (isset($_GET['deleted'])): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, 'Position deleted successfully.', '', 'deleted');
});
<?php elseif (isset($_GET['error'])): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(false, 'Cannot delete this position because it is assigned to one or more employees.', '', 'error');
});
<?php endif; ?>
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.new-edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.getElementById('new_edit_position_id').value = this.getAttribute('data-id');
      document.getElementById('new_edit_title').value = this.getAttribute('data-title');
      document.getElementById('new_edit_daily_rate').value = this.getAttribute('data-rate');
      var modal = new bootstrap.Modal(document.getElementById('newEditPositionModal'));
      modal.show();
    });
  });
});
</script>
<script>
// Validation for Position Title (Add & Edit)
function isValidTitle(title) {
  // Only allow letters (upper/lower) and spaces
  return /^[A-Za-z ]+$/.test(title.trim());
}
function showTitleError(input, msg) {
  input.setCustomValidity(msg);
  input.reportValidity();
}
document.addEventListener('DOMContentLoaded', function() {
  // Add Position Modal
  var addForm = document.querySelector('#addPositionModal form');
  if (addForm) {
    var addTitle = document.getElementById('modal_title');
    addForm.addEventListener('submit', function(e) {
      if (!isValidTitle(addTitle.value)) {
        showTitleError(addTitle, 'Position Title must only contain letters and spaces.');
        e.preventDefault();
      } else {
        addTitle.setCustomValidity('');
      }
    });
    addTitle.addEventListener('input', function() {
      addTitle.setCustomValidity('');
    });
  }
  // Edit Position Modal
  var editForm = document.getElementById('newEditPositionForm');
  if (editForm) {
    var editTitle = document.getElementById('new_edit_title');
    editForm.addEventListener('submit', function(e) {
      if (!isValidTitle(editTitle.value)) {
        showTitleError(editTitle, 'Position Title must only contain letters and spaces.');
        e.preventDefault();
      } else {
        editTitle.setCustomValidity('');
      }
    });
    editTitle.addEventListener('input', function() {
      editTitle.setCustomValidity('');
    });
  }
});
</script>
<script>
// Prevent numbers and special characters in Position Title fields (Add & Edit)
document.addEventListener('DOMContentLoaded', function() {
  function filterTitleInput(e) {
    // Only allow letters and spaces
    let value = e.target.value;
    let filtered = value.replace(/[^A-Za-z ]+/g, '');
    if (value !== filtered) {
      e.target.value = filtered;
    }
  }
  var addTitle = document.getElementById('modal_title');
  if (addTitle) {
    addTitle.addEventListener('input', filterTitleInput);
  }
  var editTitle = document.getElementById('new_edit_title');
  if (editTitle) {
    editTitle.addEventListener('input', filterTitleInput);
  }
});
</script>
<div class="modal fade" id="deletePositionModal" tabindex="-1" aria-labelledby="deletePositionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deletePositionModalLabel">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete <strong id="positionTitle"></strong>?</p>
        <p class="text-danger">This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="confirmDeletePosition" class="btn btn-danger">Delete</a>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.delete-position-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var posId = this.getAttribute('data-id');
      var posTitle = this.getAttribute('data-title');
      document.getElementById('positionTitle').textContent = posTitle;
      var confirmDelete = document.getElementById('confirmDeletePosition');
      confirmDelete.setAttribute('href', '?delete=' + posId);
      var modal = new bootstrap.Modal(document.getElementById('deletePositionModal'));
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