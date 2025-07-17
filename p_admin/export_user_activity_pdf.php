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