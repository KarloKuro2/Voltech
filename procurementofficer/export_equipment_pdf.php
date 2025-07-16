<?php
require_once('fpdf.php');
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// Fetch all equipment
$sql = "SELECT equipment_name, category, rental_fee, depreciation, equipment_price FROM equipment ORDER BY equipment_name";
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

$header = ['No', 'Equipment Name', 'Rent/Company', 'Rental Fee', 'Depreciation', 'Equipment Price'];
$widths = [10, 60, 35, 35, 35, 40];
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
    $pdf->Cell($widths[2], 7, $row['category'], 1);
    // Rental Fee
    if ($row['category'] === 'Rental') {
        $rentalFee = (isset($row['rental_fee']) && $row['rental_fee'] !== '') ? 'Php ' . number_format($row['rental_fee'], 2) : '—';
        $pdf->Cell($widths[3], 7, $rentalFee, 1);
        $pdf->Cell($widths[4], 7, '—', 1);
        $pdf->Cell($widths[5], 7, '—', 1);
    } else { // Company
        $pdf->Cell($widths[3], 7, '—', 1);
        // Depreciation formatting
        if (isset($row['depreciation']) && $row['depreciation'] !== '') {
            $depr = floatval($row['depreciation']);
            $deprStr = (intval($depr) == $depr) ? intval($depr) . ' yrs' : $depr . ' yrs';
        } else {
            $deprStr = '—';
        }
        $pdf->Cell($widths[4], 7, $deprStr, 1);
        // Equipment Price
        $equipPrice = (isset($row['equipment_price']) && $row['equipment_price'] !== '') ? 'Php ' . number_format($row['equipment_price'], 2) : '—';
        $pdf->Cell($widths[5], 7, $equipPrice, 1);
    }
    $pdf->Ln();
}

$pdf->Output('D', 'equipment.pdf');
exit; 