<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 2) {
    die('Unauthorized');
}
require_once('fpdf.php');
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) die("Connection failed: " . $con->connect_error);

// Fetch all users
$query = "SELECT * FROM users ORDER BY firstname, lastname";
$result = $con->query($query);

// PDF output
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 12, 'User Activity Reports', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Ln(4);

// Table header
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(70, 10, 'Full Name', 1);
$pdf->Cell(50, 10, 'User Level', 1);
$pdf->Cell(60, 10, 'Latest Activity', 1);
$pdf->Ln();
$pdf->SetFont('Arial', '', 12);

// Table rows
while ($user = $result->fetch_assoc()) {
    $fullName = $user['firstname'] . ' ' . $user['lastname'];
    switch ($user['user_level']) {
        case 1:
            $userLevel = 'Super Admin';
            break;
        case 2:
            $userLevel = 'Admin';
            break;
        case 3:
            $userLevel = 'Project Manager';
            break;
        case 4:
            $userLevel = 'Procurement Officer';
            break;
        default:
            $userLevel = 'Unknown';
    }
    $latestActivity = 'N/A'; // Placeholder
    $pdf->Cell(70, 10, iconv('UTF-8', 'ISO-8859-1', $fullName), 1);
    $pdf->Cell(50, 10, $userLevel, 1);
    $pdf->Cell(60, 10, $latestActivity, 1);
    $pdf->Ln();
}

$pdf->Output('D', 'user_activity_reports.pdf'); 