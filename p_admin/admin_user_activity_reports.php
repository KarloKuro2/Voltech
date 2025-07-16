<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 2) {
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

// Fetch user's position and project count
$user_position = 'N/A';
$project_count = 0;
// Get position from employees/positions
$emp_result = $con->query("SELECT p.title FROM employees e LEFT JOIN positions p ON e.position_id = p.position_id WHERE e.user_id = '$userid' LIMIT 1");
if ($emp_result && $emp_result->num_rows > 0) {
    $emp_row = $emp_result->fetch_assoc();
    $user_position = $emp_row['title'];
}
// If user_level is 3, override position to Project Manager
if (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 3) {
    $user_position = 'Project Manager';
}
// Get project count
$proj_result = $con->query("SELECT COUNT(*) as total FROM projects WHERE user_id = '$userid'");
if ($proj_result && $row = $proj_result->fetch_assoc()) {
    $project_count = $row['total'];
}

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle AJAX password change
    if (isset($_POST['change_password'])) {
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
            // Check current password
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
    if (isset($_POST['save'])) {
        $fname = trim($_POST['first_name']);
        $lname = trim($_POST['last_name']);
        if ($fname && $lname) {
            $sql = "UPDATE users SET firstname = '$fname', lastname='$lname' WHERE id='$userid'";
            if (mysqli_query($con, $sql)) {
                $_SESSION['firstname'] = $fname;
                $_SESSION['lastname'] = $lname;
                $_SESSION['success_message'] = "Profile updated successfully.";
                header('Location: po_profile.php');
                exit();
            } else {
                $_SESSION['error_message'] = "Could not update profile. " . mysqli_error($con);
                header('Location: po_profile.php');
                exit();
            }
        } else {
            $_SESSION['error_message'] = "First and last name are required.";
            header('Location: po_profile.php');
            exit();
        }
    }
    if (isset($_POST['but_upload'])) {
        if (!empty($_POST['cropped_image'])) {
            $data = $_POST['cropped_image'];
            $name = 'profile_' . $userid . '_' . time() . '.png';
            $target_dir = "../uploads/";
            $target_file = $target_dir . $name;
            $data = preg_replace('/^data:image\/(png|jpg|jpeg);base64,/', '', $data);
            $data = str_replace(' ', '+', $data);
            file_put_contents($target_file, base64_decode($data));
            $query = "UPDATE users SET profile_path = '$name' WHERE id='$userid'";
            if (mysqli_query($con, $query)) {
                $userprofile = '../uploads/' . $name;
                $_SESSION['userprofile'] = $userprofile;
                $_SESSION['success_message'] = "Profile picture updated.";
                header('Location: po_profile.php');
                exit();
            } else {
                $_SESSION['error_message'] = "Could not update profile picture in database. " . mysqli_error($con);
                header('Location: po_profile.php');
                exit();
            }
        } else {
            if (isset($_FILES['file']) && $_FILES['file']['name']) {
                $name = basename($_FILES['file']['name']);
                $target_dir = "../uploads/";
                $target_file = $target_dir . $name;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                $extensions_arr = array("jpg", "jpeg", "png", "gif");
                if (in_array($imageFileType, $extensions_arr)) {
                    move_uploaded_file($_FILES['file']['tmp_name'], $target_file);
                    $query = "UPDATE users SET profile_path = '$name' WHERE id='$userid'";
                    if (mysqli_query($con, $query)) {
                        $userprofile = '../uploads/' . $name;
                        $_SESSION['userprofile'] = $userprofile;
                        $_SESSION['success_message'] = "Profile picture updated.";
                        header('Location: po_profile.php');
                        exit();
                    } else {
                        $_SESSION['error_message'] = "Could not update profile picture in database. " . mysqli_error($con);
                        header('Location: po_profile.php');
                        exit();
                    }
                } else {
                    $_SESSION['error_message'] = "Invalid file type. Only JPG, JPEG, PNG, GIF allowed.";
                    header('Location: po_profile.php');
                    exit();
                }
            } else {
                $_SESSION['error_message'] = "No file selected.";
                header('Location: po_profile.php');
                exit();
            }
        }
    }
    // Refresh user info after update
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
    <link rel="stylesheet" href="po_styles.css" />
    <!-- Cropper.js CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" />
    <title>Procurement Officer Profile</title>
    <style>
        #profilepic-preview {
            width: 200px;
            height: 200px;
            object-fit: cover;
            object-position: center;
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
                <h2 class="fs-2 m-0">Profile</h2>
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
                        <h4 class="mb-0">User Activity Reports</h4>
                        <div class="ms-auto">
                            <a href="#" class="btn btn-danger exportPdfBtn">
                                <i class="fas fa-file-pdf"></i> Export as PDF
                            </a>
                        </div>
                    </div>
                    <hr>
                    <div class="table-responsive mb-0">
                        <table class="table table-bordered table-striped mb-0">
                            <thead class="thead-dark">
                                <tr>
                                    <th>No</th>
                                    <th>Full Name</th>
                                    <th>User Level</th>
                                    <th>Latest Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT * FROM users WHERE user_level IN (3,4) ORDER BY firstname, lastname";
                                $result = mysqli_query($con, $query);
                                $no = 1;
                                while ($user = mysqli_fetch_assoc($result)) {
                                    $fullName = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);
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
                                    echo "<td>$fullName</td>";
                                    echo "<td>$userLevel</td>";
                                    echo '<td><button class="btn btn-info btn-sm view-activity-btn" data-userid="' . $user['id'] . '" data-userlevel="' . $user['user_level'] . '">View</button></td>';
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
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
        <!-- Export PDF Confirmation Modal (right side) -->
        <div class="modal fade" id="exportPdfModal" tabindex="-1" aria-labelledby="exportPdfModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="exportPdfModalLabel">Export as PDF</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <p>Are you sure you want to export the user activity reports as PDF?</p>
              </div>
              <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="export_user_activity_pdf.php" id="confirmExportPdf" class="btn btn-danger">Export</a>
              </div>
            </div>
          </div>
        </div>
        <!-- Activity Modal -->
        <div class="modal fade" id="activityModal" tabindex="-1" aria-labelledby="activityModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="activityModalLabel">User Activities</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div id="activityModalContent">Loading...</div>
              </div>
            </div>
          </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script>
    var el = document.getElementById("wrapper");
    var toggleButton = document.getElementById("menu-toggle");
    toggleButton.onclick = function () {
        el.classList.toggle("toggled");
    };
</script>
<script>
const profilePicInput = document.getElementById('profilepic');
const profilePicPreview = document.getElementById('profilepic-preview');
const uploadPicBtn = document.getElementById('uploadPicBtn');
const cropperModal = new bootstrap.Modal(document.getElementById('cropperModal'));
const cropperImage = document.getElementById('cropper-image');
const cropImageBtn = document.getElementById('cropImageBtn');
const croppedImageInput = document.getElementById('cropped_image');
let cropper = null;

if (profilePicInput && profilePicPreview && uploadPicBtn && cropperImage && cropImageBtn && croppedImageInput) {
    profilePicInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                cropperImage.src = e.target.result;
                cropperModal.show();
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    document.getElementById('cropperModal').addEventListener('shown.bs.modal', function () {
        cropper = new Cropper(cropperImage, {
            aspectRatio: 1,
            viewMode: 1,
            autoCropArea: 1,
            minCropBoxWidth: 200,
            minCropBoxHeight: 200,
            ready() {
                cropper.setCropBoxData({ width: 200, height: 200 });
            }
        });
    });
    document.getElementById('cropperModal').addEventListener('hidden.bs.modal', function () {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        // Reset file input if modal closed without cropping
        // profilePicInput.value = '';
    });
    cropImageBtn.addEventListener('click', function() {
        if (cropper) {
            const canvas = cropper.getCroppedCanvas({ width: 200, height: 200 });
            const dataUrl = canvas.toDataURL('image/png');
            profilePicPreview.src = dataUrl;
            croppedImageInput.value = dataUrl;
            uploadPicBtn.classList.remove('d-none');
            cropperModal.hide();
        }
    });
}
</script>
<script>
// Feedback Modal Logic
function showFeedbackModal(success, message) {
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
}
<?php if ($success_message): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, <?php echo json_encode($success_message); ?>);
});
<?php elseif ($error_message): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(false, <?php echo json_encode($error_message); ?>);
});
<?php endif; ?>
</script>
<script>
// Change Password AJAX
document.addEventListener('DOMContentLoaded', function() {
  var changePasswordForm = document.getElementById('changePasswordForm');
  var feedbackDiv = document.getElementById('changePasswordFeedback');
  if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', function(e) {
      e.preventDefault();
      feedbackDiv.innerHTML = '';
      var formData = new FormData(changePasswordForm);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'po_profile.php', true);
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
// Export PDF button logic (like po_materials.php)
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.exportPdfBtn').forEach(function(exportBtn) {
        exportBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var modal = new bootstrap.Modal(document.getElementById('exportPdfModal'));
            modal.show();
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.view-activity-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var userId = this.getAttribute('data-userid');
      var userLevel = this.getAttribute('data-userlevel');
      var modal = new bootstrap.Modal(document.getElementById('activityModal'));
      var contentDiv = document.getElementById('activityModalContent');
      contentDiv.innerHTML = 'Loading...';
      fetch('fetch_user_notifications.php?user_id=' + userId + '&user_level=' + userLevel)
        .then(response => response.text())
        .then(html => {
          contentDiv.innerHTML = html;
        })
        .catch(() => {
          contentDiv.innerHTML = '<div class="alert alert-danger">Failed to load activities.</div>';
        });
      modal.show();
    });
  });
});
</script>
</body>
</html> 