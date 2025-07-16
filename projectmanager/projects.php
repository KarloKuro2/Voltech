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

// Default divisions per category
$default_divisions = [
    'House' => ['Foundation', 'Roof', 'Walls', 'Windows', 'Flooring', 'Plumbing', 'Electrical', 'Painting'],
    'Building' => ['Floor', 'Layout', 'Roof', 'Windows', 'Sample'],
    'Renovation' => ['Demolition', 'Structural Repairs', 'Painting', 'Finishing']
];
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
$io_filter = isset($_GET['io']) ? $_GET['io'] : 'all';

if (isset($_GET['archive'])) {
    $archive_id = intval($_GET['archive']);
    mysqli_query($con, "UPDATE projects SET archived=1 WHERE project_id='$archive_id' AND user_id='$userid'");
    header("Location: projects.php?archived=1");
    exit();
}



if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_project'])) {
    $project = mysqli_real_escape_string($con, $_POST['project']);
    $region = isset($_POST['Region']) ? mysqli_real_escape_string($con, $_POST['Region']) : '';
    $province = isset($_POST['Province']) ? mysqli_real_escape_string($con, $_POST['Province']) : '';
    $municipality = isset($_POST['Municipality']) ? mysqli_real_escape_string($con, $_POST['Municipality']) : '';
    $barangay = isset($_POST['Baranggay']) ? mysqli_real_escape_string($con, $_POST['Baranggay']) : '';
    $location = trim($region . ' ' . $province . ' ' . $municipality . ' ' . $barangay);
    $budget = floatval($_POST['budget']);
    $start_date = $_POST['start_date'];
    $deadline = $_POST['deadline'];
    $foreman = mysqli_real_escape_string($con, $_POST['foreman']);
    $category = mysqli_real_escape_string($con, $_POST['category']);
    $materials = mysqli_real_escape_string($con, $_POST['materials']);
    $billings = floatval($_POST['billings']);
    $size = isset($_POST['size']) ? floatval($_POST['size']) : null;
    $io = $_POST['io'];
    $user_id = $userid;

    $sql = "INSERT INTO projects (user_id, project, location, budget, start_date, deadline, foreman, category, materials, billings, size, io)
            VALUES ('$user_id', '$project', '$location', '$budget', '$start_date', '$deadline', '$foreman', '$category', '$materials', '$billings', '$size', '$io')";

    if (mysqli_query($con, $sql)) {
        // Get the last inserted project_id
        $new_project_id = mysqli_insert_id($con);

        // Insert default divisions for the selected category
        if (isset($default_divisions[$category])) {
            foreach ($default_divisions[$category] as $division) {
                $division_esc = mysqli_real_escape_string($con, $division);
                mysqli_query($con, "INSERT INTO project_divisions (project_id, division_name, progress) VALUES ('$new_project_id', '$division_esc', 0)");
            }
        }

        // Get foreman details
        if (!empty($foreman)) {
            $fres = mysqli_query($con, "SELECT e.employee_id, p.title as position_title, p.daily_rate FROM employees e LEFT JOIN positions p ON e.position_id = p.position_id WHERE CONCAT(e.first_name, ' ', e.last_name) = '" . mysqli_real_escape_string($con, $foreman) . "' LIMIT 1");
            if ($frow = mysqli_fetch_assoc($fres)) {
                $foreman_id = $frow['employee_id'];
                $position = mysqli_real_escape_string($con, $frow['position_title']);
                $daily_rate = floatval($frow['daily_rate']);
                // Insert into project_add_employee
                mysqli_query($con, "INSERT INTO project_add_employee (project_id, employee_id, position, daily_rate, days, schedule, total) VALUES ('$new_project_id', '$foreman_id', '$position', '$daily_rate', 0, 0, 0)");
            }
        }

        header("Location: projects.php?success=1");
        exit();
    } else {
        $forecastMessage = '<div class="alert alert-danger">Error: ' . mysqli_error($con) . '</div>';
    }
}

// (Removed auto-update for project status based on start_date and deadline)

// Fetch all employees with position 'Foreman' for the dropdown
$foreman_position_id = null;
$pos_result = mysqli_query($con, "SELECT position_id FROM positions WHERE title = 'Foreman' LIMIT 1");
if ($pos_result && $row = mysqli_fetch_assoc($pos_result)) {
    $foreman_position_id = $row['position_id'];
}
$foremen = [];
if ($foreman_position_id) {
    $emp_result = mysqli_query($con, "SELECT employee_id, first_name, last_name FROM employees WHERE position_id = '$foreman_position_id'");
    while ($emp = mysqli_fetch_assoc($emp_result)) {
        $foremen[] = $emp;
    }
}



// --- PAGINATION & SEARCH LOGIC FOR PROJECT LIST ---
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';

// Only show projects where archived=0 in the main list
$filter_sql = "user_id='$userid' AND archived=0";
if ($io_filter !== 'all') {
    $filter_sql .= " AND io='$io_filter'";
}
if ($search !== '') {
    $filter_sql .= " AND (project LIKE '%$search%' OR location LIKE '%$search%')";
}

// Count total projects for pagination
$count_query = "SELECT COUNT(*) as total FROM projects WHERE $filter_sql";
$count_result = mysqli_query($con, $count_query);
$total_projects = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_projects / $limit);


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
    <title>Project Manager Projects</title>
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
                    <h2 class="fs-2 m-0">Project</h2>
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
                  
                                <div class="card mb-5 shadow rounded-3">
                                  <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                      <h4 class="mb-0">List of Projects</h4>
                                      <a href="project_archived.php" class="btn btn-danger ms-2">View Archived Projects</a>
                                    </div>
                                    <hr>
                                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                                        <form method="get" id="statusFilterForm" class="mb-0" style="flex:0 0 auto;">
                                          <div class="form-group mb-0" style="min-width:150px;">
                                            <select class="form-control" name="io" onchange="document.getElementById('statusFilterForm').submit();">
                                              <option value="all" <?php if($io_filter == 'all') echo 'selected'; ?>>All</option>
                                              <option value="1" <?php if($io_filter == '1') echo 'selected'; ?>>On going</option>
                                              <option value="2" <?php if($io_filter == '2') echo 'selected'; ?>>Finished</option>
                                              <option value="3" <?php if($io_filter == '3') echo 'selected'; ?>>Canceled</option>
                                              <option value="4" <?php if($io_filter == '4') echo 'selected'; ?>>Pending</option>
                                            </select>
                                          </div>
                                          <?php if ($search !== ''): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
                                          <?php if ($page > 1): ?><input type="hidden" name="page" value="<?php echo $page; ?>"><?php endif; ?>
                                        </form>
                                        <form method="get" id="searchForm" class="mb-0" style="width:250px;flex:0 0 auto;">
                                          <div class="search-box position-relative">
                                            <span class="position-absolute" style="left:10px;top:50%;transform:translateY(-50%);color:#aaa;z-index:2;">
                                              <i class="fas fa-search"></i>
                                            </span>
                                            <input type="hidden" name="io" value="<?php echo $io_filter; ?>">
                                            <input type="text" class="form-control pl-4" name="search" placeholder="Search project/location" value="<?php echo htmlspecialchars($search); ?>" id="searchInput" autocomplete="off" style="padding-left:2rem;">
                                          </div>
                                        </form>
                                        <div class="ms-auto" style="flex:0 0 auto;text-align:right;">
                                          <a href="gantt.php" class="btn btn-outline-primary me-2" style="width: 180px;">
                                            <i class="fas fa-project-diagram"></i> Show Gantt Chart
                                          </a>
                                          <button class="btn btn-success" style="width:180px;" data-bs-toggle="modal" data-bs-target="#AddProjectModal">
                                            <i class="fas fa-plus"></i> New Project
                                          </button>
                                      </div>
                                  </div>
                              </div>
                                <script>
                                // Search input auto-submit (vanilla JS)
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
                                </script>

                                <!-- Project Table -->
                                <div class="table-responsive mb-0">
                                    <table class="table table-bordered table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>No.</th>
                                                <th>Project</th>
                                                <th>Status</th>
                                                <th>Location</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Query to fetch projects based on filter, search, and pagination
                                            $query = mysqli_query($con, "SELECT * FROM projects WHERE $filter_sql ORDER BY deadline DESC LIMIT $limit OFFSET $offset");
                                            $no = $offset + 1;
                                            if (mysqli_num_rows($query) > 0) {
                                                while ($row = mysqli_fetch_assoc($query)) {
                                                    $id = $row['project_id'];
                                            ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo $row['project']; ?></td>
                                                <td>
                                                    <?php
                                                    $status_labels = [
                                                        '1' => '<span class="badge bg-success">On going</span>',
                                                        '2' => '<span class="badge bg-secondary">Finished</span>',
                                                        '3' => '<span class="badge bg-danger">Canceled</span>',
                                                        '4' => '<span class="badge bg-warning text-dark">Pending</span>'
                                                    ];
                                                    echo isset($status_labels[$row['io']]) ? $status_labels[$row['io']] : '<span class="badge bg-light">Unknown</span>';
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    // If location is numeric, show 'Unknown', else show as is
                                                    echo (is_numeric($row['location'])) ? 'Unknown' : htmlspecialchars($row['location']);
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="project_details.php?id=<?php echo $id; ?>" class="btn btn-sm btn-primary text-white font-weight-bold mr-2">
                                                        <i fas fa eye></i> View More
                                                    </a>
                                                    <button class="btn btn-sm btn-secondary text-white font-weight-bold upload-files-btn mx-1"
                                                            data-project-id="<?php echo $id; ?>"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#uploadFilesModal">
                                                        <i class="fas fa-upload"></i> Files
                                                    </button>
                                                    <button class="btn btn-sm btn-danger text-white font-weight-bold archive-project" data-project-id="<?php echo $id; ?>">
                                                        <i fas fa trash "></i> Archive
                                                    </button>
                                                </td>
                                            </tr>
                                <?php
                                                }
                                            } else {
                                            ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No projects found</td>
                                            </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <nav aria-label="Page navigation" class="mt-3 mb-3">
                                  <ul class="pagination justify-content-center custom-pagination-green mb-0">
                                    <li class="page-item<?php if($page <= 1) echo ' disabled'; ?>">
                                      <a class="page-link" href="?page=<?php echo $page-1; ?>&io=<?php echo $io_filter; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                                    </li>
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                      <li class="page-item<?php if($i == $page) echo ' active'; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&io=<?php echo $io_filter; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                      </li>
                                    <?php endfor; ?>
                                    <li class="page-item<?php if($page >= $total_pages) echo ' disabled'; ?>">
                                      <a class="page-link" href="?page=<?php echo $page+1; ?>&io=<?php echo $io_filter; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                                    </li>
                                  </ul>
                                </nav>
                            </div>
                     
            </div>
    <!-- /#page-content-wrapper -->
    </div>

    <div class="modal fade" id="AddProjectModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
             <div class="modal-content">
                 <div class="modal-header">
                     <h5 class="modal-title">Add New Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                             <form method="POST" action="projects.php" id="addProjectForm">
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Project Name*</label>
                                                <input type="text" class="form-control" name="project" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Region*</label>
                                                <select class="form-control" name="Region" id="region-select" required>
                                                    <option value="" selected disabled>Select Region</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Province*</label>
                                                <select class="form-control" name="Province" id="province-select" required disabled>
                                                    <option value="" selected disabled>Select Region First</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Municipality*</label>
                                                <select class="form-control" name="Municipality" id="municipality-select" required disabled>
                                                    <option value="" selected disabled>Select Province First</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Baranggay*</label>
                                                <select class="form-control" name="Baranggay" id="barangay-select" required disabled>
                                                     <option value="" selected disabled>Select Municipality First</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Budget (₱)*</label>
                                                <input type="number" step="0.01" class="form-control" name="budget" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Start Date*</label>
                                                <input type="date" class="form-control" name="start_date" required>
                            </div>
                                            <div class="form-group">
                                                <label>Deadline*</label>
                                                 <input type="date" class="form-control" name="deadline" required>
                        </div>
                    </div>
                                        <div class="col-md-6">
                                            <div class="form-group" style="display:none;">
                                                <label>Status*</label>
                                                <select class="form-control" name="io" id="status-select" disabled>
                                                    <option value="4" selected>Pending</option>
                                                </select>
                                            </div>
                                                <input type="hidden" name="io" value="4">
                                            <div class="form-group">
                                                <label>Foreman</label>
                                                <select class="form-control" name="foreman">
                                                    <option value="" disabled selected>Select Foreman</option>
                                                        <?php foreach (
                                                            isset(
                                                                 $foremen
                                                                ) ? $foremen : [] as $foreman): ?>
                                                             <option value="<?php echo htmlspecialchars($foreman['first_name'] . ' ' . $foreman['last_name']); ?>">
                                                                <?php echo htmlspecialchars($foreman['first_name'] . ' ' . $foreman['last_name']); ?>
                                                             </option>
                                                        <?php endforeach; ?>
                                                 </select>
                                             </div>
                                            <div class="form-group">
                                                <label>Category*</label>
                                                    <select class="form-control" name="category" required>
                                                        <option value="" disabled selected>Select Category</option>
                                                        <option value="House">House</option>
                                                        <option value="Building">Building</option>
                                                        <option value="Renovation">Renovation</option>
                                                    </select>
                                            </div>
                                             <div class="form-group">
                                                 <label>Size (m²)*</label>
                                                    <input type="number" step="0.01" class="form-control" name="size" required>
                            </div>
                                             <div class="form-group">
                                                 <label>Initial Billings (₱)</label>
                                                     <input type="number" step="0.01" class="form-control" name="billings" value="0">
                        </div>
                    </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>Materials might be used</label>
                                                     <textarea class="form-control" name="materials" rows="3"></textarea>
                </div>
                            </div>
                        </div>
                    </div>
                                        <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="add_project" class="btn btn-primary">Save Project</button>
                                    </div>
                                </form>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
        </div>

       

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   

   

    <div class="modal fade" id="archiveModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Archive Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to archive this project?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmArchive">Archive</button>
            </div>
        </div>
    </div>
    </div>

    <!-- JS for Archive button -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let projectToArchive = null;
            const archiveModalEl = document.getElementById('archiveModal');
            const archiveModal = new bootstrap.Modal(archiveModalEl);

            document.querySelectorAll('.archive-project').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                e.preventDefault();
                projectToArchive = this.getAttribute('data-project-id');
                archiveModal.show();
                });
            });

            document.getElementById('confirmArchive').addEventListener('click', function() {
                if (projectToArchive) {
                window.location.href = 'projects.php?archive=' + projectToArchive;
                }
                archiveModal.hide();
            });

            archiveModalEl.addEventListener('hidden.bs.modal', function() {
                projectToArchive = null;
            });
        });
    </script>

    <!-- Place this at the end of the body, after jQuery is loaded -->
    <script>
        let phData = null;
        fetch('philippines.json')
            .then(response => response.json())
            .then(data => {
                phData = data;
                // Populate regions
                const regionSelect = document.getElementById('region-select');
                for (const regionKey in phData) {
                    const region = phData[regionKey];
                    const opt = document.createElement('option');
                    opt.value = region.region_name;
                    opt.textContent = region.region_name;
                    regionSelect.appendChild(opt);
                }
            });

        document.getElementById('region-select').addEventListener('change', function() {
            const regionName = this.value;
            const regionKey = Object.keys(phData).find(key => phData[key].region_name === regionName);
            const provinceSelect = document.getElementById('province-select');
            const municipalitySelect = document.getElementById('municipality-select');
            const barangaySelect = document.getElementById('barangay-select');
            provinceSelect.innerHTML = '<option value="" selected disabled>Select Province</option>';
            municipalitySelect.innerHTML = '<option value="" selected disabled>Select Province First</option>';
            barangaySelect.innerHTML = '<option value="" selected disabled>Select Municipality First</option>';
            provinceSelect.disabled = false;
            municipalitySelect.disabled = true;
            barangaySelect.disabled = true;
            for (const provinceName in phData[regionKey].province_list) {
                const opt = document.createElement('option');
                opt.value = provinceName;
                opt.textContent = provinceName;
                provinceSelect.appendChild(opt);
                }
            });

        document.getElementById('province-select').addEventListener('change', function() {
            const regionName = document.getElementById('region-select').value;
            const regionKey = Object.keys(phData).find(key => phData[key].region_name === regionName);
            const provinceName = this.value;
            const municipalitySelect = document.getElementById('municipality-select');
            const barangaySelect = document.getElementById('barangay-select');
            municipalitySelect.innerHTML = '<option value="" selected disabled>Select Municipality</option>';
            barangaySelect.innerHTML = '<option value="" selected disabled>Select Municipality First</option>';
            municipalitySelect.disabled = false;
            barangaySelect.disabled = true;
            for (const municipalityName in phData[regionKey].province_list[provinceName].municipality_list) {
                const opt = document.createElement('option');
                opt.value = municipalityName;
                opt.textContent = municipalityName;
                municipalitySelect.appendChild(opt);
                }
            });

        document.getElementById('municipality-select').addEventListener('change', function() {
            const regionName = document.getElementById('region-select').value;
            const regionKey = Object.keys(phData).find(key => phData[key].region_name === regionName);
            const provinceName = document.getElementById('province-select').value;
            const municipalityName = this.value;
            const barangaySelect = document.getElementById('barangay-select');
            barangaySelect.innerHTML = '<option value="" selected disabled>Select Barangay</option>';
            barangaySelect.disabled = false;
            const barangayList = phData[regionKey].province_list[provinceName].municipality_list[municipalityName].barangay_list;
            for (const barangay of barangayList) {
                const opt = document.createElement('option');
                opt.value = barangay;
                opt.textContent = barangay;
                barangaySelect.appendChild(opt);
        }
    });
    </script>

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
        // Show feedback modal if redirected after add or archive
        <?php if (isset($_GET['success'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
        showFeedbackModal(true, 'Project saved successfully.', '', 'success');
        });
        <?php elseif (isset($_GET['archived'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
        showFeedbackModal(true, 'Project archived successfully.', '', 'archived');
        });
        <?php endif; ?>
        </script>

        <?php if (isset($forecastMessage) && strpos($forecastMessage, 'Error:') !== false): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
        showFeedbackModal(false, 'Failed to save project.', <?php echo json_encode(strip_tags($forecastMessage)); ?>, 'forecastMessage');
        });
    </script>
<?php endif; ?>

    <script>
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
    </script>

    <script>
document.addEventListener('DOMContentLoaded', function() {
  // Set min for Start Date to today
  var startDateInput = document.querySelector('#AddProjectModal input[name="start_date"]');
  if (startDateInput) {
    var today = new Date();
    var yyyy = today.getFullYear();
    var mm = String(today.getMonth() + 1).padStart(2, '0');
    var dd = String(today.getDate()).padStart(2, '0');
    var minDate = yyyy + '-' + mm + '-' + dd;
    startDateInput.setAttribute('min', minDate);
  }
  // Set min for Deadline to tomorrow
  var deadlineInput = document.querySelector('#AddProjectModal input[name="deadline"]');
  if (deadlineInput) {
    var tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    var yyyy = tomorrow.getFullYear();
    var mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
    var dd = String(tomorrow.getDate()).padStart(2, '0');
    var minDeadline = yyyy + '-' + mm + '-' + dd;
    deadlineInput.setAttribute('min', minDeadline);
  }
  // Prevent submit if start date is in the past or deadline is today/past
  var addProjectForm = document.getElementById('addProjectForm');
  if (addProjectForm && startDateInput && deadlineInput) {
    addProjectForm.addEventListener('submit', function(e) {
      var selectedStart = startDateInput.value;
      var selectedDeadline = deadlineInput.value;
      var now = new Date();
      now.setHours(0,0,0,0);
      // Start Date check
      if (selectedStart) {
        var selectedStartDate = new Date(selectedStart + 'T00:00:00');
        if (selectedStartDate < now) {
          startDateInput.setCustomValidity('Start Date cannot be in the past.');
          startDateInput.reportValidity();
          e.preventDefault();
          return;
        } else {
          startDateInput.setCustomValidity('');
        }
      }
      // Deadline check
      if (selectedDeadline) {
        var selectedDeadlineDate = new Date(selectedDeadline + 'T00:00:00');
        var tomorrow = new Date(now);
        tomorrow.setDate(tomorrow.getDate() + 1);
        if (selectedDeadlineDate < tomorrow) {
          deadlineInput.setCustomValidity('Deadline must be after today.');
          deadlineInput.reportValidity();
          e.preventDefault();
          return;
        } else {
          deadlineInput.setCustomValidity('');
        }
      }
    });
    startDateInput.addEventListener('input', function() {
      startDateInput.setCustomValidity('');
    });
    deadlineInput.addEventListener('input', function() {
      deadlineInput.setCustomValidity('');
    });
  }
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

<!-- Upload/Download Files Modal -->
<div class="modal fade" id="uploadFilesModal" tabindex="-1" aria-labelledby="uploadFilesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="uploadFilesModalLabel">Upload Project Permits & Clearances</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2 mb-3" style="font-size: 0.97rem;">
          Upload images for the following required permits and clearances for this project:
          <ul class="mb-0 ps-3">
            <li>LGU Permit</li>
            <li>Barangay Clearance</li>
            <li>Fire Clearance</li>
            <li>Occupancy Permit</li>
          </ul>
        </div>
        <form class="mb-3 single-upload-form" method="POST" action="upload_lgu.php" enctype="multipart/form-data">
          <input type="hidden" name="project_id" id="modal_project_id_lgu">
          <img id="preview_file_photo_lgu" class="img-thumbnail mb-2 d-none" style="max-width: 220px; max-height: 220px; display:block; margin:auto;" />
          <label class="form-label">LGU Permit</label>
          <input type="file" class="form-control file-input-preview" name="file_photo" accept="image/*">
          <div class="invalid-feedback">Please select a photo before uploading.</div>
          <button type="submit" class="btn btn-success mt-2">Upload LGU Permit</button>
        </form>
        <form class="mb-3 single-upload-form" method="POST" action="upload_barangay.php" enctype="multipart/form-data">
          <input type="hidden" name="project_id" id="modal_project_id_barangay">
          <img id="preview_file_photo_barangay" class="img-thumbnail mb-2 d-none" style="max-width: 220px; max-height: 220px; display:block; margin:auto;" />
          <label class="form-label">Barangay Clearance</label>
          <input type="file" class="form-control file-input-preview" name="file_photo" accept="image/*">
          <div class="invalid-feedback">Please select a photo before uploading.</div>
          <button type="submit" class="btn btn-success mt-2">Upload Barangay Clearance</button>
        </form>
        <form class="mb-3 single-upload-form" method="POST" action="upload_fire.php" enctype="multipart/form-data">
          <input type="hidden" name="project_id" id="modal_project_id_fire">
          <img id="preview_file_photo_fire" class="img-thumbnail mb-2 d-none" style="max-width: 220px; max-height: 220px; display:block; margin:auto;" />
          <label class="form-label">Fire Clearance</label>
          <input type="file" class="form-control file-input-preview" name="file_photo" accept="image/*">
          <div class="invalid-feedback">Please select a photo before uploading.</div>
          <button type="submit" class="btn btn-success mt-2">Upload Fire Clearance</button>
        </form>
        <form class="mb-3 single-upload-form" method="POST" action="upload_occupancy.php" enctype="multipart/form-data">
          <input type="hidden" name="project_id" id="modal_project_id_occupancy">
          <img id="preview_file_photo_occupancy" class="img-thumbnail mb-2 d-none" style="max-width: 220px; max-height: 220px; display:block; margin:auto;" />
          <label class="form-label">Occupancy Permit</label>
          <input type="file" class="form-control file-input-preview" name="file_photo" accept="image/*">
          <div class="invalid-feedback">Please select a photo before uploading.</div>
          <button type="submit" class="btn btn-success mt-2">Upload Occupancy Permit</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
// Set project_id for all forms in the modal when opening
const uploadFilesModal = document.getElementById('uploadFilesModal');
uploadFilesModal.addEventListener('show.bs.modal', function (event) {
  const triggerBtn = event.relatedTarget;
  let projectId = null;
  if (triggerBtn && triggerBtn.getAttribute('data-project-id')) {
    projectId = triggerBtn.getAttribute('data-project-id');
  } else {
    // fallback: try to get from a global or selection
    projectId = document.getElementById('modal_project_id')?.value || '';
  }
  document.getElementById('modal_project_id_lgu').value = projectId;
  document.getElementById('modal_project_id_barangay').value = projectId;
  document.getElementById('modal_project_id_fire').value = projectId;
  document.getElementById('modal_project_id_occupancy').value = projectId;
});
// Image preview for each file input
const previewMap = {
  'lgu': 'preview_file_photo_lgu',
  'barangay': 'preview_file_photo_barangay',
  'fire': 'preview_file_photo_fire',
  'occupancy': 'preview_file_photo_occupancy'
};
document.querySelectorAll('.single-upload-form').forEach(function(form) {
  const fileInput = form.querySelector('input[type="file"]');
  const formId = form.getAttribute('action').replace('upload_', '').replace('.php', '');
  const previewImg = document.getElementById('preview_file_photo_' + formId);
  fileInput.addEventListener('change', function() {
    if (fileInput.files && fileInput.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        previewImg.src = e.target.result;
        previewImg.classList.remove('d-none');
      };
      reader.readAsDataURL(fileInput.files[0]);
    } else {
      previewImg.classList.add('d-none');
      previewImg.src = '';
    }
  });
});
</script>

<?php if (isset($_GET['upload_success'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    showFeedbackModal(true, 'Files uploaded successfully.', '', 'upload_success');
});
</script>
<?php endif; ?>
<?php if (isset($_GET['upload_error'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    showFeedbackModal(false, 'Failed to upload files.', <?php echo json_encode(strip_tags($_GET['upload_error'])); ?>, 'upload_error');
});
</script>
<?php endif; ?>

<!-- Add JS for client-side validation of file input before upload -->
<script>
document.querySelectorAll('.single-upload-form').forEach(function(form) {
  const fileInput = form.querySelector('input[type="file"]');
  const invalidFeedback = fileInput.nextElementSibling; // the .invalid-feedback div

  form.addEventListener('submit', function(e) {
    if (!fileInput.files || !fileInput.files[0]) {
      e.preventDefault();
      fileInput.classList.add('is-invalid');
      if (invalidFeedback) invalidFeedback.style.display = 'block';
      fileInput.focus();
    } else {
      fileInput.classList.remove('is-invalid');
      if (invalidFeedback) invalidFeedback.style.display = 'none';
    }
  });

  // Hide error when user selects a file
  fileInput.addEventListener('change', function() {
    if (fileInput.files && fileInput.files[0]) {
      fileInput.classList.remove('is-invalid');
      if (invalidFeedback) invalidFeedback.style.display = 'none';
    }
  });
});
</script>

</body>

</html>