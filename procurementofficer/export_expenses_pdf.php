<?php
require_once('fpdf.php');
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// Fetch all expenses
$sql = "SELECT expensedate, expensecategory, expense, description FROM expenses ORDER BY expensedate DESC";
$result = $con->query($sql);

class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial','B',14);
        $this->Cell(0,10,'Expenses List',0,1,'C');
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

$header = ['#', 'Date', 'Type', 'Amount', 'Description'];
$widths = [12, 40, 70, 35, 110]; // Increased 'Type' column width
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
    $pdf->Cell($widths[1], 7, date('M d, Y', strtotime($row['expensedate'])), 1);
    $pdf->Cell($widths[2], 7, $row['expensecategory'], 1);
    $pdf->Cell($widths[3], 7, 'Php ' . number_format($row['expense'], 2), 1, 0, 'C');
    $pdf->Cell($widths[4], 7, $row['description'], 1);
    $pdf->Ln();
}

$pdf->Output('D', 'expenses.pdf');
exit; 