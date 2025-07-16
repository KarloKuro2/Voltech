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

// --- Yearly Expenses Data for Chart ---
$year = 2025; // or the year you want to show
$monthly_expenses = array_fill(1, 12, 0);
$sql = "SELECT MONTH(expensedate) as month, SUM(expense) as total FROM expenses WHERE user_id='$userid' AND YEAR(expensedate)='$year' GROUP BY MONTH(expensedate)";
$res = mysqli_query($con, $sql);
while ($row = mysqli_fetch_assoc($res)) {
    $monthly_expenses[(int)$row['month']] = (float)$row['total'];
}

// --- Top 3 Projects by Progress ---
$top_projects = [];
$top_labels = [];
$top_progress = [];
$top_sql = "SELECT p.project_id, p.project, AVG(d.progress) as avg_progress FROM projects p LEFT JOIN project_divisions d ON p.project_id = d.project_id WHERE p.user_id='$userid' GROUP BY p.project_id, p.project ORDER BY avg_progress DESC LIMIT 3";
$top_res = mysqli_query($con, $top_sql);
while ($row = mysqli_fetch_assoc($top_res)) {
    $top_projects[] = $row;
    $top_labels[] = $row['project'];
    $top_progress[] = round($row['avg_progress'], 1);
}

// --- Estimate Expense Comparison Data ---
$est_labels = [];
$est_totals = [];
$proj_sql = "SELECT project_id, project FROM projects WHERE user_id='$userid' AND (io='1' OR io='4') ORDER BY project_id DESC";
$proj_res = mysqli_query($con, $proj_sql);
while ($proj = mysqli_fetch_assoc($proj_res)) {
    $pid = $proj['project_id'];
    $est_labels[] = $proj['project'];
    // Employees
    $emp_total = 0;
    $emp_query = mysqli_query($con, "SELECT total FROM project_add_employee WHERE project_id='$pid'");
    while ($erow = mysqli_fetch_assoc($emp_query)) {
        $emp_total += floatval($erow['total']);
    }
    // Materials
    $mat_total = 0;
    $mat_query = mysqli_query($con, "SELECT total FROM project_add_materials WHERE project_id='$pid'");
    while ($mrow = mysqli_fetch_assoc($mat_query)) {
        $mat_total += floatval($mrow['total']);
    }
    // Equipment (include all, even returned)
    $equip_total = 0;
    $equip_query = mysqli_query($con, "SELECT total FROM project_add_equipment WHERE project_id='$pid'");
    while ($eqrow = mysqli_fetch_assoc($equip_query)) {
        $equip_total += floatval($eqrow['total']);
    }
    $grand_total = $emp_total + $mat_total + $equip_total;
    $est_totals[] = round($grand_total, 2);
}

// --- Actual Expense Data for Line Chart ---
$actual_labels = [];
$actual_totals = [];
$proj_sql2 = "SELECT project_id, project FROM projects WHERE user_id='$userid' ORDER BY project_id DESC";
$proj_res2 = mysqli_query($con, $proj_sql2);
while ($proj = mysqli_fetch_assoc($proj_res2)) {
    $pid = $proj['project_id'];
    $actual_labels[] = $proj['project'];
    // Sum all expenses for this project (from project_add_employee, project_add_materials, project_add_equipment)
    $emp_total = 0;
    $emp_query = mysqli_query($con, "SELECT total FROM project_add_employee WHERE project_id='$pid'");
    while ($erow = mysqli_fetch_assoc($emp_query)) {
        $emp_total += floatval($erow['total']);
    }
    $mat_total = 0;
    $mat_query = mysqli_query($con, "SELECT total FROM project_add_materials WHERE project_id='$pid'");
    while ($mrow = mysqli_fetch_assoc($mat_query)) {
        $mat_total += floatval($mrow['total']);
    }
    $equip_total = 0;
    $equip_query = mysqli_query($con, "SELECT total FROM project_add_equipment WHERE project_id='$pid'");
    while ($eqrow = mysqli_fetch_assoc($equip_query)) {
        $equip_total += floatval($eqrow['total']);
    }
    $actual_total = $emp_total + $mat_total + $equip_total;
    $actual_totals[] = round($actual_total, 2);
}

// --- Total Estimated Expense for All Projects (for single-bar chart) ---
$total_estimate_sum = 0;
foreach ($est_totals as $val) {
    $total_estimate_sum += $val;
}
$all_projects_label = ['All Projects'];
$all_projects_data = [$total_estimate_sum];

// --- Project Category Distribution Data for Pie Chart ---
$category_labels = [];
$category_counts = [];
$cat_query = mysqli_query($con, "SELECT category, COUNT(*) as count FROM projects WHERE user_id='$userid' GROUP BY category");
while ($row = mysqli_fetch_assoc($cat_query)) {
    $category_labels[] = $row['category'];
    $category_counts[] = (int)$row['count'];
}

// --- Category Estimation Data for Line Chart ---
$cat_est_labels = [];
$cat_est_totals = [];
$cat_est_query = mysqli_query($con, "SELECT category, SUM(
    (SELECT IFNULL(SUM(total),0) FROM project_add_employee WHERE project_id=p.project_id) +
    (SELECT IFNULL(SUM(total),0) FROM project_add_materials WHERE project_id=p.project_id) +
    (SELECT IFNULL(SUM(total),0) FROM project_add_equipment WHERE project_id=p.project_id)
) as total FROM projects p WHERE user_id='$userid' AND (io='1' OR io='4') GROUP BY category");
while ($row = mysqli_fetch_assoc($cat_est_query)) {
    $cat_est_labels[] = $row['category'];
    $cat_est_totals[] = round($row['total'], 2);
}

// --- Prepare project data for each category using the same logic as Estimate Expense Project Comparison ---
$categories = ['House', 'Renovation', 'Building'];
$projects_by_category = [
    'House' => [],
    'Renovation' => [],
    'Building' => []
];
$totals_by_category = [
    'House' => 0,
    'Renovation' => 0,
    'Building' => 0
];
$proj_sql = "SELECT project_id, project, category FROM projects WHERE user_id='$userid' AND (io='1' OR io='4') ORDER BY project_id DESC";
$proj_res = mysqli_query($con, $proj_sql);
while ($proj = mysqli_fetch_assoc($proj_res)) {
    $pid = $proj['project_id'];
    $cat = ucfirst(strtolower(trim($proj['category'])));
    if (!in_array($cat, $categories)) continue;
    // Calculate estimated expense as in Estimate Expense Project Comparison
    $emp_total = 0;
    $emp_query = mysqli_query($con, "SELECT total FROM project_add_employee WHERE project_id='$pid'");
    while ($erow = mysqli_fetch_assoc($emp_query)) {
        $emp_total += floatval($erow['total']);
    }
    $mat_total = 0;
    $mat_query = mysqli_query($con, "SELECT total FROM project_add_materials WHERE project_id='$pid'");
    while ($mrow = mysqli_fetch_assoc($mat_query)) {
        $mat_total += floatval($mrow['total']);
    }
    $equip_total = 0;
    $equip_query = mysqli_query($con, "SELECT total FROM project_add_equipment WHERE project_id='$pid'");
    while ($eqrow = mysqli_fetch_assoc($equip_query)) {
        $equip_total += floatval($eqrow['total']);
    }
    $grand_total = $emp_total + $mat_total + $equip_total;
    $projects_by_category[$cat][] = [
        'name' => $proj['project'],
        'total' => round($grand_total, 2)
    ];
    $totals_by_category[$cat] += $grand_total;
}

// Handle AJAX password change (like pm_profile.php)
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

// Helper function for short number formatting
function short_number_format(
    $num,
    $precision = 1
) {
    if ($num >= 1000000000000) {
        return number_format($num / 1000000000000, $precision) . 't';
    } elseif ($num >= 1000000000) {
        return number_format($num / 1000000000, $precision) . 'b';
    } elseif ($num >= 1000000) {
        return number_format($num / 1000000, $precision) . 'm';
    } elseif ($num >= 1000) {
        return number_format($num / 1000, $precision) . 'k';
    } else {
        return number_format($num, 2);
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
    <title>Project Manager Dashboard</title>
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
        <img class="img img-fluid rounded-circle mb-2 sidebar-profile-img" src="<?php echo $userprofile; ?>" width="70" alt="User Profile">
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
                    <h2 class="fs-2 m-0">Dashboard</h2>
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
                <!-- Centered Clock, Date, and Icon with Background -->
                <div class="row g-3 my-2">
                  <div class="col-12 mb-3 d-flex flex-column flex-md-row align-items-center justify-content-between">
                    <!-- Left: Date Range + Export (no background) -->
                    <form class="row g-2 align-items-end flex-nowrap mb-3 mb-md-0" method="post" action="export_dashboard_pdf.php" target="_blank">
                      <div class="col-auto">
                        <label for="start_date" class="form-label mb-0">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                      </div>
                      <div class="col-auto">
                        <label for="end_date" class="form-label mb-0">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                      </div>
                      <div class="col-auto">
                        <button type="submit" class="btn btn-success mt-2 mt-md-0">
                          <i class="fas fa-file-pdf"></i> Export as PDF
                        </button>
                      </div>
                    </form>
                    <!-- Right: Clock and Date (with background) -->
                    <div class="d-flex flex-column align-items-end ms-md-auto"
                         style="background: linear-gradient(90deg, #e0f7fa 0%, #fff 100%); border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); padding: 1.2rem 2.2rem;">
                      <div class="d-flex align-items-center" style="gap: 0.5rem;">
                        <i class="fas fa-clock fa-2x text-primary"></i>
                        <span id="dashboard-clock" style="font-size:2.2rem; font-weight:bold; letter-spacing:2px;"></span>
                      </div>
                      <div id="dashboard-date" style="font-size:1.1rem; color:#009d63; font-weight:500;"></div>
                    </div>
                  </div>
                </div>
                <div class="row g-3 my-2">
                    <div class="col-md-3">
                        <div class="p-3 bg-white shadow d-flex justify-content-around align-items-center rounded card-link"
                             onclick="showCardConfirmModal('Do you want to view all expenses?', 'expenses.php')">
                            <div>
                                <h3 class="fs-2">
                                <?php
                                      $exp_query = mysqli_query($con, "SELECT SUM(expense) as total FROM expenses WHERE user_id='$userid'");
                                      $exp_result = mysqli_fetch_assoc($exp_query);
                                      echo '₱ ' . short_number_format($exp_result['total'] ?? 0);
                                      ?>
                                </h3>
                                <p class="fs-5">Total Expenses</p>
                            </div>
                            <i class="fas fa-wallet fs-1 primary-text"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white shadow d-flex justify-content-around align-items-center rounded card-link"
                             onclick="showCardConfirmModal('Do you want to view all equipment?', 'equipment.php')">
                            <div>
                                <h3 class="fs-2">
                                <?php
                                      $equip_query = mysqli_query($con, "SELECT COUNT(*) as count FROM equipment WHERE user_id='$userid'");
                                      $equip_result = mysqli_fetch_assoc($equip_query);
                                      echo number_format($equip_result['count'] ?? 0);
                                      ?>
                                </h3>
                                <p class="fs-5">Total Equipment</p>
                            </div>
                            <i class="fas fa-hand-holding-usd fs-1 primary-text"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white shadow d-flex justify-content-around align-items-center rounded card-link"
                             onclick="showCardConfirmModal('Do you want to view all projects?', 'projects.php')">
                            <div>
                                <h3 class="fs-2">
                                <?php
                                      $proj_query = mysqli_query($con, "SELECT COUNT(*) as count FROM projects WHERE user_id='$userid'");
                                      $proj_result = mysqli_fetch_assoc($proj_query);
                                      echo number_format($proj_result['count'] ?? 0);
                                      ?>
                                </h3>
                                <p class="fs-5">Total Projects</p>
                            </div>
                            <i class="fas fa-clipboard-list fs-1 primary-text"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white shadow d-flex justify-content-around align-items-center rounded card-link"
                             onclick="showCardConfirmModal('Do you want to view all employees?', 'employees.php')">
                            <div>
                                <h3 class="fs-2">
                                <?php
                                      $emp_query = mysqli_query($con, "SELECT COUNT(*) as count FROM employees WHERE user_id='$userid'");
                                      $emp_result = mysqli_fetch_assoc($emp_query);
                                      echo number_format($emp_result['count'] ?? 0);
                                      ?>
                                </h3>
                                <p class="fs-5">Total Employees</p>
                            </div>
                            <i class="fas fa-user-friends fs-1 primary-text"></i>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row my-4">
                    <div class="col-md-4 mb-4 d-flex">
                        <div class="card shadow flex-fill">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Yearly Expenses</h5>
                                <canvas id="expensesChart" height="300" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4 d-flex">
                        <div class="card shadow flex-fill">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Project Most Progress</h5>
                                <canvas id="projectProgressChart" height="300" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4 d-flex">
                        <div class="card shadow flex-fill">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Estimate Expense Project Comparison</h5>
                                <canvas id="estimateExpenseChart" height="300" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row my-4">
                    <div class="col-md-4 mb-4 d-flex">
                        <div class="card shadow flex-fill">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Estimate Expense For All Projects</h5>
                                <canvas id="allProjectsEstimateBarChart" height="300" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4 d-flex">
                        <div class="card shadow flex-fill">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Category Estimation</h5>
                                <canvas id="categoryEstimationLineChart" height="300" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4 d-flex">
                        <div class="card shadow flex-fill">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Project Category Distribution</h5>
                                <canvas id="projectCategoryBarChart" height="300" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row my-4">
                    <div class="col-md-4 mb-4 d-flex">
                        <div class="card shadow flex-fill">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">House Projects</h5>
                                <div class="mb-2 fw-bold">Average Cost Total: ₱ <?php echo count($projects_by_category['House']) > 0 ? number_format($totals_by_category['House'] / count($projects_by_category['House']), 2) : '0.00'; ?></div>
                                <canvas id="houseProjectsChart" height="300"  style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4 d-flex">
                        <div class="card shadow flex-fill">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Renovation Projects</h5>
                                <div class="mb-2 fw-bold">Average Cost Total: ₱ <?php echo count($projects_by_category['Renovation']) > 0 ? number_format($totals_by_category['Renovation'] / count($projects_by_category['Renovation']), 2) : '0.00'; ?></div>
                                <canvas id="renovationProjectsChart" height="300"  style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4 d-flex">
                        <div class="card shadow flex-fill">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Building Projects</h5>
                                <div class="mb-2 fw-bold">Average Cost Total: ₱ <?php echo count($projects_by_category['Building']) > 0 ? number_format($totals_by_category['Building'] / count($projects_by_category['Building']), 2) : '0.00'; ?></div>
                                <canvas id="buildingProjectsChart" height="300"  style="height: 300px;"></canvas>
                            </div>
                        </div>
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
    document.addEventListener('DOMContentLoaded', function() {
      const expensesCtx = document.getElementById('expensesChart').getContext('2d');
      const expensesChart = new Chart(expensesCtx, {
          type: 'line',
          data: {
              labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
              datasets: [{
                  label: 'Total Expenses',
                  data: <?php echo json_encode(array_values($monthly_expenses)); ?>,
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
    });

    const projectProgressCtx = document.getElementById('projectProgressChart').getContext('2d');
    const projectProgressChart = new Chart(projectProgressCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($top_labels); ?>,
            datasets: [
                {
                    label: 'Average Progress (%)',
                    data: <?php echo json_encode($top_progress); ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.7)'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: { display: true, text: 'Progress (%)' }
                },
                x: {
                    title: { display: true, text: 'Project' }
                }
            }
        }
    });
    // Add Estimate Expense Comparison Chart
    const estimateExpenseCtx = document.getElementById('estimateExpenseChart').getContext('2d');
    const estimateExpenseChart = new Chart(estimateExpenseCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($est_labels); ?>,
            datasets: [
                {
                    label: 'Estimated Expense (₱)',
                    data: <?php echo json_encode($est_totals); ?>,
                    borderColor: 'rgba(255, 193, 7, 0.9)',
                    backgroundColor: 'rgba(255, 193, 7, 0.2)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: 'rgba(255, 193, 7, 1)'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true } },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Estimated Expense (₱)' }
                },
                x: {
                    title: { display: true, text: 'Project' }
                }
            }
        }
    });
    // Add All Projects Estimate Bar Chart
    const allProjectsEstimateBarCtx = document.getElementById('allProjectsEstimateBarChart').getContext('2d');
    const allProjectsEstimateBarChart = new Chart(allProjectsEstimateBarCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($all_projects_label); ?>,
            datasets: [
                {
                    label: 'Total Estimated Expense (₱)',
                    data: <?php echo json_encode($all_projects_data); ?>,
                    backgroundColor: 'rgba(0, 123, 255, 0.7)'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Total Estimated Expense (₱)' }
                },
                x: {
                    title: { display: true, text: '' }
                }
            }
        }
    });
    // Add Category Estimation Line Chart
    const categoryEstimationLineCtx = document.getElementById('categoryEstimationLineChart').getContext('2d');
    const categoryEstimationLineChart = new Chart(categoryEstimationLineCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($cat_est_labels); ?>,
            datasets: [
                {
                    label: 'Estimated Expense (₱)',
                    data: <?php echo json_encode($cat_est_totals); ?>,
                    borderColor: 'rgba(0, 123, 255, 0.9)',
                    backgroundColor: 'rgba(0, 123, 255, 0.2)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: 'rgba(0, 123, 255, 1)'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true } },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Estimated Expense (₱)' }
                },
                x: {
                    title: { display: true, text: 'Category' }
                }

            }

        }
    });
    // Add Project Category Horizontal Bar Chart
    const projectCategoryBarCtx = document.getElementById('projectCategoryBarChart').getContext('2d');
    const projectCategoryBarChart = new Chart(projectCategoryBarCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($category_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($category_counts); ?>,
                backgroundColor: [
                    'rgba(40, 167, 69, 0.7)',    // Green
                    'rgba(255, 193, 7, 0.7)',    // Yellow
                    'rgba(0, 123, 255, 0.7)'     // Blue
                ]
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    beginAtZero: true,
                    title: { display: true, text: 'Number of Projects' }
                },
                y: {
                    title: { display: true, text: 'Category' }
                }
            }
        }
    });

    const houseProjects = <?php echo json_encode($projects_by_category['House']); ?>;
    const renovationProjects = <?php echo json_encode($projects_by_category['Renovation']); ?>;
    const buildingProjects = <?php echo json_encode($projects_by_category['Building']); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // House: Standard Bar Chart
        new Chart(document.getElementById('houseProjectsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: houseProjects.map(p => p.name),
                datasets: [{
                    label: 'Estimated Expense (₱)',
                    data: houseProjects.map(p => p.total),
                    backgroundColor: 'rgba(0, 123, 255, 0.7)'
                }]
            },
            options: { responsive: true }
        });

        // Renovation: Stacked Bar Chart (vertical)
        new Chart(document.getElementById('renovationProjectsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: renovationProjects.map(p => p.name),
                datasets: [{
                    label: 'Estimated Expense (₱)',
                    data: renovationProjects.map(p => p.total),
                    backgroundColor: 'rgba(255, 193, 7, 0.7)'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: true } },
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true }
                }
            }
        });

        // Building: Grouped Bar Chart (vertical, different color)
        new Chart(document.getElementById('buildingProjectsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: buildingProjects.map(p => p.name),
                datasets: [{
                    label: 'Estimated Expense (₱)',
                    data: buildingProjects.map(p => p.total),
                    backgroundColor: 'rgba(40, 167, 69, 0.7)'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: true } },
                scales: {
                    x: { stacked: false },
                    y: { stacked: false, beginAtZero: true }
                }
            }
        });
    });
    </script>
    <script>
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
    </script>
    <!-- Confirmation Modal -->
    <div class="modal fade" id="cardConfirmModal" tabindex="-1" aria-labelledby="cardConfirmModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center">
          <div class="modal-body">
            <span style="font-size: 2.5rem; color: #009d63;"><i class="fas fa-question-circle"></i></span>
            <h5 id="cardConfirmModalLabel" class="mt-3 mb-2">Are you sure?</h5>
            <p id="cardConfirmModalMsg"></p>
            <div class="d-flex justify-content-center gap-2 mt-3">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-success" id="cardConfirmModalGo">Go</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script>
function confirmAndRedirect(message, url) {
  // replaced by showCardConfirmModal
}
let cardConfirmUrl = '';
function showCardConfirmModal(message, url) {
  document.getElementById('cardConfirmModalMsg').textContent = message;
  cardConfirmUrl = url;
  var modal = new bootstrap.Modal(document.getElementById('cardConfirmModal'));
  modal.show();
  // Remove previous event listeners to avoid stacking
  const goBtn = document.getElementById('cardConfirmModalGo');
  goBtn.onclick = function() {
    window.location.href = cardConfirmUrl;
  };
}
</script>
<script>
function updateClockAndDate() {
  const now = new Date();
  const h = String(now.getHours()).padStart(2, '0');
  const m = String(now.getMinutes()).padStart(2, '0');
  const s = String(now.getSeconds()).padStart(2, '0');
  document.getElementById('dashboard-clock').textContent = `${h}:${m}:${s}`;
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  document.getElementById('dashboard-date').textContent = now.toLocaleDateString('en-US', options);
}
setInterval(updateClockAndDate, 1000);
updateClockAndDate();
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