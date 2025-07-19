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
$category_filter = isset($_GET['category']) ? mysqli_real_escape_string($con, $_GET['category']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : '';
$supplier_filter = isset($_GET['supplier']) ? mysqli_real_escape_string($con, $_GET['supplier']) : '';

// Build WHERE clause
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(material_name LIKE '%$search%' OR category LIKE '%$search%' OR supplier_name LIKE '%$search%')";
}
if (!empty($category_filter)) {
    $where_conditions[] = "category = '$category_filter'";
}
if (!empty($status_filter)) {
    $where_conditions[] = "status = '$status_filter'";
}
if (!empty($supplier_filter)) {
    $where_conditions[] = "supplier_name = '$supplier_filter'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Get total number of records with filters
$total_query = "SELECT COUNT(*) as total FROM materials $where_clause";
$total_result = $con->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_items = $total_row['total'];
$total_pages = ceil($total_items / $items_per_page);

// Get distinct values for filters
$categories_query = "SELECT DISTINCT category FROM materials ORDER BY category";
$statuses_query = "SELECT DISTINCT status FROM materials ORDER BY status";
$suppliers_query = "SELECT DISTINCT supplier_name FROM materials ORDER BY supplier_name";

$categories = $con->query($categories_query);
$statuses = $con->query($statuses_query);
$suppliers = $con->query($suppliers_query);

// Add these after $con = new mysqli(...);
$all_suppliers = $con->query("SELECT id, supplier_name FROM suppliers WHERE approval = 'Approved' ORDER BY supplier_name");
$all_warehouses = $con->query("SELECT id, warehouse FROM warehouses WHERE approval = 'Approved' ORDER BY warehouse");
$all_categories = $con->query("SELECT material_category FROM materials_category ORDER BY material_category");

// Fetch materials from database with pagination and filters
$sql = "SELECT * FROM materials $where_clause";
if (!empty($where_clause)) {
    $sql = "SELECT * FROM materials $where_clause AND approval = 'Approved' LIMIT $offset, $items_per_page";
} else {
    $sql = "SELECT * FROM materials WHERE approval = 'Approved' LIMIT $offset, $items_per_page";
}
$result = $con->query($sql);

// Add short_number_format function for summary cards
function short_number_format($num, $precision = 1) {
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
    <title>Procurement Officer Materials</title>
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
                    <h2 class="fs-2 m-0">Materials</h2>
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
                
                <!-- TABLE CARD -->
                <div class="card mb-5 shadow rounded-3">

                    <div class="card-body p-4">
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">Materials Management</h4>
                            <div>
                                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                                    <i class="fas fa-plus"></i> Add Material
                                            </button>
                                            <a href="#" class="btn btn-danger exportPdfBtn">
                                                <i class="fas fa-file-pdf"></i> Export as PDF
                                            </a>
                                        </div>
                                    </div>
                        <hr>
                        <form method="GET" class="d-flex flex-wrap gap-2 mb-3" id="searchForm" style="min-width:260px; max-width:900px;">
                                <div class="input-group" style="min-width:220px; max-width:320px;">
                                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" class="form-control border-start-0" name="search" placeholder="Search material/category/supplier" value="<?php echo htmlspecialchars($search); ?>" id="searchInput" autocomplete="off">
                                </div>
                                <select name="category" class="form-control" style="max-width:180px;" id="categoryFilter">
                                        <option value="">All Categories</option>
                                    <?php $categories->data_seek(0); while($cat = $categories->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category_filter === $cat['category'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['category']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                <select name="status" class="form-control" style="max-width:180px;" id="statusFilter">
                                        <option value="">All Status</option>
                                        <option value="Available" <?php echo $status_filter === 'Available' ? 'selected' : ''; ?>>Available</option>
                                        <option value="In Use" <?php echo $status_filter === 'In Use' ? 'selected' : ''; ?>>In Use</option>
                                        <option value="Low Stock" <?php echo $status_filter === 'Low Stock' ? 'selected' : ''; ?>>Low Stock</option>
                                        <option value="Damaged" <?php echo $status_filter === 'Damaged' ? 'selected' : ''; ?>>Damaged</option>
                                    </select>
                                <select name="supplier" class="form-control" style="max-width:180px;" id="supplierFilter">
                                        <option value="">All Suppliers</option>
                                    <?php $suppliers->data_seek(0); while($sup = $suppliers->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($sup['supplier_name']); ?>" <?php echo $supplier_filter === $sup['supplier_name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($sup['supplier_name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                        </form>
                            <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                var searchInput = document.getElementById('searchInput');
                                var categoryFilter = document.getElementById('categoryFilter');
                                var statusFilter = document.getElementById('statusFilter');
                                var supplierFilter = document.getElementById('supplierFilter');
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
                                if (categoryFilter && searchForm) {
                                    categoryFilter.addEventListener('change', function() {
                                        searchForm.submit();
                                    });
                                }
                                if (statusFilter && searchForm) {
                                    statusFilter.addEventListener('change', function() {
                                        searchForm.submit();
                                    });
                                }
                                if (supplierFilter && searchForm) {
                                    supplierFilter.addEventListener('change', function() {
                                        searchForm.submit();
                                    });
                                }
                            });
                            </script>
                    
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                            <thead class="thead-dark">
                                <tr>
                                        <th>No</th>
                                    <th>Category</th>
                                    <th>Material Name</th>
                                    <th>Quantity</th>
                                    <th>Unit</th>
                                    <th>Status</th>
                                    <th>Supplier</th>
                                    <th>Total Amount</th>
                                        <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                    <?php 
                                    $rownum = 1 + $offset;
                                    $result->data_seek(0); while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                        <td><?php echo $rownum++; ?></td>
                                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td><?php echo htmlspecialchars($row['material_name']); ?></td>
                                    <td><?php echo $row['quantity']; ?></td>
                                    <td><?php echo htmlspecialchars($row['unit']); ?></td>
                                    <td>
                                            <span class="badge bg-<?php echo $row['status'] == 'Low Stock' ? 'warning' : ($row['status'] == 'Available' ? 'success' : ($row['status'] == 'In Use' ? 'primary' : 'danger')); ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                    <td>₱ <?php echo number_format($row['total_amount'], 2); ?></td>
                                        <td class="text-center">
                                            <a href="#" class="btn btn-sm btn-primary text-white font-weight-bold" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['id']; ?>">
                                                <i class="fas fa-eye"></i> View More
                                        </a>
                                    </td>
                                </tr>
                                <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                    <h5 class="modal-title" id="viewModalLabel<?php echo $row['id']; ?>">Material Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="container-fluid">
                                                    <div class="row mb-3">
                                                        <div class="col-md-6 mb-2">
                                                            <h4 class="fw-bold mb-0 text-primary"><i class="fas fa-cube me-2"></i><?php echo htmlspecialchars($row['material_name']); ?></h4>
                                                        </div>
                                                        <div class="col-md-6 mb-2 text-md-end">
                                                            <span class="fw-bold text-secondary"><i class="fas fa-truck me-1"></i>Supplier:</span> <?php echo htmlspecialchars($row['supplier_name']); ?>
                                                        </div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-6 mb-2">
                                                            <span class="fw-bold text-secondary"><i class="fas fa-info-circle me-1"></i>Status:</span> <span class="badge bg-<?php echo $row['status'] == 'Low Stock' ? 'warning' : ($row['status'] == 'Available' ? 'success' : ($row['status'] == 'In Use' ? 'primary' : 'danger')); ?>"><?php echo $row['status']; ?></span>
                                                        </div>
                                                        <div class="col-md-6 mb-2 text-md-end">
                                                            <span class="fw-bold text-secondary"><i class="fas fa-map-marker-alt me-1"></i>Location:</span> <?php echo htmlspecialchars($row['location']); ?>
                                                        </div>
                                                    </div>
                                                    <div class="row justify-content-center">
                                                        <div class="col-12 col-md-10">
                                                            <div class="card shadow-sm border-0 mb-2">
                                                                <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
                                                                    <div class="mb-2 flex-fill">
                                                                        <span class="fw-bold text-muted"><i class="fas fa-sort-numeric-up me-1"></i>Quantity:</span> <?php echo $row['quantity']; ?>
                                                                    </div>
                                                                    <div class="mb-2 flex-fill">
                                                                        <span class="fw-bold text-muted"><i class="fas fa-ruler me-1"></i>Unit:</span> <?php echo htmlspecialchars($row['unit']); ?>
                                                                    </div>
                                                                    <div class="mb-2 flex-fill">
                                                                        <span class="fw-bold text-muted"><i class="fas fa-coins me-1"></i>Material Price:</span> ₱ <?php echo number_format($row['material_price'], 2); ?>
                                                                    </div>
                                                                    <div class="mb-2 flex-fill">
                                                                        <span class="fw-bold text-muted"><i class="fas fa-tags me-1"></i>Category:</span> <?php echo htmlspecialchars($row['category']); ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>" data-bs-dismiss="modal">Edit</button>
                                                    <a href="#" class="btn btn-danger btn-sm text-white delete-material-btn" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['material_name']); ?>">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                    <h5 class="modal-title" id="editModalLabel<?php echo $row['id']; ?>">Edit Material</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                                <form id="editForm<?php echo $row['id']; ?>" action="update_materials.php" method="POST">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="update" value="1">
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label>Material Name</label>
                                                                <input type="text" class="form-control" name="material_name" 
                                                                       value="<?php echo htmlspecialchars($row['material_name']); ?>" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Category</label>
                                                                <select class="form-control" name="category" required>
                                                                        <?php $categories->data_seek(0); while($cat = $categories->fetch_assoc()): ?>
                                                                        <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                                                            <?php echo $row['category'] == $cat['category'] ? 'selected' : ''; ?>>
                                                                            <?php echo htmlspecialchars($cat['category']); ?>
                                                                        </option>
                                                                    <?php endwhile; ?>
                                                                </select>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Quantity</label>
                                                                <input type="number" class="form-control" name="quantity" 
                                                                       value="<?php echo $row['quantity']; ?>" required>
                                                            </div>
                                                                <div class="form-group">
                                                                    <label>Unit</label>
                                                                    <input type="text" class="form-control" name="unit" 
                                                                           value="<?php echo htmlspecialchars($row['unit']); ?>" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Status</label>
                                                                <select class="form-control" name="status" required>
                                                                    <option value="Available" <?php echo $row['status'] == 'Available' ? 'selected' : ''; ?>>Available</option>
                                                                    <option value="In Use" <?php echo $row['status'] == 'In Use' ? 'selected' : ''; ?>>In Use</option>
                                                                    <option value="Low Stock" <?php echo $row['status'] == 'Low Stock' ? 'selected' : ''; ?>>Low Stock</option>
                                                                    <option value="Damaged" <?php echo $row['status'] == 'Damaged' ? 'selected' : ''; ?>>Damaged</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label>Supplier</label>
                                                                <select class="form-control" name="supplier_name" required>
                                                                        <?php $suppliers->data_seek(0); while($sup = $suppliers->fetch_assoc()): ?>
                                                                        <option value="<?php echo htmlspecialchars($sup['supplier_name']); ?>"
                                                                            <?php echo $row['supplier_name'] == $sup['supplier_name'] ? 'selected' : ''; ?>>
                                                                            <?php echo htmlspecialchars($sup['supplier_name']); ?>
                                                                        </option>
                                                                    <?php endwhile; ?>
                                                                </select>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Material Price</label>
                                                                <input type="number" step="0.01" class="form-control" name="material_price" 
                                                                       value="<?php echo $row['material_price']; ?>" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Other Cost</label>
                                                                <input type="number" step="0.01" class="form-control labor-other-edit" name="labor_other" 
                                                                       value="<?php echo $row['labor_other']; ?>">
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Amount</label>
                                                                <input type="number" step="0.01" class="form-control amount-sum-edit" name="amount_sum" value="<?php echo ($row['material_price'] || $row['labor_other']) ? number_format($row['material_price'] + $row['labor_other'], 2, '.', '') : ''; ?>" readonly>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" name="update" class="btn btn-primary">Update Material</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        </div>
                        <nav aria-label="Page navigation" class="mt-3 mb-3">
                            <ul class="pagination justify-content-center custom-pagination-green mb-0">
                                <li class="page-item<?php if($page <= 1) echo ' disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($supplier_filter) ? '&supplier=' . urlencode($supplier_filter) : ''; ?>">Previous</a>
                                        </li>
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item<?php if($i == $page) echo ' active'; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($supplier_filter) ? '&supplier=' . urlencode($supplier_filter) : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                <li class="page-item<?php if($page >= $total_pages) echo ' disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($supplier_filter) ? '&supplier=' . urlencode($supplier_filter) : ''; ?>">Next</a>
                                        </li>
                                </ul>
                            </nav>
                    </div>
                    </div>
                </div>
            </div>
                
            </div>
        </div>
    </div>
    <!-- /#page-content-wrapper -->
    </div>

      <!-- Add Material Modal -->
      <div class="modal fade" id="addMaterialModal" tabindex="-1" aria-labelledby="addMaterialModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addMaterialModalLabel">Add New Material</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="add_materials.php" method="POST">
            <div class="modal-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label>Material Name</label>
                    <input type="text" class="form-control" name="material_name" required>
                  </div>
                  <div class="form-group mb-3">
                    <label>Supplier</label>
                    <select class="form-control" name="supplier_name" required>
                      <option value="" disabled selected>Select Supplier</option>
                      <?php if ($all_suppliers) { while($sup = $all_suppliers->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($sup['supplier_name']); ?>">
                          <?php echo htmlspecialchars($sup['supplier_name']); ?>
                        </option>
                      <?php endwhile; } ?>
                    </select>
                  </div>
                  <div class="form-group mb-3">
                    <label>Location</label>
                    <select class="form-control" name="location">
                      <option value="" disabled selected>Select Location</option>
                      <?php if ($all_warehouses) { while($wh = $all_warehouses->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($wh['warehouse']); ?>">
                          <?php echo htmlspecialchars($wh['warehouse']); ?>
                        </option>
                      <?php endwhile; } ?>
                    </select>
                  </div>
                  <div class="form-group mb-3">
                    <label>Category</label>
                    <select class="form-control" name="category" required>
                      <option value="" disabled selected>Select Category</option>
                      <?php if ($all_categories) { while($cat = $all_categories->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($cat['material_category']); ?>">
                          <?php echo htmlspecialchars($cat['material_category']); ?>
                        </option>
                      <?php endwhile; } ?>
                    </select>
                  </div>
                  <div class="row">
                    <div class="col-6">
                      <div class="form-group mb-3">
                        <label>Quantity</label>
                        <input type="number" class="form-control" name="quantity" required>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="form-group mb-3">
                        <label>Unit</label>
                        <select class="form-control" name="unit" required>
                          <option value="" disabled selected>Select Unit</option>
                          
                          <option value="Set">Set</option>
                          <option value="Sets">Sets</option>
                          <option value="Mts">Mts</option>
                          <option value="Lgts">Lgts</option>
                          <option value="Pcs">Pcs</option>
                          <option value="Lot">Lot</option>
                          <option value="kg">kg</option>
                          <option value="g">g</option>
                          <option value="t">t</option>
                          <option value="m³">m&sup3;</option>
                          <option value="ft³">ft&sup3;</option>
                          <option value="L">L</option>
                          <option value="mL">mL</option>
                          <option value="m">m</option>
                          <option value="mm">mm</option>
                          <option value="cm">cm</option>
                          <option value="ft">ft</option>
                          <option value="in">in</option>
                          <option value="pcs">pcs</option>
                          <option value="bndl">bndl</option>
                          <option value="rl">rl</option>
                          <option value="set">set</option>
                          <option value="sack/bag">sack/bag</option>
                          <option value="m²">m&sup2;</option>
                          <option value="ft²">ft&sup2;</option>
                        </select>
                      </div>
                    </div>
                  </div>
                  <input type="hidden" name="status" value="Available">
                </div>
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label>Purchase Date</label>
                    <input type="date" class="form-control" name="purchase_date" required>
                  </div>
                  <div class="form-group mb-3">
                    <label>Material Price</label>
                    <input type="number" step="0.01" class="form-control" name="material_price" required>
                  </div>
                  <div class="form-group mb-3">
                    <label>Other Cost</label>
                    <input type="number" step="0.01" class="form-control" name="labor_other" id="labor_other">
                  </div>
                  
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" name="add" class="btn btn-success">Add Material</button>
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
    <!-- Delete Material Modal -->
    <div class="modal fade" id="deleteMaterialModal" tabindex="-1" aria-labelledby="deleteMaterialModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteMaterialModalLabel">Confirm Delete</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete <strong id="materialName"></strong>?</p>
            <p class="text-danger">This action cannot be undone.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <a href="#" id="confirmDeleteMaterial" class="btn btn-danger">Delete</a>
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
    <!-- Export PDF Confirmation Modal (only one per page) -->
    <div class="modal fade" id="exportPdfModal" tabindex="-1" aria-labelledby="exportPdfModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exportPdfModalLabel">Export as PDF</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to export the materials list as PDF?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <a href="export_materials_pdf.php" id="confirmExportPdf" class="btn btn-danger">Export</a>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="po_materials.js"></script>
    <script>
    // Live update Amount field in Edit Material modal as sum of Material Price and Other Cost
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('[id^="editModal"]').forEach(function(modal) {
        modal.addEventListener('shown.bs.modal', function() {
          var priceInput = modal.querySelector('input[name="material_price"]');
          var otherInput = modal.querySelector('input[name="labor_other"]');
          var amountInput = modal.querySelector('input.amount-sum-edit');
          function updateAmount() {
            var price = priceInput.value.trim() === '' ? '' : parseFloat(priceInput.value) || 0;
            var other = otherInput.value.trim() === '' ? '' : parseFloat(otherInput.value) || 0;
            if (price === '' && other === '') {
              amountInput.value = '';
            } else {
              amountInput.value = ((price || 0) + (other || 0)).toFixed(2);
            }
          }
          if (priceInput && otherInput && amountInput) {
            priceInput.addEventListener('input', updateAmount);
            otherInput.addEventListener('input', updateAmount);
            updateAmount();
          }
        });
      });
    });
    </script>
    <script>
    // Live update Amount field as sum of Material Price and Other Cost
    document.addEventListener('DOMContentLoaded', function() {
      var priceInput = document.querySelector('input[name="material_price"]');
      var otherInput = document.querySelector('input[name="labor_other"]');
      var amountInput = document.getElementById('amount_sum');
      function updateAmount() {
        var price = parseFloat(priceInput.value) || 0;
        var other = parseFloat(otherInput.value) || 0;
        amountInput.value = (price + other).toFixed(2);
      }
      if (priceInput && otherInput && amountInput) {
        priceInput.addEventListener('input', updateAmount);
        otherInput.addEventListener('input', updateAmount);
        // Initialize on load
        updateAmount();
      }
    });
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
        showFeedbackModal(true, 'Material added successfully!', '', 'added');
      } else if (params.get('updated') === '1') {
        showFeedbackModal(true, 'Material updated successfully!', '', 'updated');
      } else if (params.get('deleted') === '1') {
        showFeedbackModal(true, 'Material deleted successfully!', '', 'deleted');
      }
    })();
    </script>
    <script>
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
</body>

</html>