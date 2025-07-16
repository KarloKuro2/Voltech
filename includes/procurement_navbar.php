<!-- Navbar --> 
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <button class="btn btn-link" id="menu-toggle">
        <span data-feather="menu" class="text-white"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav ml-auto">
            <!-- Notification dropdown -->
            <li class="nav-item dropdown mr-3">
                <a class="nav-link text-white" href="#" id="notificationDropdown" role="button" data-toggle="dropdown">
                    <div class="position-relative">
                        <span data-feather="bell" id="notifBell"></span>
                        <span class="notification-badge badge badge-danger d-none"></span>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-right notification-menu" aria-labelledby="notificationDropdown">
                    <h6 class="dropdown-header">Notifications</h6>
                    <div id="notificationList">
                        <!-- Notifications will be loaded here -->
                    </div>
                </div>
            </li>
            <!-- User dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <img class="img img-fluid rounded-circle" src="<?php echo $userprofile ?>" width="25">
                </a>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                    <a class="dropdown-item" href="procurement_profile.php"><span data-feather="user" class="mr-2"></span>Your Profile</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutConfirmModal">
                        <span data-feather="power" class="mr-2"></span>Logout
                    </a>
                </div>
            </li>
        </ul>
    </div>
</nav>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutConfirmModal" tabindex="-1" role="dialog" aria-labelledby="logoutConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutConfirmModalLabel">Confirm Logout</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure you want to logout?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </div>
    </div>
</div>

<style>
.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    font-size: 0.6rem;
    padding: 0.25rem 0.4rem;
}

/* Ensure Feather Icons have proper spacing */
.dropdown-item svg {
    margin-right: 0.5rem;
    width: 16px;
    height: 16px;
    vertical-align: text-top;
}
</style>

<script>
// Initialize when document is ready
$(document).ready(function() {
    // Initialize Feather Icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Toggle notification bell color when dropdown is shown/hidden
    $('#notificationDropdown').on('click', function() {
        $('#notifBell').toggleClass('text-white text-success');
    });

    $('.notification-menu').on('hide.bs.dropdown', function () {
        $('#notifBell').removeClass('text-success').addClass('text-white');
    });n
    
    // Re-initialize icons after modals are shown
    $('.modal').on('shown.bs.modal', function () {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
});
</script>
