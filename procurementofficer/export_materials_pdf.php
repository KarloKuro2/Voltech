<?php
require_once('fpdf.php');
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// Fetch all materials
$sql = "SELECT category, material_name, quantity, unit, status, supplier_name, total_amount FROM materials ORDER BY category, material_name";
$result = $con->query($sql);

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
$pdf->Ln(2);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,7,'Procurement Officer',0,1,'L');
$pdf->Ln(2);
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Materials List',0,1,'C');
$pdf->Ln(2);

// Table header
$pdf->SetFont('Arial','B',10);
$header = ['#', 'Category', 'Material Name', 'Qty', 'Unit', 'Status', 'Supplier', 'Total Amount'];
$widths = [10, 28, 40, 15, 15, 20, 40, 32]; // Total: 180mm
$leftMargin = 10;
$pdf->SetLeftMargin($leftMargin);
$tableWidth = array_sum($widths);
$pageWidth = $pdf->GetPageWidth() - 2 * $leftMargin;
$x = ($pageWidth - $tableWidth) / 2 + $leftMargin;
$pdf->SetX($x);
foreach ($header as $i => $col) {
    $pdf->Cell($widths[$i], 8, $col, 1, 0, 'C');
}
$pdf->Ln();

$pdf->SetFont('Arial','',9);
$count = 1;
while ($row = $result->fetch_assoc()) {
    $pdf->SetX($x);
    $pdf->Cell($widths[0], 7, $count++, 1, 0, 'C');
    $pdf->Cell($widths[1], 7, $row['category'], 1);
    $pdf->Cell($widths[2], 7, $row['material_name'], 1);
    $pdf->Cell($widths[3], 7, $row['quantity'], 1, 0, 'C');
    $pdf->Cell($widths[4], 7, $row['unit'], 1, 0, 'C');
    $pdf->Cell($widths[5], 7, $row['status'], 1, 0, 'C');
    $pdf->Cell($widths[6], 7, $row['supplier_name'], 1);
    $pdf->Cell($widths[7], 7, 'Php ' . number_format($row['total_amount'], 2), 1, 0, 'C');
    $pdf->Ln();
}

$pdf->Output('D', 'materials.pdf');
exit;
