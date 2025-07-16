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
      padding: 0.5rem 1rem; /* Reduce padding */
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
      top: 0; /* Ensure it starts from the very top */
      padding-top: 0; /* Remove top padding */
  }

  #page-content-wrapper {
      margin-left: 250px;
      margin-top: 56px; /* Match navbar height exactly */
      transition: margin-left 0.3s ease;
      padding-top: 0; /* Remove top padding */
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
      padding-top: 1rem; /* Reduce top padding */
      padding-bottom: 20px;
  }
  
  /* Adjust user section in sidebar */
  .user {
      padding-top: 1rem;
  }

  /* Mobile-friendly sidebar styles */
  @media (max-width: 768px) {
      #sidebar-wrapper {
          margin-left: -250px;
          position: fixed;
          height: 100%;
          z-index: 1050;
          width: 250px;
      }

      #wrapper.toggled #sidebar-wrapper {
          margin-left: 0;
      }

      #page-content-wrapper {
          margin-left: 0;
          width: 100%;
      }

      .navbar {
          left: 0;
          width: 100%;
      }

      /* Overlay when sidebar is open */
      .sidebar-overlay {
          display: none;
          position: fixed;
          width: 100%;
          height: 100%;
          background: rgba(0, 0, 0, 0.4);
          z-index: 1040;
          opacity: 0;
          transition: opacity 0.3s ease;
      }

      #wrapper.toggled .sidebar-overlay {
          display: block;
          opacity: 1;
      }

      /* Menu toggle button styling */
      #menu-toggle {
          position: fixed;
          left: 0;
          top: 0;
          z-index: 1060;
          background: transparent;
          border: none;
          color: #fff;
          width: 56px;
          height: 56px;
          display: flex;
          align-items: center;
          justify-content: center;
          transition: all 0.3s ease;
      }

      /* Move hamburger when sidebar is open */
      #wrapper.toggled #menu-toggle {
          left: 260px; /* 250px (sidebar width) + 10px spacing */
          right: auto;
      }

      /* Navbar adjustments */
      .navbar {
          left: 0;
          width: 100%;
          padding-left: 56px;
          transition: all 0.3s ease;
          min-height: 56px;
          height: 56px;
      }

      #wrapper.toggled .navbar {
          padding-left: 15px;
      }

      /* Maintain navbar height and elements when toggled */
      #wrapper.toggled .navbar {
          height: 56px;
          min-height: 56px;
      }

      #wrapper.toggled .navbar-nav {
          display: flex !important;
          align-items: center;
      }

      /* Ensure navbar elements are properly aligned */
      .navbar .navbar-nav {
          height: 100%;
          align-items: center;
      }

      /* Ensure sidebar content is scrollable on mobile */
      #sidebar-wrapper .list-group {
          overflow-y: auto;
          max-height: calc(100vh - 200px); /* Adjust based on header height */
      }

      /* Improve touch targets on mobile */
      @media (max-width: 768px) {
          .list-group-item {
              padding: 1rem 1.25rem;
          }

          .user {
              padding: 1rem;
          }

          .navbar-toggler {
              display: block !important;
          }
      }
  }

  /* Stat Card Styles */
  .border-left-primary {
      border-left: 4px solid #4e73df !important;
  }
  .border-left-success {
      border-left: 4px solid #1cc88a !important;
  }
  .border-left-info {
      border-left: 4px solid #36b9cc !important;
  }
  .border-left-warning {
      border-left: 4px solid #f6c23e !important;
  }
  .text-primary {
      color: #4e73df !important;
  }
  .text-success {
      color: #1cc88a !important;
  }
  .text-info {
      color: #36b9cc !important;
  }
  .text-warning {
      color: #f6c23e !important;
  }
  .card {
      position: relative;
      display: flex;
      flex-direction: column;
      min-width: 0;
      word-wrap: break-word;
      background-color: #fff;
      background-clip: border-box;
      border: 1px solid #e3e6f0;
      border-radius: 0.35rem;
  }
  .card.bg-primary {
      background-color: #4e73df !important;
      border: none;
  }
  .card.bg-success {
      background-color: #1cc88a !important;
      border: none;
  }
  .card.bg-info {
      background-color: #36b9cc !important;
      border: none;
  }
  .card.bg-warning {
      background-color: #f6c23e !important;
      border: none;
  }
  .card.bg-primary .text-gray-800,
  .card.bg-success .text-gray-800,
  .card.bg-info .text-gray-800,
  .card.bg-warning .text-gray-800,
  .card.bg-primary .text-uppercase,
  .card.bg-success .text-uppercase,
  .card.bg-info .text-uppercase,
  .card.bg-warning .text-uppercase {
      color: #fff !important;
  }
  .card.bg-primary .stat-icon,
  .card.bg-success .stat-icon,
  .card.bg-info .stat-icon,
  .card.bg-warning .stat-icon {
      color: rgba(255, 255, 255, 0.8);
  }

  /* Chart card styles */
  .card .card-body {
      padding: 1rem;
  }

  .card canvas {
      width: 100% !important;
      margin: auto;
  }

  /* Responsive chart adjustments */
  @media (max-width: 768px) {
      .card canvas {
          height: 250px !important;
      }
  }
  
  /* Add these styles to your existing style section */
  .card-link {
      text-decoration: none;
      display: block;
      height: 100%;
  }
  
  .hover-effect {
      transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  
  .hover-effect:hover {
      transform: translateY(-5px);
      box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
  }
  
  .card-link:hover .card {
      opacity: 0.95;
  }
  
  .stat-icon i {
      font-size: 2.5rem;
      opacity: 0.3;
  }
  
  .card-link:hover .stat-icon i {
      opacity: 0.5;
  }
</style>
<!-- Sidebar -->
<div class="border-right bg-dark" id="sidebar-wrapper">
    <div class="user text-center py-4">
        <img class="img img-fluid rounded-circle mb-2" src="<?php echo isset(
    $userprofile) ? $userprofile : (isset($_SESSION['userprofile']) ? $_SESSION['userprofile'] : 'uploads/default_profile.png'); ?>" width="120" alt="User Profile">
        <h5 class="mb-1 text-white"><?php echo isset($username) ? $username : (isset($_SESSION['username']) ? $_SESSION['username'] : ''); ?></h5>
        <p class="text-muted small mb-0"><?php echo isset($useremail) ? $useremail : (isset($_SESSION['useremail']) ? $_SESSION['useremail'] : ''); ?></p>
    </div>
    
    <!-- Main Navigation -->
    <div class="sidebar-heading">Management</div>
    <div class="list-group list-group-flush">
        <a href="index.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'sidebar-active' : ''; ?>">
            <span data-feather="home"></span> Dashboard
        </a>
        
        <a href="project_list.php" class="list-group-item list-group-item-action <?php echo (basename($_SERVER['PHP_SELF']) == 'project_list.php' || basename($_SERVER['PHP_SELF']) == 'project_detail.php') ? 'sidebar-active' : ''; ?>">
            <span data-feather="clipboard"></span> Projects
        </a>
        
        <a href="manage_expense.php" class="list-group-item list-group-item-action <?php echo (basename($_SERVER['PHP_SELF']) == 'add_expense.php' || basename($_SERVER['PHP_SELF']) == 'manage_expense.php') ? 'sidebar-active' : ''; ?>">
            <span data-feather="dollar-sign"></span> Expenses
        </a>

        <a href="materials_list.php" class="list-group-item list-group-item-action <?php echo (basename($_SERVER['PHP_SELF']) == 'add_materials.php' || basename($_SERVER['PHP_SELF']) == 'materials_list.php') ? 'sidebar-active' : ''; ?>">
            <span data-feather="package"></span> Materials
        </a>

        <a href="equipment_list.php" class="list-group-item list-group-item-action <?php echo (basename($_SERVER['PHP_SELF']) == 'add_equipment.php' || basename($_SERVER['PHP_SELF']) == 'equipment_list.php') ? 'sidebar-active' : ''; ?>">
            <span data-feather="tool"></span> Equipment
        </a>
        
        <a href="supplier_list.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'supplier_list.php' ? 'sidebar-active' : ''; ?>">
            <span data-feather="truck"></span> Suppliers
        </a>
        
        <a href="employee_list.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'employee_list.php' ? 'sidebar-active' : ''; ?>">
            <span data-feather="users"></span> Employees
        </a>
        
        <a href="position.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'position.php' ? 'sidebar-active' : ''; ?>">
            <span data-feather="briefcase"></span> Position
        </a>
    </div>
    
    <!-- Settings Section -->
    <div class="sidebar-heading">Settings</div>
    <div class="list-group list-group-flush">
        <a href="profile.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'sidebar-active' : ''; ?>">
            <span data-feather="user"></span> Profile
        </a>
        <a href="#" data-toggle="modal" data-target="#logoutConfirmModal" class="list-group-item list-group-item-action">
            <span data-feather="power"></span> Logout
        </a>
    </div>
</div>

<!-- Add this script to the end of your page or in your main JS file -->
<script>
// Initialize Feather Icons
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>
