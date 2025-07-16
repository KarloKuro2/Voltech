<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 2) {
    header("Location: ../login.php");
    exit();
}
// Add the database connection here so it's available for the whole script
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}
// Change Password Backend Handler
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['change_password'])
) {
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
// Handle approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_type'], $_POST['approve_id'])) {
    $type = $_POST['approve_type'];
    $id = intval($_POST['approve_id']);
    $table = '';
    $item_name = '';
    $user_id = null;
    $notif_type_pm = '';
    $notif_type_proc = 'Approval';
    $notif_message_proc = '';
    $notif_message_pm = '';
    switch ($type) {
        case 'supplier':
            $table = 'suppliers';
            $row = $con->query("SELECT supplier_name, user_id FROM suppliers WHERE id = $id")->fetch_assoc();
            $item_name = $row['supplier_name'];
            $user_id = $row['user_id'];
            $notif_type_pm = 'New added supplier';
            $notif_message_proc = "Your request for supplier ($item_name) has been approved.";
            $notif_message_pm = "A new supplier ($item_name) has been approved and added.";
            break;
        case 'warehouse':
            $table = 'warehouses';
            $row = $con->query("SELECT warehouse, user_id FROM warehouses WHERE id = $id")->fetch_assoc();
            $item_name = $row['warehouse'];
            $user_id = $row['user_id'];
            $notif_type_pm = 'New added warehouse';
            $notif_message_proc = "Your request for warehouse ($item_name) has been approved.";
            $notif_message_pm = "A new warehouse ($item_name) has been approved and added.";
            break;
        case 'material':
            $table = 'materials';
            $row = $con->query("SELECT material_name, user_id FROM materials WHERE id = $id")->fetch_assoc();
            $item_name = $row['material_name'];
            $user_id = $row['user_id'];
            $notif_type_pm = 'New added material';
            $notif_message_proc = "Your request for material ($item_name) has been approved.";
            $notif_message_pm = "A new material ($item_name) has been approved and added.";
            break;
        case 'equipment':
            $table = 'equipment';
            $row = $con->query("SELECT equipment_name, user_id FROM equipment WHERE id = $id")->fetch_assoc();
            $item_name = $row['equipment_name'];
            $user_id = $row['user_id'];
            $notif_type_pm = 'New added equipment';
            $notif_message_proc = "Your request for equipment ($item_name) has been approved.";
            $notif_message_pm = "A new equipment ($item_name) has been approved and added.";
            break;
    }
    if ($table && $id > 0) {
        $con->query("UPDATE $table SET approval = 'Approved', approval_date = NOW() WHERE id = $id");
        // Insert into order_expenses for material
        if ($type === 'material') {
            $mat = $con->query("SELECT total_amount FROM materials WHERE id = $id")->fetch_assoc();
            $expense = isset($mat['total_amount']) ? floatval($mat['total_amount']) : 0;
            if ($expense > 0) {
                $desc = "Purchased A $item_name";
                $stmt = $con->prepare("INSERT INTO order_expenses (user_id, expense, expensedate, expensecategory, description) VALUES (?, ?, NOW(), 'Material', ?)");
                $stmt->bind_param("ids", $user_id, $expense, $desc);
                $stmt->execute();
                $stmt->close();
            }
        }
        // Insert into order_expenses for equipment
        if ($type === 'equipment') {
            $equip = $con->query("SELECT category, equipment_price, rental_fee FROM equipment WHERE id = $id")->fetch_assoc();
            $category = strtolower($equip['category']);
            $expense = 0;
            $desc = '';
            if ($category === 'company') {
                $expense = isset($equip['equipment_price']) ? floatval($equip['equipment_price']) : 0;
                $desc = "Purchased A $item_name";
            } elseif ($category === 'rental') {
                $expense = isset($equip['rental_fee']) ? floatval($equip['rental_fee']) : 0;
                $desc = "Rented A $item_name";
            }
            if ($expense > 0) {
                $stmt = $con->prepare("INSERT INTO order_expenses (user_id, expense, expensedate, expensecategory, description) VALUES (?, ?, NOW(), 'Equipment', ?)");
                $stmt->bind_param("ids", $user_id, $expense, $desc);
                $stmt->execute();
                $stmt->close();
            }
        }
        // Insert notification for procurement officer (requestor)
        if ($user_id) {
            $stmt = $con->prepare("INSERT INTO notifications_procurement (user_id, notif_type, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
            $stmt->bind_param("iss", $user_id, $notif_type_proc, $notif_message_proc);
            $stmt->execute();
            $stmt->close();
        }
        // Insert notification for all project managers
        $pm_query = $con->query("SELECT id FROM users WHERE user_level = 3");
        while ($pm = $pm_query->fetch_assoc()) {
            $pm_id = $pm['id'];
            $stmt2 = $con->prepare("INSERT INTO notifications_projectmanager (user_id, notif_type, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
            $stmt2->bind_param("iss", $pm_id, $notif_type_pm, $notif_message_pm);
            $stmt2->execute();
            $stmt2->close();
        }
    }
    header('Location: admin_approval_requests.php?approved=1');
    exit();
}
        // Handle rejection actions
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['reject_type'], $_POST['reject_id'])
        ) {
            $type = $_POST['reject_type'];
            $id = intval($_POST['reject_id']);
            $table = '';
            $item_name = '';
            $user_id = null;
            switch ($type) {
                case 'supplier':
                    $table = 'suppliers';
                    $row = $con->query("SELECT supplier_name, user_id FROM suppliers WHERE id = $id")->fetch_assoc();
                    $item_name = $row['supplier_name'];
                    $user_id = $row['user_id'];
                    break;
                case 'warehouse':
                    $table = 'warehouses';
                    $row = $con->query("SELECT warehouse, user_id FROM warehouses WHERE id = $id")->fetch_assoc();
                    $item_name = $row['warehouse'];
                    $user_id = $row['user_id'];
                    break;
                case 'material':
                    $table = 'materials';
                    $row = $con->query("SELECT material_name, user_id FROM materials WHERE id = $id")->fetch_assoc();
                    $item_name = $row['material_name'];
                    $user_id = $row['user_id'];
                    break;
                case 'equipment':
                    $table = 'equipment';
                    $row = $con->query("SELECT equipment_name, user_id FROM equipment WHERE id = $id")->fetch_assoc();
                    $item_name = $row['equipment_name'];
                    $user_id = $row['user_id'];
                    break;
            }
            if ($table && $id > 0) {
                $con->query("DELETE FROM $table WHERE id = $id");
                if ($user_id) {
                    $msg = "Sorry, your request for $item_name has been rejected.";
                    $stmt = $con->prepare("INSERT INTO notifications_procurement (user_id, notif_type, message, is_read, created_at) VALUES (?, 'Rejection', ?, 0, NOW())");
                    $stmt->bind_param("is", $user_id, $msg);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            header('Location: admin_approval_requests.php?rejected=1');
            exit();
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
// Always define fetch_pending and the arrays before HTML output
function fetch_pending($con, $table, $fields) {
    $result = $con->query("SELECT id, $fields FROM $table WHERE approval = 'Pending' ORDER BY id DESC");
    $rows = [];
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}
$materials = fetch_pending($con, 'materials', 'material_name, category, quantity, supplier_name');
$suppliers = fetch_pending($con, 'suppliers', 'supplier_name, contact_person, email');
$equipment = fetch_pending($con, 'equipment', 'equipment_name, category, status');
$warehouses = fetch_pending($con, 'warehouses', 'warehouse, category, slots');
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
                <h2 class="fs-2 m-0">User Management</h2>
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
            <div class="card mb-4 shadow rounded-3">
                <div class="card-body p-4">
                    <ul class="nav nav-tabs mb-4" id="approvalTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button" role="tab" aria-controls="materials" aria-selected="true">Materials</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="suppliers-tab" data-bs-toggle="tab" data-bs-target="#suppliers" type="button" role="tab" aria-controls="suppliers" aria-selected="false">Suppliers</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="equipment-tab" data-bs-toggle="tab" data-bs-target="#equipment" type="button" role="tab" aria-controls="equipment" aria-selected="false">Equipment</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="warehouses-tab" data-bs-toggle="tab" data-bs-target="#warehouses" type="button" role="tab" aria-controls="warehouses" aria-selected="false">Warehouses</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="approvalTabsContent">
                        <div class="tab-pane fade show active" id="materials" role="tabpanel" aria-labelledby="materials-tab">
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered table-striped">
                                    <thead><tr><th>No</th><th>Name</th><th>Category</th><th>Quantity</th><th>Supplier</th><th>Action</th></tr></thead>
                                    <tbody>
                                    <?php $no=1; foreach ($materials as $m): ?>
                                        <tr>
                                            <td><?=$no++?></td>
                                            <td><?=htmlspecialchars($m['material_name'])?></td>
                                            <td><?=htmlspecialchars($m['category'])?></td>
                                            <td><?=htmlspecialchars($m['quantity'])?></td>
                                            <td><?=htmlspecialchars($m['supplier_name'])?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="approve_type" value="material">
                                                    <input type="hidden" name="approve_id" value="<?=$m['id']?>">
                                                    <button class="btn btn-success btn-sm">Approve</button>
                                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal_<?=$m['id']?>">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <div class="modal fade" id="rejectModal_<?=$m['id']?>" tabindex="-1" aria-labelledby="rejectModalLabel_<?=$m['id']?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="rejectModalLabel_<?=$m['id']?>">Reject Request</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Are you sure you want to reject this request?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="reject_type" value="material">
                                                            <input type="hidden" name="reject_id" value="<?=$m['id']?>">
                                                            <button type="submit" class="btn btn-danger">Reject</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; if (empty($materials)): ?><tr><td colspan="6">No pending materials.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="suppliers" role="tabpanel" aria-labelledby="suppliers-tab">
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered table-striped">
                                    <thead><tr><th>No</th><th>Name</th><th>Contact</th><th>Email</th><th>Action</th></tr></thead>
                                    <tbody>
                                    <?php $no=1; foreach ($suppliers as $s): ?>
                                        <tr>
                                            <td><?=$no++?></td>
                                            <td><?=htmlspecialchars($s['supplier_name'])?></td>
                                            <td><?=htmlspecialchars($s['contact_person'])?></td>
                                            <td><?=htmlspecialchars($s['email'])?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="approve_type" value="supplier">
                                                    <input type="hidden" name="approve_id" value="<?=$s['id']?>">
                                                    <button class="btn btn-success btn-sm">Approve</button>
                                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal_<?=$s['id']?>">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <div class="modal fade" id="rejectModal_<?=$s['id']?>" tabindex="-1" aria-labelledby="rejectModalLabel_<?=$s['id']?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="rejectModalLabel_<?=$s['id']?>">Reject Request</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Are you sure you want to reject this request?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="reject_type" value="supplier">
                                                            <input type="hidden" name="reject_id" value="<?=$s['id']?>">
                                                            <button type="submit" class="btn btn-danger">Reject</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; if (empty($suppliers)): ?><tr><td colspan="5">No pending suppliers.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="equipment" role="tabpanel" aria-labelledby="equipment-tab">
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered table-striped">
                                    <thead><tr><th>No</th><th>Name</th><th>Category</th><th>Status</th><th>Action</th></tr></thead>
                                    <tbody>
                                    <?php $no=1; foreach ($equipment as $e): ?>
                                        <tr>
                                            <td><?=$no++?></td>
                                            <td><?=htmlspecialchars($e['equipment_name'])?></td>
                                            <td><?=htmlspecialchars($e['category'])?></td>
                                            <td><?=htmlspecialchars($e['status'])?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="approve_type" value="equipment">
                                                    <input type="hidden" name="approve_id" value="<?=$e['id']?>">
                                                    <button class="btn btn-success btn-sm">Approve</button>
                                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal_<?=$e['id']?>">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <div class="modal fade" id="rejectModal_<?=$e['id']?>" tabindex="-1" aria-labelledby="rejectModalLabel_<?=$e['id']?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="rejectModalLabel_<?=$e['id']?>">Reject Request</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Are you sure you want to reject this request?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="reject_type" value="equipment">
                                                            <input type="hidden" name="reject_id" value="<?=$e['id']?>">
                                                            <button type="submit" class="btn btn-danger">Reject</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; if (empty($equipment)): ?><tr><td colspan="5">No pending equipment.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="warehouses" role="tabpanel" aria-labelledby="warehouses-tab">
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered table-striped">
                                    <thead><tr><th>No</th><th>Warehouse</th><th>Category</th><th>Slots</th><th>Action</th></tr></thead>
                                    <tbody>
                                    <?php $no=1; foreach ($warehouses as $w): ?>
                                        <tr>
                                            <td><?=$no++?></td>
                                            <td><?=htmlspecialchars($w['warehouse'])?></td>
                                            <td><?=htmlspecialchars($w['category'])?></td>
                                            <td><?=htmlspecialchars($w['slots'])?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="approve_type" value="warehouse">
                                                    <input type="hidden" name="approve_id" value="<?=$w['id']?>">
                                                    <button class="btn btn-success btn-sm">Approve</button>
                                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal_<?=$w['id']?>">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <div class="modal fade" id="rejectModal_<?=$w['id']?>" tabindex="-1" aria-labelledby="rejectModalLabel_<?=$w['id']?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="rejectModalLabel_<?=$w['id']?>">Reject Request</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Are you sure you want to reject this request?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="reject_type" value="warehouse">
                                                            <input type="hidden" name="reject_id" value="<?=$w['id']?>">
                                                            <button type="submit" class="btn btn-danger">Reject</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; if (empty($warehouses)): ?><tr><td colspan="5">No pending warehouses.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
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
                    location.reload();
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
                        location.reload();
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
</script>
<script>
// 2A. Approve confirmation
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(function(form) {
      var approveBtn = form.querySelector('button.btn-success');
      if (approveBtn && approveBtn.textContent.trim() === 'Approve') {
        approveBtn.classList.add('approve-btn');
        approveBtn.addEventListener('click', function(e) {
          e.preventDefault();
          if (confirm('Are you sure you want to approve this request?')) {
            form.submit();
          }
        });
      }
    });
  });
// 2B. Feedback Modal logic
function showFeedbackModal(success, message) {
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
  window.history.replaceState({}, document.title, window.location.pathname);
}
(function() {
  var params = new URLSearchParams(window.location.search);
  if (params.get('approved') === '1') {
    showFeedbackModal(true, 'Request approved successfully!');
  } else if (params.get('rejected') === '1') {
    showFeedbackModal(true, 'Request rejected successfully!');
  }
})();
</script>
</body>
</html> 