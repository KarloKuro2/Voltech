<?php
require_once('fpdf.php');
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// Fetch all equipment
$sql = "SELECT equipment_name, used_in, usage_purpose, borrow_time, return_time, status FROM equipment ORDER BY equipment_name";
$result = $con->query($sql);

class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial','B',14);
        $this->Cell(0,10,'Equipment List',0,1,'C');
        $this->Ln(2);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage('L', 'Letter');
$pdf->SetFont('Arial','B',10);

$header = ['#', 'Equipment Name', 'Used In', 'Purpose', 'Borrow Time', 'Return Time', 'Status'];
$widths = [10, 45, 35, 60, 35, 35, 25];
$leftMargin = 15;
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
    $pdf->Cell($widths[1], 7, $row['equipment_name'], 1);
    $pdf->Cell($widths[2], 7, $row['used_in'], 1);
    $pdf->Cell($widths[3], 7, $row['usage_purpose'], 1);
    $pdf->Cell($widths[4], 7, $row['borrow_time'] ? date('M d, Y h:i A', strtotime($row['borrow_time'])) : 'N/A', 1);
    $pdf->Cell($widths[5], 7, $row['return_time'] ? date('M d, Y h:i A', strtotime($row['return_time'])) : 'N/A', 1);
    $pdf->Cell($widths[6], 7, $row['status'], 1, 0, 'C');
    $pdf->Ln();
}

$pdf->Output('D', 'equipment.pdf');
exit; 