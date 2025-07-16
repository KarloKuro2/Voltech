<?php
  include("session.php");

  // Define forecasting database constants if not already defined
  if (!defined('DB_HOST')) {
      define('DB_HOST', 'localhost');
      define('DB_USER', 'root');
      define('DB_PASS', '');
      define('DB_NAME', 'Voltech2');
  }

  // Forecasting Database class
  class Database {
    private $conn;

    public function __construct() {
        $this->connect();
        $this->createTables();
    }

    private function connect() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    private function createTables() {
        $sql = "CREATE TABLE IF NOT EXISTS projects_forecasting (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_name VARCHAR(255) NOT NULL,
            old_size FLOAT NOT NULL,
            old_cost FLOAT NOT NULL,
            new_size FLOAT NOT NULL,
            estimated_cost FLOAT NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->conn->query($sql);
    }

    public function getRecentProjects($limit = 5) {
        $result = $this->conn->query("SELECT * FROM projects_forecasting ORDER BY created_at DESC LIMIT $limit");
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Initialize forecasting database and get recent projects
$forecastDb = new Database();
$recent_forecast_projects = $forecastDb->getRecentProjects();

// Fetch Expense Data for Charts
$exp_category_dc = mysqli_query($con, "SELECT expensecategory FROM expenses WHERE user_id = '$userid' GROUP BY expensecategory");
$exp_amt_dc = mysqli_query($con, "SELECT SUM(expense) as total_expense FROM expenses WHERE user_id = '$userid' GROUP BY expensecategory");

// Prepare arrays for chart data
$categories = array();
$amounts = array();

// Store categories and amounts in arrays
while($row = mysqli_fetch_assoc($exp_category_dc)) {
  $categories[] = $row['expensecategory'];
}

while($row = mysqli_fetch_assoc($exp_amt_dc)) {
  $amounts[] = $row['total_expense'];
}

// Fetch data for line chart
$exp_date_line = mysqli_query($con, "SELECT expensedate FROM expenses WHERE user_id = '$userid' GROUP BY expensedate");
$exp_amt_line = mysqli_query($con, "SELECT SUM(expense) as total_expense FROM expenses WHERE user_id = '$userid' GROUP BY expensedate");

// Prepare arrays for line chart
$dates = array();
$date_amounts = array();

// Store dates and amounts in arrays
while($row = mysqli_fetch_assoc($exp_date_line)) {
  $dates[] = $row['expensedate'];
}

while($row = mysqli_fetch_assoc($exp_amt_line)) {
  $date_amounts[] = $row['total_expense'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="description" content="">
<meta name="author" content="">

<title>Project Manager - Dashboard</title>

<!-- Bootstrap core CSS -->
<link href="css/bootstrap.min.css" rel="stylesheet">
<link href="css/style.css" rel="stylesheet">
<link href="css/sidebar.css" rel="stylesheet">

<!-- Feather JS for Icons -->
<script src="https://unpkg.com/feather-icons@4.28.0/dist/feather.min.js"></script>

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
          left: 15px;
          top: 12px;
          z-index: 1060;
          background: transparent;
          border: none;
          color: #fff;
          padding: 8px;
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
          padding-left: 60px;
          transition: all 0.3s ease;
      }

      #wrapper.toggled .navbar {
          padding-left: 15px;
      }

      /* Hide profile dropdown when sidebar is open */
      #wrapper.toggled .navbar-nav {
          display: none !important;
      }

      /* Ensure navbar maintains height when profile is hidden */
      #wrapper.toggled .navbar {
          min-height: 56px;
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
</head>

<body>
  <!-- Add this overlay div -->
  <div class="sidebar-overlay" onclick="$('#wrapper').removeClass('toggled');"></div>

<div class="d-flex" id="wrapper">

  <!-- Sidebar -->
  <div class="border-right bg-dark" id="sidebar-wrapper">
    <div class="user text-center py-4">
      <img class="img img-fluid rounded-circle mb-2" src="<?php echo $userprofile ?>" width="120" alt="User Profile">
      <h5 class="mb-1"><?php echo $username ?></h5>
      <p class="text-muted small mb-0"><?php echo $useremail ?></p>
    </div>
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

      <a href="equipment_list.php" class="list-group-item list-group-item-action <?php echo (basename($_SERVER['PHP_SELF']) == 'add_equipment_list.php' || basename($_SERVER['PHP_SELF']) == 'equipment_list.php') ? 'sidebar-active' : ''; ?>">
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
  <!-- /#sidebar-wrapper -->

  <!-- Page Content -->
  <div id="page-content-wrapper">
    <!-- Navbar -->
    <?php include('includes/navbar.php'); ?>
    <!-- Navbar -->
    <div class="container-fluid">
      <h3 class="mt-4">Dashboard</h3>

      <!-- Stats Cards Row -->
      <div class="row mb-4">
          <!-- Total Expenses Card -->
          <div class="col-xl-3 col-md-6 mb-4">
              <a href="manage_expense.php" class="card-link">
                  <div class="card shadow h-100 bg-primary text-white hover-effect">
                      <div class="card-body">
                          <div class="d-flex justify-content-between align-items-center">
                              <div>
                                  <div class="text-xs font-weight-bold text-uppercase mb-1">Total Expenses</div>
                                  <div class="h5 mb-0 font-weight-bold">
                                      <?php
                                      $exp_query = mysqli_query($con, "SELECT SUM(expense) as total FROM expenses WHERE user_id='$userid'");
                                      $exp_result = mysqli_fetch_assoc($exp_query);
                                      echo '₱ ' . number_format($exp_result['total'] ?? 0, 2);
                                      ?>
                                  </div>
                              </div>
                              <div class="stat-icon">
                                  <i data-feather="dollar-sign"></i>
                              </div>
                          </div>
                      </div>
                  </div>
              </a>
          </div>

          <!-- Equipment Count Card -->
          <div class="col-xl-3 col-md-6 mb-4">
              <a href="equipment_list.php" class="card-link">
                  <div class="card shadow h-100 bg-success text-white hover-effect">
                      <div class="card-body">
                          <div class="d-flex justify-content-between align-items-center">
                              <div>
                                  <div class="text-xs font-weight-bold text-uppercase mb-1">Total Equipment</div>
                                  <div class="h5 mb-0 font-weight-bold">
                                      <?php
                                      $equip_query = mysqli_query($con, "SELECT COUNT(*) as count FROM equipment");
                                      $equip_result = mysqli_fetch_assoc($equip_query);
                                      echo number_format($equip_result['count'] ?? 0);
                                      ?>
                                  </div>
                              </div>
                              <div class="stat-icon">
                                  <i data-feather="tool"></i>
                              </div>
                          </div>
                      </div>
                  </div>
              </a>
          </div>

          <!-- Active Projects Card -->
          <div class="col-xl-3 col-md-6 mb-4">
              <a href="project_list.php" class="card-link">
                  <div class="card shadow h-100 bg-info text-white hover-effect">
                      <div class="card-body">
                          <div class="d-flex justify-content-between align-items-center">
                              <div>
                                  <div class="text-xs font-weight-bold text-uppercase mb-1">Active Projects</div>
                                  <div class="h5 mb-0 font-weight-bold">
                                      <?php
                                      $proj_query = mysqli_query($con, "SELECT COUNT(*) as count FROM projects_forecasting WHERE 1");
                                      $proj_result = mysqli_fetch_assoc($proj_query);
                                      echo number_format($proj_result['count'] ?? 0);
                                      ?>
                                  </div>
                              </div>
                              <div class="stat-icon">
                                  <i data-feather="clipboard"></i>
                              </div>
                          </div>
                      </div>
                  </div>
              </a>
          </div>

          <!-- Total Employees Card -->
          <div class="col-xl-3 col-md-6 mb-4">
              <a href="employee_list.php" class="card-link">
                  <div class="card shadow h-100 bg-warning text-dark hover-effect">
                      <div class="card-body">
                          <div class="d-flex justify-content-between align-items-center">
                              <div>
                                  <div class="text-xs font-weight-bold text-uppercase mb-1">Total Employees</div>
                                  <div class="h5 mb-0 font-weight-bold">
                                      <?php
                                      $emp_query = mysqli_query($con, "SELECT COUNT(*) as count FROM employees");
                                      $emp_result = mysqli_fetch_assoc($emp_query);
                                      echo number_format($emp_result['count'] ?? 0);
                                      ?>
                                  </div>
                              </div>
                              <div class="stat-icon">
                                  <i data-feather="users"></i>
                              </div>
                          </div>
                      </div>
                  </div>
              </a>
          </div>
      </div>

      <h3 class="mt-4">Full-Expense Report</h3>
      <div class="row">
          <div class="col-md-6">
              <div class="card">  
                  <div class="card-header">
                      <h6 class="card-title text-center mb-0">Yearly Expenses</h6>
                  </div>
                  <div class="card-body" style="height: 300px;">
                      <canvas id="expense_line" height="280"></canvas>
                  </div>
              </div>
          </div>
          <div class="col-md-6">
              <div class="card">
                  <div class="card-header py-2">
                      <h6 class="card-title text-center mb-0">Expense Category</h6>
                  </div>
                  <div class="card-body d-flex justify-content-center align-items-center" style="height: 300px; padding-top: 5px; padding-bottom: 5px;">
                      <canvas id="expense_category_pie" height="290" width="290"></canvas>
                  </div>
              </div>
          </div>
      </div>
      <!-- Project Forecasting Chart -->
      <h3 class="mt-4">Project Cost Forecasting</h3>
      <div class="row mt-4">
          <div class="col-md-6">
              <div class="card" style="max-height: 250px;">  
                  <div class="card-header">
                      <h6 class="card-title text-center mb-0">Project Cost Comparison</h6>  
                  </div>
                  <div class="card-body">
                      <canvas id="projectsChart" height="150"></canvas>  
                  </div>
              </div>
          </div>
          <div class="col-md-6">
              <div class="card" style="max-height: 250px;">  
                  <div class="card-header">
                      <h6 class="card-title text-center mb-0">Project Size Comparison</h6>  
                  </div>
                  <div class="card-body">
                      <canvas id="projectsSizeChart" height="150"></canvas>  
                  </div>
              </div>
          </div>
      </div>
    </div>
  </div>
</div>

<!-- Edit Expense Modal -->
<div class="modal fade" id="editExpenseModal" tabindex="-1" role="dialog" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
      <div class="modal-content">
          <div class="modal-header">
              <h5 class="modal-title" id="editExpenseModalLabel">Edit Expense</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
              </button>
          </div>
          <form action="update_expense.php" method="POST">
              <div class="modal-body">
                  <input type="hidden" name="expense_id" id="edit_expense_id">
                  <div class="form-group">
                      <label>Date</label>
                      <input type="date" class="form-control" name="expensedate" id="edit_expensedate" required>
                  </div>
                  <div class="form-group">
                      <label>Amount</label>
                      <input type="number" class="form-control" name="expense" id="edit_expense" step="0.01" required>
                  </div>
                  <div class="form-group">
                      <label>Category</label>
                      <select class="form-control" name="expensecategory" id="edit_expensecategory" required>
                          <option value="Equipment">Equipment</option>
                          <option value="Material">Material</option>
                          <option value="Labor">Labor</option>
                          <option value="Transportation">Transportation</option>
                          <option value="Other">Other</option>
                      </select>
                  </div>
                  <div class="form-group">
                      <label>Description</label>
                      <input type="text" class="form-control" name="expensedesc" id="edit_expensedesc" required>
                  </div>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                  <button type="submit" class="btn btn-primary">Save Changes</button>
              </div>
          </form>
      </div>
  </div>
</div>

<!-- JavaScript -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
<script src="https://unpkg.com/feather-icons@4.28.0/dist/feather.min.js"></script>

<script>
  // Basic initialization
  document.addEventListener('DOMContentLoaded', function() {
      // Initialize Feather Icons
      feather.replace();

      // Sidebar toggle functionality
      $("#menu-toggle, .sidebar-overlay").click(function(e) {
          e.preventDefault();
          $("#wrapper").toggleClass("toggled");
      });

      // Close sidebar when clicking menu items on mobile
      if ($(window).width() < 768) {
          $('.list-group-item').click(function() {
              $("#wrapper").removeClass("toggled");
          });
      }

      // Close sidebar when window is resized to desktop size
      $(window).resize(function() {
          if ($(window).width() >= 768) {
              $("#wrapper").removeClass("toggled");
          }
      });

      // Chart initialization
      // Expense Category Pie Chart
      var ctx1 = document.getElementById("expense_category_pie").getContext('2d');
      var expenseCategoryChart = new Chart(ctx1, {
          type: 'pie',
          data: {
              labels: [<?php foreach($categories as $category) { echo '"' . $category . '",'; } ?>],
              datasets: [{
                  data: [<?php foreach($amounts as $amount) { echo $amount . ','; } ?>],
                  backgroundColor: ["#FF6384", "#36A2EB", "#FFCE56", "#4BC0C0", "#9966FF"]
              }]
          },
          options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                  legend: {
                      position: 'right',
                      labels: {
                          boxWidth: 12,
                          padding: 10,
                          font: {
                              size: 12
                          }
                      }
                  }
              },
              layout: {
                  padding: {
                      top: 0,
                      bottom: 0
                  }
              }
          }
      });

      // Yearly Expenses Line Chart
      var ctx2 = document.getElementById("expense_line").getContext('2d');
      var expenseLineChart = new Chart(ctx2, {
          type: 'line',
          data: {
              labels: [<?php foreach($dates as $date) { echo '"' . $date . '",'; } ?>],
              datasets: [{
                  label: 'Total Expenses',
                  data: [<?php foreach($date_amounts as $amount) { echo $amount . ','; } ?>],
                  backgroundColor: 'rgba(54, 162, 235, 0.2)',
                  borderColor: 'rgba(54, 162, 235, 1)',
                  borderWidth: 1
              }]
          }
      });

      // Project Forecasting Charts
      if (document.getElementById('projectsChart')) {
          const ctx3 = document.getElementById('projectsChart').getContext('2d');
          const projectsChart = new Chart(ctx3, {
              type: 'bar',
              data: {
                  labels: [<?php 
                      $chart_labels = array_map(function($p) {
                          return "'" . htmlspecialchars($p['project_name'], ENT_QUOTES) . "'";
                      }, array_slice($recent_forecast_projects, 0, 5));
                      echo implode(', ', $chart_labels);
                  ?>],
                  datasets: [{
                      label: 'Estimated Cost ($)',
                      data: [<?php 
                          $chart_data = array_map(function($p) {
                              return $p['estimated_cost'];
                          }, array_slice($recent_forecast_projects, 0, 5));
                          echo implode(', ', $chart_data);
                      ?>],
                      backgroundColor: 'rgba(54, 162, 235, 0.7)',
                      borderColor: 'rgba(54, 162, 235, 1)',
                      borderWidth: 1
                  }, {
                      label: 'Previous Cost ($)',
                      data: [<?php 
                          $chart_data = array_map(function($p) {
                              return $p['old_cost'];
                          }, array_slice($recent_forecast_projects, 0, 5));
                          echo implode(', ', $chart_data);
                      ?>],
                      backgroundColor: 'rgba(255, 99, 132, 0.7)',
                      borderColor: 'rgba(255, 99, 132, 1)',
                      borderWidth: 1
                  }]
              },
              options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  scales: {
                      y: {
                          beginAtZero: true,
                          ticks: {
                              callback: function(value) {
                                  return '₱ ' + value.toLocaleString();
                              }
                          }
                      }
                  }
              }
          });
      }

      // Edit Expense Modal Handler
      $(document).on('click', '.edit-expense', function(e) {
          e.preventDefault();
          var id = $(this).data('id');
          var date = $(this).data('date');
          var amount = $(this).data('amount');
          var category = $(this).data('category');
          var description = $(this).data('description');

          $('#edit_expense_id').val(id);
          $('#edit_expensedate').val(date);
          $('#edit_expense').val(amount);
          $('#edit_expensecategory').val(category);
          $('#edit_expensedesc').val(description);

          $('#editExpenseModal').modal('show');
      });
  });
</script>
</body>
</html>