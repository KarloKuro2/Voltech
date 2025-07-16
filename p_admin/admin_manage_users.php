<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 2) {
    header("Location: ../login.php");
    exit();
}
// Change Password Backend Handler
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['change_password'])
) {
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
// Fetch user info from DB
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
// Search and pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$filter_sql = '';
if ($search !== '') {
    $filter_sql = "WHERE (firstname LIKE '%$search%' OR lastname LIKE '%$search%' OR email LIKE '%$search%')";
}
$count_query = "SELECT COUNT(*) as total FROM users $filter_sql";
$count_result = mysqli_query($con, $count_query);
$total_users = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_users / $limit);
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
    <title>Manage Users</title>
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
            <a href="admin_dashboard.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>Dashboard
            </a>
            <a href="admin_manage_users.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'admin_manage_users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Manage Users
            </a>
            <a href="admin_user_activity_reports.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'admin_user_activity_reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> User Activity Reports
            </a>
            <a href="admin_approval_requests.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'admin_approval_requests.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Approval of Requests
            </a>
        </div>
    </div>
    <!-- /#sidebar-wrapper -->
    <!-- Page Content -->
    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                <h2 class="fs-2 m-0">User</h2>
            </div>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php include 'admin_notification.php'; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle second-text fw-bold" href="#" id="navbarDropdown"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($user_name); ?>
                            <img src="<?php echo $userprofile; ?>" alt="User" class="rounded-circle" width="30" height="30" style="margin-left: 8px;">
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="admin_profile.php">Profile</a></li>
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
                        <h4 class="mb-0">User Management</h4>
                        <button type="button" class="btn btn-success ms-auto" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-user-plus"></i> Add New User
                        </button>
                    </div>
                    <hr>
                    <form class="d-flex flex-grow-1 mb-3" method="get" action="" id="searchForm" style="min-width:260px; max-width:400px;">
                        <div class="input-group w-100">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" class="form-control border-start-0" name="search" placeholder="Search name or email" value="<?php echo htmlspecialchars($search); ?>" id="searchInput" autocomplete="off">
                        </div>
                    </form>
                    <div class="table-responsive mb-0">
                        <table class="table table-bordered table-striped mb-0">
                            <thead class="thead-dark">
                                <tr>
                                    <th>No</th>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>Email</th>
                                    <th>User Level</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT * FROM users WHERE user_level IN (3,4) $filter_sql ORDER BY firstname, lastname LIMIT $limit OFFSET $offset";
                                $result = mysqli_query($con, $query);
                                $no = 1 + $offset;
                                while ($user = mysqli_fetch_assoc($result)) {
                                    $userLevel = '';
                                    switch ($user['user_level']) {
                                        case 3:
                                            $userLevel = '<span class="badge bg-success">Project Manager</span>';
                                            break;
                                        case 4:
                                            $userLevel = '<span class="badge bg-warning text-dark">Procurement Officer</span>';
                                            break;
                                        default:
                                            $userLevel = '<span class="badge bg-secondary">Unknown</span>';
                                    }
                                    echo "<tr>";
                                    echo "<td>" . $no++ . "</td>";
                                    echo "<td>" . htmlspecialchars($user['firstname']) . "</td>";
                                    echo "<td>" . htmlspecialchars($user['lastname']) . "</td>";
                                    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                                    echo "<td>" . $userLevel . "</td>";
                                    echo "<td>" . ($user['is_verified'] ? '<span class=\'badge bg-success\'>Active</span>' : '<span class=\'badge bg-warning text-dark\'>Pending</span>') . "</td>";
                                    echo "<td>";
                                    echo "<button class='btn btn-warning btn-sm text-dark me-1' onclick='editUser(" . $user['id'] . ")'><i class='fas fa-edit'></i> Edit</button>";
                                    echo "<button class='btn btn-danger btn-sm text-white' onclick='deleteUser(" . $user['id'] . ")'><i class='fas fa-trash'></i> Delete</button>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                                ?>
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
<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="admin_add_user.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>First Name</label>
                        <input type="text" class="form-control" name="firstname" required>
                    </div>
                    <div class="mb-3">
                        <label>Last Name</label>
                        <input type="text" class="form-control" name="lastname" required>
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label>Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label>User Level</label>
                        <select class="form-control" name="user_level" required>
                            <option value="3">Project Manager</option>
                            <option value="4">Procurement Officer</option>
                            <option value="2">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_verified" value="0">
                            <input class="form-check-input" type="checkbox" id="add_is_verified" name="is_verified" value="1">
                            <label class="form-check-label" for="add_is_verified">Account Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editUserForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-3">
                        <label>First Name</label>
                        <input type="text" class="form-control" name="firstname" id="edit_firstname" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label>Last Name</label>
                        <input type="text" class="form-control" name="lastname" id="edit_lastname" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label>New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" name="password" id="edit_password" autocomplete="off">
                        <small class="form-text text-muted">Only fill this if you want to change the password</small>
                    </div>
                    <div class="mb-3">
                        <label>User Level</label>
                        <select class="form-control" name="user_level" id="edit_user_level" required>
                            <option value="3">Project Manager</option>
                            <option value="4">Procurement Officer</option>
                            <option value="2">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_verified" value="0">
                            <input class="form-check-input" type="checkbox" id="edit_is_verified" name="is_verified" value="1">
                            <label class="form-check-label" for="edit_is_verified">Account Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteConfirmModalLabel">Delete User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this user? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function editUser(userId) {
    $.ajax({
        url: 'admin_edit_user.php',
        type: 'GET',
        data: { id: userId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var user = response.user;
                $('#edit_user_id').val(user.id);
                $('#edit_firstname').val(user.firstname);
                $('#edit_lastname').val(user.lastname);
                $('#edit_email').val(user.email);
                $('#edit_user_level').val(user.user_level);
                $('#edit_is_verified').prop('checked', user.is_verified == 1);
                $('#editUserModal').modal('show');
            } else {
                alert(response.message || 'Error fetching user data');
            }
        },
        error: function() {
            alert('Error fetching user data');
        }
    });
}
let userIdToDelete = null;
function deleteUser(userId) {
    userIdToDelete = userId;
    $('#deleteConfirmModal').modal('show');
}
$(document).ready(function() {
    // Handle edit user form submission
    $('#editUserForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            type: 'POST',
            url: 'admin_edit_user.php',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#editUserModal').modal('hide');
                    window.location.href = 'admin_manage_users.php?updated=1';
                } else {
                    alert(response.message || 'Error updating user');
                }
            },
            error: function() {
                alert('Error processing request');
            }
        });
    });
    // Handle delete confirmation
    $('#confirmDelete').click(function() {
        if (userIdToDelete) {
            $.ajax({
                url: 'admin_delete_user.php',
                type: 'POST',
                data: { user_id: userIdToDelete },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#deleteConfirmModal').modal('hide');
                        window.location.href = 'admin_manage_users.php?deleted=1';
                    } else {
                        alert(response.message || 'Error deleting user');
                    }
                },
                error: function() {
                    alert('Error processing delete request');
                }
            });
        }
    });
    // Handle change password form submission
    var changePasswordForm = document.getElementById('changePasswordForm');
    var feedbackDiv = document.getElementById('changePasswordFeedback');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (feedbackDiv) feedbackDiv.innerHTML = '';
            var formData = new FormData(changePasswordForm);
            formData.append('change_password', '1');
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'admin_change_password.php', true); // Changed URL
            xhr.onload = function() {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        if (feedbackDiv) feedbackDiv.innerHTML = '<div class="alert alert-success">' + res.message + '</div>';
                        changePasswordForm.reset();
                        setTimeout(function() {
                            var modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
                            if (modal) modal.hide();
                        }, 1200);
                    } else {
                        if (feedbackDiv) feedbackDiv.innerHTML = '<div class="alert alert-danger">' + res.message + '</div>';
                    }
                } catch (err) {
                    if (feedbackDiv) feedbackDiv.innerHTML = '<div class="alert alert-danger">Unexpected error. Please try again.</div>';
                }
            };
            xhr.send(formData);
        });
    }
});
// Feedback Modal Logic

</script>
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
    showFeedbackModal(true, 'User added successfully!', '', 'added');
  } else if (params.get('updated') === '1') {
    showFeedbackModal(true, 'User updated successfully!', '', 'updated');
  } else if (params.get('deleted') === '1') {
    showFeedbackModal(true, 'User deleted successfully!', '', 'deleted');
  }
})();
</script>
</body>
</html> 