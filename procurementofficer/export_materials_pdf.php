<?php
require_once('fpdf.php');
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// Fetch all materials
$sql = "SELECT category, material_name, quantity, unit, status, supplier_name, total_amount FROM materials ORDER BY category, material_name";
$result = $con->query($sql);

class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial','B',14);
        $this->Cell(0,10,'Materials List',0,1,'C');
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
$pdf->AddPage('L', 'Letter'); // Use Letter size
$pdf->SetFont('Arial','B',10);

$header = ['#', 'Category', 'Material Name', 'Quantity', 'Unit', 'Status', 'Supplier', 'Total Amount'];
$widths = [10, 35, 50, 20, 20, 25, 45, 30];
$leftMargin = 15; // You can adjust this value for more/less space
$pdf->SetLeftMargin($leftMargin);

// Calculate total table width and center X
$tableWidth = array_sum($widths);
$pageWidth = $pdf->GetPageWidth() - 2 * $leftMargin;
$x = ($pageWidth - $tableWidth) / 2 + $leftMargin;

// Table header
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
