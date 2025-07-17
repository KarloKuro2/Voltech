<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    die('Unauthorized');
}
require_once('fpdf.php');
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) die("Connection failed: " . $con->connect_error);
$userid = $_SESSION['user_id'];
$start = isset($_POST['start_date']) ? $_POST['start_date'] : null;
$end = isset($_POST['end_date']) ? $_POST['end_date'] : null;
if (!$start || !$end) die('Date range required');

// Project analytics (from projects table)
$project_count = 0;
$category_counts = ['House' => 0, 'Renovation' => 0, 'Building' => 0];
$total_budget = 0;
$proj_query = mysqli_query($con, "SELECT category, budget FROM projects WHERE user_id='$userid'");
while ($row = mysqli_fetch_assoc($proj_query)) {
    $project_count++;
    $cat = ucfirst(strtolower(trim($row['category'])));
    if (isset($category_counts[$cat])) $category_counts[$cat]++;
    $total_budget += floatval($row['budget']);
}
$average_budget = $project_count > 0 ? $total_budget / $project_count : 0;
$estimated_total_expenses = $total_budget; // This is the sum of all project budgets

// Total expenses (from expenses table, filtered by expensedate)
$total_expenses = 0;
$exp_query = mysqli_query($con, "SELECT SUM(expense) as total FROM expenses WHERE user_id='$userid' AND expensedate BETWEEN '$start' AND '$end'");
if ($row = mysqli_fetch_assoc($exp_query)) {
    $total_expenses = $row['total'] ?? 0;
}

// PDF output
$pdf = new FPDF();
$pdf->AddPage();
// Voltech Letterhead
$pdf->Image('../uploads/voltech_logo_transparent.png', 10, 10, 28);
$pdf->SetXY(40, 12);
$pdf->SetFont('Arial','B',15);
$pdf->Cell(0,7,'VOLTECH ELECTRICAL CONSTRUCTION',0,1);
$pdf->SetX(40);
$pdf->SetFont('Arial','B',10);
$pdf->SetTextColor(90,90,90);
$pdf->Cell(0,6,'CONTRACTORS    ENGINEERS    DESIGNERS    CONSULTANTS',0,1);
$pdf->SetX(40);
$pdf->SetFont('Arial','',8);
$pdf->SetTextColor(60,60,60);
$pdf->Cell(0,5,'Office: 60 AT Reyes St., Pag-asa Mandaluyong City',0,1);
$pdf->SetX(40);
$pdf->Cell(0,5,'Prov. Address: 729 Malapit, San Isidro Nueva Ecija',0,1);
$pdf->SetX(40);
$pdf->Cell(0,5,'Contact Nos.: 0917 418 8456  •  0923 966 2079',0,1);
$pdf->SetTextColor(0,0,0);
$pdf->Ln(6);
$pdf->SetDrawColor(120,120,120);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 12, 'Dashboard Summary', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Ln(4);
$pdf->Cell(0, 8, "Date Range: $start to $end", 0, 1);
$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Project Analytics', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, "Total Projects: $project_count", 0, 1);
$pdf->Cell(0, 8, "Average Project Budget: Php " . number_format($average_budget, 2), 0, 1);
$pdf->Cell(0, 8, 'Projects by Category:', 0, 1);
foreach ($category_counts as $cat => $count) {
    $pdf->Cell(0, 8, "  - $cat: $count", 0, 1);
}
$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Estimated Total Expenses (All Projects): Php ' . number_format($estimated_total_expenses, 2), 0, 1);
$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Total Expenses', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, "Total Expenses (from $start to $end): Php " . number_format($total_expenses, 2), 0, 1);
$pdf->Ln(4);
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 8, '[Chart export placeholder: To include a chart, export it as an image in the browser and upload it to the server for PDF embedding.]', 0, 1);

if (isset($_POST['chart_image']) && !empty($_POST['chart_image'])) {
    $img = $_POST['chart_image'];
    $img = str_replace('data:image/png;base64,', '', $img);
    $img = str_replace(' ', '+', $img);
    $imgData = base64_decode($img);
    $imgFile = tempnam(sys_get_temp_dir(), 'chart') . '.png';
    file_put_contents($imgFile, $imgData);
    // Add image to PDF (new page)
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'House Projects Chart', 0, 1, 'C');
    $pdf->Image($imgFile, 15, 30, 180); // X, Y, Width (mm)
    unlink($imgFile);
}

$pdf->Output('D', 'dashboard_summary.pdf'); 