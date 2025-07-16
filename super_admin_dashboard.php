<?php
include("session.php");

// Check if user is super admin
if (!isset($_SESSION['user_level']) || $_SESSION['user_level'] != 1) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Super Admin Dashboard</title>

    <!-- Bootstrap core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="css/sidebar.css" rel="stylesheet">

    <!-- Feather JS for Icons -->
    <script src="js/feather.min.js"></script>

    <style>
        .card a { color: #000; font-weight: 500; }
        .card a:hover { color: #28a745; text-decoration: dotted; }
        
        /* Fixed navbar and sidebar styling */
        .navbar {
            position: fixed;
            top: 0;
            right: 0;
            left: 250px; /* Match sidebar width */
            z-index: 1030;
            background-color: #343a40 !important;
            transition: left 0.3s ease; /* Smooth transition for sidebar toggle */
        }

        /* Adjust navbar when sidebar is toggled */
        #wrapper.toggled .navbar {
            left: 0;
        }

        #sidebar-wrapper {
            position: fixed;
            height: 100vh;
            width: 250px;
            overflow-y: auto;
            z-index: 1020;
            transition: margin-left 0.3s ease;
        }

        #page-content-wrapper {
            margin-left: 250px;
            margin-top: 56px; /* Height of navbar */
            transition: margin-left 0.3s ease;
        }

        /* Adjust wrapper when sidebar is toggled */
        #wrapper.toggled #page-content-wrapper {
            margin-left: 0;
        }

        /* Add scrollbar styling for sidebar */
        #sidebar-wrapper::-webkit-scrollbar {
            width: 5px;
        }

        #sidebar-wrapper::-webkit-scrollbar-track {
            background: #343a40;
        }

        #sidebar-wrapper::-webkit-scrollbar-thumb {
            background: #666;
            border-radius: 5px;
        }
        
        /* Ensure content doesn't hide under navbar */
        .container-fluid {
            padding-top: 20px;
            padding-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="border-right bg-dark" id="sidebar-wrapper">
            <div class="user">
                <img class="img img-fluid rounded-circle" src="<?php echo $userprofile ?>" width="120">
                <h5><?php echo $username ?></h5>
                <p><?php echo $useremail ?></p>
            </div>
            <div class="sidebar-heading">Management</div>
            <div class="list-group list-group-flush">
                <a href="super_admin_dashboard.php" class="list-group-item list-group-item-action sidebar-active">
                    <span data-feather="home"></span> Dashboard
                </a>
            </div>
            <div class="sidebar-heading">Settings </div>
            <div class="list-group list-group-flush">
                <a href="profile.php" class="list-group-item list-group-item-action">
                    <span data-feather="user"></span> Profile
                </a>
                <a href="logout.php" class="list-group-item list-group-item-action">
                    <span data-feather="power"></span> Logout
                </a>
            </div>
        </div>

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <!-- Navbar -->
            <?php include('includes/navbar.php'); ?>

            <div class="container-fluid">
                <h3 class="mt-4">Super Admin Dashboard</h3>
                <!-- Add your super admin specific content here -->
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="js/jquery.slim.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/feather.min.js"></script>
    <script>
        // Initialize Feather icons
        feather.replace();
        
        // Initialize all dropdowns
        $(document).ready(function() {
            // Manual dropdown handling for sidebar
            $('.dropdown-toggle').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).next('.dropdown-menu').toggleClass('show');
                $('.dropdown-toggle').not(this).next('.dropdown-menu').removeClass('show');
            });
            
            // Close dropdowns when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.dropdown').length) {
                    $('.dropdown-menu').removeClass('show');
                }
            });
            
            // Toggle sidebar
            $("#menu-toggle").click(function(e) {
                e.preventDefault();
                $("#wrapper").toggleClass("toggled");
            });
        });
    </script>
</body>
</html>