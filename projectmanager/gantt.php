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

$projects = [];
$res = mysqli_query($con, "SELECT project_id, project, start_date, deadline FROM projects WHERE user_id='$userid' AND archived=0 ORDER BY project_id DESC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $projects[] = $row;
    }
}
function monthIndex($date) {
    return (int)date('n', strtotime($date)) - 1;
}
function yearOf($date) {
    return (int)date('Y', strtotime($date));
}
$currentYear = date('Y');
$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
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
    <title>Project Manager Gantt Chart</title>
    <style>
.card-link { cursor: pointer; text-decoration: none; color: inherit; }
.card-link:hover { box-shadow: 0 0 0 2px #009d6333; }
</style>
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
                    <h2 class="fs-2 m-0">Project Gantt Chart</h2>
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
                <div class="card mb-4 shadow rounded-3">
                    <div class="card-body">
                        <div class="d-flex flex-column align-items-center mb-2">
                            <div class="w-100 d-flex justify-content-center align-items-center mb-2">
                                <h4 class="mb-0 text-center flex-grow-1">Gantt Chart (by Month, <?php echo $currentYear; ?>)</h4>
                            </div>
                            <hr class="w-100 my-2">
                            <div class="w-100 d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <a href="projects.php" class="btn btn-danger"><i class="fas fa-arrow-left me-1"></i> Back</a>
                                <div class="ms-auto d-flex gap-2">
                                    <button class="btn btn-danger" id="exportPdfBtn"><i class="fas fa-file-pdf me-1"></i> Export as PDF</button>
                                    <button class="btn btn-primary" id="exportImgBtn"><i class="fas fa-image me-1"></i> Export as Image</button>
                                </div>
                            </div>
                        </div>
                        <?php if (count($projects) === 0): ?>
                            <div class="alert alert-info">No projects found for your account.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle text-center" id="ganttTable" style="min-width:900px;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:220px;">Project</th>
                                        <?php foreach ($months as $m): ?>
                                            <th style="width:56px;"><?php echo $m; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $p): ?>
                                    <tr>
                                        <td class="text-start fw-bold"><?php echo htmlspecialchars($p['project']); ?></td>
                                        <?php
                                            $startIdx = monthIndex($p['start_date']);
                                            $endIdx = monthIndex($p['deadline']);
                                            $startYear = yearOf($p['start_date']);
                                            $endYear = yearOf($p['deadline']);
                                            // Calculate bar start and end for this year
                                            $barStart = ($startYear < $currentYear) ? 0 : $startIdx;
                                            $barEnd = ($endYear > $currentYear) ? 11 : $endIdx;
                                            // Left empty cells
                                            for ($i = 0; $i < $barStart; $i++) echo '<td></td>';
                                            // Bar cell
                                            $colspan = $barEnd - $barStart + 1;
                                            echo '<td colspan="' . $colspan . '" style="padding:0;vertical-align:middle;">';
                                            echo '<div style="height:32px;background:#009d63;border-radius:4px;width:100%;position:relative;display:flex;align-items:center;justify-content:center;">';
                                            $start_fmt = date('m-d-Y', strtotime($p['start_date']));
                                            $end_fmt = date('m-d-Y', strtotime($p['deadline']));
                                            echo '<span style="color:#fff;font-size:0.6em;font-weight:bold;text-shadow:0 1px 2px #0008;">' . $start_fmt . ' to ' . $end_fmt . '</span>';
                                            echo '</div>';
                                            echo '</td>';
                                            // Right empty cells
                                            for ($i = $barEnd + 1; $i < 12; $i++) echo '<td></td>';
                                        ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /#page-content-wrapper -->
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
    <?php if (count($projects) > 0): ?>
    <script>
const ctx = document.getElementById('ganttChart').getContext('2d');
const data = {
    labels: <?php echo json_encode(array_column($projects, 'project')); ?>,
    datasets: [{
        label: 'Project',
        data: <?php echo json_encode(array_map(function($p, $i) { return 10 + $i * 5; }, $projects, array_keys($projects))); ?>,
        backgroundColor: '#009d63',
        borderRadius: 8,
        barPercentage: 0.7,
        categoryPercentage: 0.7,
    }]
};
const config = {
    type: 'bar',
    data: data,
    options: {
        indexAxis: 'y',
        plugins: {
            legend: { display: false },
            title: { display: false }
        },
        scales: {
            x: { display: false, min: 0 },
            y: { ticks: { color: '#222', font: { size: 16, weight: 'bold' } } }
        },
        responsive: true,
        maintainAspectRatio: false,
    }
};
new Chart(ctx, config);
</script>
<?php endif; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
document.getElementById('exportImgBtn').onclick = function() {
    html2canvas(document.getElementById('ganttTable')).then(function(canvas) {
        var link = document.createElement('a');
        link.download = 'gantt_chart.png';
        link.href = canvas.toDataURL();
        link.click();
    });
};
document.getElementById('exportPdfBtn').onclick = function() {
    html2canvas(document.getElementById('ganttTable')).then(function(canvas) {
        var imgData = canvas.toDataURL('image/png');
        var pdf = new window.jspdf.jsPDF({orientation: 'landscape'});
        var pageWidth = pdf.internal.pageSize.getWidth();
        var pageHeight = pdf.internal.pageSize.getHeight();
        var imgWidth = pageWidth - 20;
        var imgHeight = canvas.height * imgWidth / canvas.width;
        pdf.addImage(imgData, 'PNG', 10, 10, imgWidth, imgHeight);
        pdf.save('gantt_chart.pdf');
    });
};
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