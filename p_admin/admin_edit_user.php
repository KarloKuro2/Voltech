<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection error']);
    exit();
}
// Handle GET request to fetch user data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($con, $_GET['id']);
    $result = $con->query("SELECT id, firstname, lastname, email, user_level, is_verified FROM users WHERE id = '$id' LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = mysqli_real_escape_string($con, $_POST['user_id']);
    $firstname = mysqli_real_escape_string($con, $_POST['firstname']);
    $lastname = mysqli_real_escape_string($con, $_POST['lastname']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $user_level = mysqli_real_escape_string($con, $_POST['user_level']);
    $is_verified = isset($_POST['is_verified']) ? intval($_POST['is_verified']) : 0;
    $password = isset($_POST['password']) && $_POST['password'] !== '' ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

    // Check previous is_verified value
    $prev_result = $con->query("SELECT is_verified FROM users WHERE id='$id'");
    $prev_is_verified = 0;
    if ($prev_result && $prev_result->num_rows > 0) {
        $prev_row = $prev_result->fetch_assoc();
        $prev_is_verified = (int)$prev_row['is_verified'];
    }

    if ($password) {
        $query = "UPDATE users SET firstname='$firstname', lastname='$lastname', email='$email', password='$password', user_level='$user_level', is_verified='$is_verified' WHERE id='$id'";
    } else {
        $query = "UPDATE users SET firstname='$firstname', lastname='$lastname', email='$email', user_level='$user_level', is_verified='$is_verified' WHERE id='$id'";
    }
    if (mysqli_query($con, $query)) {
        // If account was just activated, insert notification
        if ($prev_is_verified == 0 && $is_verified == 1) {
            $notif_type = 'Activation';
            $message = 'Work hard to our company';
            $notif_table = null;
            if ($user_level == 3) {
                $notif_table = 'notifications_projectmanager';
            } elseif ($user_level == 4) {
                $notif_table = 'notifications_procurement';
            }
            if ($notif_table) {
                $stmt = $con->prepare("INSERT INTO $notif_table (user_id, notif_type, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                $stmt->bind_param('iss', $id, $notif_type, $message);
                $stmt->execute();
                $stmt->close();
            }
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating user: ' . mysqli_error($con)]);
    }
    exit();
}
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>

<!-- HTML Modal Form -->
<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" id="editUserId" name="user_id">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" class="form-control" id="editFirstname" name="firstname" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" class="form-control" id="editLastname" name="lastname" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" class="form-control" id="editEmail" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>User Level</label>
                        <select class="form-control" id="editUserLevel" name="user_level" required>
                            <option value="1">Regular User</option>
                            <option value="2">Administrator</option>
                            <option value="3">Project Manager</option>
                            <option value="4">Procurement Officer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="editIsVerified" name="is_verified">
                            <label class="custom-control-label" for="editIsVerified">Verified User</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" id="editPassword" name="password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle form submission
    $('#editUserForm').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'admin_edit_user.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#editUserModal').modal('hide');
                    // Refresh the users list or update the row
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error processing request');
            }
        });
    });
});

function editUser(userId) {
    $.get('admin_edit_user.php', { id: userId })
        .done(function(response) {
            if (response.success) {
                $('#editUserId').val(response.user.id);
                $('#editFirstname').val(response.user.firstname);
                $('#editLastname').val(response.user.lastname);
                $('#editEmail').val(response.user.email);
                $('#editUserLevel').val(response.user.user_level);
                $('#editIsVerified').prop('checked', response.user.is_verified == 1);
                $('#editPassword').val(''); // Clear password field
                $('#editUserModal').modal('show');
            } else {
                alert('Error: ' + response.message);
            }
        })
        .fail(function() {
            alert('Error fetching user data');
        });
}
</script>