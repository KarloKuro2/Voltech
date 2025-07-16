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


$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($con, $_GET['type']) : '';
$date_range = isset($_GET['date_range']) ? mysqli_real_escape_string($con, $_GET['date_range']) : '';

// Build WHERE clause
$where_conditions = ["user_id = '$userid'"];
if (!empty($search)) {
    $where_conditions[] = "(expensecategory LIKE '%$search%' OR description LIKE '%$search%')";
}
if (!empty($type_filter)) {
    $where_conditions[] = "expensecategory = '$type_filter'";
}
if (!empty($date_range)) {
    switch($date_range) {
        case 'today':
            $where_conditions[] = "DATE(expensedate) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "YEARWEEK(expensedate, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'month':
            $where_conditions[] = "YEAR(expensedate) = YEAR(CURDATE()) AND MONTH(expensedate) = MONTH(CURDATE())";
            break;
        case 'year':
            $where_conditions[] = "YEAR(expensedate) = YEAR(CURDATE())";
            break;
    }
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Get total number of records with filters
$total_query = "SELECT COUNT(*) as total FROM order_expenses $where_clause";
$total_result = mysqli_query($con, $total_query);
$total_row = mysqli_fetch_assoc($total_result);
$total_items = $total_row['total'];
$total_pages = ceil($total_items / $items_per_page);

// Expense types for filter dropdown
$expense_types = ["Materials", "Labor", "Equipment", "Transportation", "Site Costs", "Office Supplies", "Others"];

// Get total expenses and this month's total (always define before HTML)
$total_expense_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as total FROM order_expenses WHERE user_id = '$userid'");
$total_expense = mysqli_fetch_assoc($total_expense_query);

$month_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as month_total 
    FROM order_expenses 
    WHERE user_id = '$userid' 
    AND MONTH(expensedate) = MONTH(CURRENT_DATE())
    AND YEAR(expensedate) = YEAR(CURRENT_DATE())");
$month_total = mysqli_fetch_assoc($month_query);

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

// Total Expenses (All Time)
$total_expense_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as total FROM order_expenses WHERE user_id = '$userid'");
$total_expense = mysqli_fetch_assoc($total_expense_query);

// Total Expenses This Month
$month_expense_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as total FROM order_expenses WHERE user_id = '$userid' AND MONTH(expensedate) = MONTH(CURDATE()) AND YEAR(expensedate) = YEAR(CURDATE())");
$month_expense = mysqli_fetch_assoc($month_expense_query);

// Total Expenses Last 7 Days (rolling week)
$week_expense_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as total FROM order_expenses WHERE user_id = '$userid' AND expensedate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)");
$week_expense = mysqli_fetch_assoc($week_expense_query);

// Total Expenses This Year
$year_expense_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as total FROM order_expenses WHERE user_id = '$userid' AND YEAR(expensedate) = YEAR(CURDATE())");
$year_expense = mysqli_fetch_assoc($year_expense_query);

// Get paginated results
$query = "SELECT expense_id, expensedate, expensecategory, expense, description 
          FROM order_expenses $where_clause 
          ORDER BY expensedate DESC 
          LIMIT $offset, $items_per_page";
$result = mysqli_query($con, $query);

// Remove inline add, update, and delete logic (to be moved to separate files)

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
    <title>Procurement Officer Orders</title>
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
                    <h2 class="fs-2 m-0">Orders Expenses</h2>
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
                  <div class="card-body p-4">
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                      <h4 class="mb-0">Orders Expense</h4>
                      <div>
                        <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                          <i class="fas fa-plus"></i> Add Expense
                        </button>
                        <a href="#" class="btn btn-danger" id="exportPdfBtn">
                          <i class="fas fa-file-pdf"></i> Export as PDF
                        </a>
                      </div>
                    </div>
                    <hr>
                    <div class="mb-3 d-flex flex-wrap align-items-center justify-content-between">
                      <form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-0" id="searchFilterForm" style="min-width:220px; max-width:320px;">
                        <div class="input-group w-100">
                          <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                          <input type="text" class="form-control border-start-0" name="search" placeholder="Search type/description" value="<?php echo htmlspecialchars($search); ?>" autocomplete="off" id="searchInput1">
                        </div>
                      </form>
                      <div class="ms-auto fw-bold text-success" style="font-size:1.1rem;">
                        Total Expenses: ₱<?php echo number_format($total_expense['total'], 2); ?>
                      </div>
                    </div>
                    <div class="table-responsive mb-0">
                      <table class="table table-bordered table-striped mb-0">
                        <thead class="thead-dark">
                          <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th class="text-center">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php 
                          $count = 1;
                          while ($row = mysqli_fetch_assoc($result)): 
                          ?>
                          <tr>
                            <td><?php echo $count + $offset; ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['expensedate'])); ?></td>
                            <td><?php echo htmlspecialchars($row['expensecategory']); ?></td>
                            <td>₱<?php echo number_format($row['expense'], 2); ?></td>
                            <td class="text-truncate" title="<?php echo htmlspecialchars($row['description']); ?>">
                              <?php echo htmlspecialchars($row['description']); ?>
                            </td>
                            <td class="text-center">
                              <div class="action-buttons">
                                <a href="#" class="btn btn-sm btn-primary text-white font-weight-bold" data-bs-toggle="modal" data-bs-target="#viewExpenseModal<?php echo $row['expense_id']; ?>">
                                  <i class="fas fa-eye"></i> View More
                                </a>
                              </div>
                            </td>
                          </tr>
                          <?php 
                          $count++;
                          endwhile; 
                          if (mysqli_num_rows($result) == 0): 
                          ?>
                          <tr>
                            <td colspan="6" class="text-center py-4">No expenses found</td>
                          </tr>
                          <?php endif; ?>
                        </tbody>
                      </table>
                    </div>
                    <nav aria-label="Page navigation" class="mt-4">
                      <ul class="pagination justify-content-center custom-pagination-green">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                          <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . urlencode($type_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                            <span class="sr-only">Previous</span>
                          </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                          <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . urlencode($type_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>">
                              <?php echo $i; ?>
                            </a>
                          </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                          <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . urlencode($type_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                            <span class="sr-only">Next</span>
                          </a>
                        </li>
                      </ul>
                    </nav>
                  </div>
                </div>
            </div>
        </div>

        <!-- Add Expense Modal -->
        <div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addExpenseModalLabel">Add New Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="add_order_expense.php" method="POST">
                        <div class="modal-body">
                            <div class="form-group">
                                <label><b>Expense Type</b></label>
                                <select class="form-control" name="expensecategory" required>
                                    <option value="Others">Others</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><b>Amount (₱)</b></label>
                                <input type="number" step="0.01" class="form-control" name="expenseamount" placeholder="Enter amount" required>
                            </div>
                            
                            <div class="form-group">
                                <label><b>Date</b></label>
                                <input type="date" class="form-control" name="expensedate" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label><b>Description/Remarks</b></label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Enter description or remarks" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="add" class="btn btn-success">Add Expense</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View and Edit Modals -->
        <?php 
        // Reset the result pointer
        mysqli_data_seek($result, 0);
        
        while ($row = mysqli_fetch_assoc($result)): 
        ?>
        <!-- View Expense Modal -->
        <div class="modal fade" id="viewExpenseModal<?php echo $row['expense_id']; ?>" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Expense Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row">
                            <dt class="col-sm-4">Date:</dt>
                            <dd class="col-sm-8"><?php echo date('M d, Y', strtotime($row['expensedate'])); ?></dd>
                            
                            <dt class="col-sm-4">Type:</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($row['expensecategory']); ?></dd>
                            
                            <dt class="col-sm-4">Amount:</dt>
                            <dd class="col-sm-8">₱<?php echo number_format($row['expense'], 2); ?></dd>
                        </dl>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editExpenseModal<?php echo $row['expense_id']; ?>" data-bs-dismiss="modal">Edit</button>
                        <a href="#" class="btn btn-danger btn-sm text-white delete-expense-btn" data-id="<?php echo $row['expense_id']; ?>" data-name="<?php echo htmlspecialchars($row['expensecategory']); ?>">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Expense Modal -->
        <div class="modal fade" id="editExpenseModal<?php echo $row['expense_id']; ?>" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="update_order_expense.php" method="POST">
                        <input type="hidden" name="expense_id" value="<?php echo $row['expense_id']; ?>">
                        <div class="modal-body">
                            <div class="form-group">
                                <label><b>Expense Type</b></label>
                                <select class="form-control" name="expensecategory" required>
                                    <option value="Materials" <?php echo ($row['expensecategory'] == 'Materials') ? 'selected' : ''; ?>>Materials</option>
                                    <option value="Labor" <?php echo ($row['expensecategory'] == 'Labor') ? 'selected' : ''; ?>>Labor</option>
                                    <option value="Equipment" <?php echo ($row['expensecategory'] == 'Equipment') ? 'selected' : ''; ?>>Equipment</option>
                                    <option value="Transportation" <?php echo ($row['expensecategory'] == 'Transportation') ? 'selected' : ''; ?>>Transportation</option>
                                    <option value="Site Costs" <?php echo ($row['expensecategory'] == 'Site Costs') ? 'selected' : ''; ?>>Site Costs</option>
                                    <option value="Office Supplies" <?php echo ($row['expensecategory'] == 'Office Supplies') ? 'selected' : ''; ?>>Office Supplies</option>
                                    <option value="Others" <?php echo ($row['expensecategory'] == 'Others') ? 'selected' : ''; ?>>Others</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><b>Amount (₱)</b></label>
                                <input type="number" step="0.01" class="form-control" name="expenseamount" value="<?php echo htmlspecialchars($row['expense']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label><b>Date</b></label>
                                <input type="date" class="form-control" name="expensedate" value="<?php echo htmlspecialchars($row['expensedate']); ?>" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update" class="btn btn-primary">Update Expense</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endwhile; ?>

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
document.addEventListener('DOMContentLoaded', function() {
    var searchInput1 = document.getElementById('searchInput1');
    var searchForm1 = document.getElementById('searchFilterForm'); // Changed from searchForm1 to searchFilterForm
    var typeFilter = document.getElementById('typeFilter');
    var dateRangeFilter = document.getElementById('dateRangeFilter');
    var searchForm2 = document.getElementById('searchForm2'); // This variable is no longer used
    if (searchInput1 && searchForm1) {
        var searchTimeout;
        searchInput1.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                searchForm1.submit();
            }, 400);
        });
    }
    // Removed typeFilter and dateRangeFilter event listeners as they are no longer in the form
});
</script>
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
<?php if (isset($_GET['success'])): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, 'Expense added successfully!', '', 'success');
});
<?php elseif (isset($_GET['updated'])): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, 'Expense updated successfully!', '', 'updated');
});
<?php elseif (isset($_GET['deleted'])): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, 'Expense deleted successfully!', '', 'deleted');
});
<?php elseif (isset($_GET['error'])): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(false, 'An error occurred. Please try again.', '', 'error');
});
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'validation'): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(false, 'All fields are required and amount must be greater than zero.', '', 'error');
});
<?php endif; ?>
</script>
<div class="modal fade" id="deleteExpenseModal" tabindex="-1" aria-labelledby="deleteExpenseModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteExpenseModalLabel">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete <strong id="expenseName"></strong>?</p>
        <p class="text-danger">This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="confirmDeleteExpense" class="btn btn-danger" data-delete-url="delete_order_expense.php?id=<?php echo $row['expense_id']; ?>">Delete</a>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.delete-expense-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var expId = this.getAttribute('data-id');
      var expName = this.getAttribute('data-name');
      document.getElementById('expenseName').textContent = expName;
      var confirmDelete = document.getElementById('confirmDeleteExpense');
      confirmDelete.setAttribute('href', 'delete_order_expense.php?id=' + expId);
      var modal = new bootstrap.Modal(document.getElementById('deleteExpenseModal'));
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


<div class="modal fade" id="exportPdfModal" tabindex="-1" aria-labelledby="exportPdfModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exportPdfModalLabel">Export as PDF</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to export the expenses as PDF?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="export_expenses_pdf.php" id="confirmExportPdf" class="btn btn-danger">Export</a>
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
</script>
<!-- Export PDF Confirmation Modal -->

<script>
document.addEventListener('DOMContentLoaded', function() {
  var exportBtn = document.getElementById('exportPdfBtn');
  if (exportBtn) {
    exportBtn.addEventListener('click', function(e) {
      e.preventDefault();
      var modal = new bootstrap.Modal(document.getElementById('exportPdfModal'));
      modal.show();
    });
  }
});
</script>
</body>

</html>