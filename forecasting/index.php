<?php
require_once 'config.php';
require_once 'database.php';

$db = new Database();
$message = '';
$stats = $db->getProjectStats();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $old_size = floatval($_POST['old_size']);
    $old_cost = floatval($_POST['old_cost']);
    $new_size = floatval($_POST['new_size']);
    $project_name = trim($_POST['project_name']);
    $notes = trim($_POST['notes']);

    if ($old_size > 0 && $old_cost > 0 && $new_size > 0 && !empty($project_name)) {
        $estimated_cost = ($new_size / $old_size) * $old_cost;
        
        $data = [
            'project_name' => $project_name,
            'old_size' => $old_size,
            'old_cost' => $old_cost,
            'new_size' => $new_size,
            'estimated_cost' => $estimated_cost,
            'notes' => $notes
        ];

        if ($db->saveProject($data)) {
            $message = '<div class="alert alert-success">Project estimate saved successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error saving project estimate.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Please fill all required fields with valid values.</div>';
    }
}

$recent_projects = $db->getRecentProjects();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Construction Cost Estimator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .card { box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
        .form-control:focus { border-color: #80bdff; box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); }
        .stats-card { transition: transform 0.3s; }
        .stats-card:hover { transform: translateY(-5px); }
        .chart-container { position: relative; margin: auto; height: 300px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1">
                <i class="fas fa-building"></i> Construction Cost Estimator
            </span>
        </div>
    </nav>

    <div class="container">
        <?php echo $message; ?>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card text-center bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Projects</h5>
                        <p class="card-text h3"><?php echo $stats['total_projects']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Average Cost</h5>
                        <p class="card-text h3">$<?php echo number_format($stats['avg_cost'], 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Latest Project</h5>
                        <p class="card-text h3">$<?php echo number_format($stats['latest_cost'], 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Average Size</h5>
                        <p class="card-text h3"><?php echo number_format($stats['avg_size'], 1); ?> m²</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-calculator"></i> New Estimate
                    </div>
                    <div class="card-body">
                        <form method="post" id="estimateForm">
                            <div class="mb-3">
                                <label class="form-label">Project Name:</label>
                                <input type="text" class="form-control" name="project_name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reference Project Size (m²):</label>
                                <input type="number" step="0.01" class="form-control" name="old_size" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reference Project Cost ($):</label>
                                <input type="number" step="0.01" class="form-control" name="old_cost" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Project Size (m²):</label>
                                <input type="number" step="0.01" class="form-control" name="new_size" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes:</label>
                                <textarea class="form-control" name="notes" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-calculator"></i> Calculate Estimate
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-chart-bar"></i> Recent Projects Overview
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="projectsChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-history"></i> Project History
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Size (m²)</th>
                                        <th>Estimated Cost</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_projects as $project): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                        <td><?php echo number_format($project['new_size'], 1); ?></td>
                                        <td>$<?php echo number_format($project['estimated_cost'], 2); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($project['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ctx = document.getElementById('projectsChart').getContext('2d');
        const projects = <?php echo json_encode($recent_projects); ?>;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: projects.map(p => p.project_name),
                datasets: [{
                    label: 'Estimated Cost ($)',
                    data: projects.map(p => p.estimated_cost),
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Previous Cost ($)',
                    data: projects.map(p => p.old_cost),
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'New Size (m²)',
                    data: projects.map(p => p.new_size),
                    backgroundColor: 'rgba(255, 159, 64, 0.5)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1,
                    yAxisID: 'y1'
                },
                {
                    label: 'Previous Size (m²)',
                    data: projects.map(p => p.old_size),
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString() + ' m²';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    </script>
</body>
</html>
