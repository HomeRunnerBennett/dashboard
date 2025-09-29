<?php
require_once 'vendor/autoload.php'; // For TCPDF or other PDF library

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $export_data = json_decode($_POST['export_data'], true);
    $dashboard = $_POST['dashboard'] ?? 'malpay';
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Dashboard System');
    $pdf->SetAuthor('Dashboard System');
    $pdf->SetTitle(ucfirst($dashboard) . ' Transaction Report');
    $pdf->SetSubject('Transaction Report');
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Title
    $pdf->Cell(0, 10, ucfirst($dashboard) . ' Transaction Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Date Range: ' . $export_data['date_range'], 0, 1, 'C');
    $pdf->Ln(10);
    
    // Summary Statistics
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Summary Statistics', 0, 1);
    $pdf->SetFont('helvetica', '', 12);
    
    if ($dashboard === 'malpay') {
        $pdf->Cell(0, 8, 'Completed Transactions: ' . number_format($export_data['stats']['total_out_logs']), 0, 1);
        $pdf->Cell(0, 8, 'Total Amount: MK' . number_format($export_data['stats']['total_amount'] ?? 0, 2), 0, 1);
        $pdf->Cell(0, 8, 'Average Response Time: ' . number_format($export_data['stats']['avg_response_time'] ?? 0, 2) . 'ms', 0, 1);
        $pdf->Cell(0, 8, 'Active Merchants: ' . count($export_data['merchants']), 0, 1);
    } else {
        $pdf->Cell(0, 8, 'Total Transactions: ' . number_format($export_data['stats']['total_transactions']), 0, 1);
        $pdf->Cell(0, 8, 'Successful: ' . number_format($export_data['stats']['successful']), 0, 1);
        $pdf->Cell(0, 8, 'Pending: ' . number_format($export_data['stats']['pending']), 0, 1);
        $pdf->Cell(0, 8, 'Failed: ' . number_format($export_data['stats']['failed']), 0, 1);
        $pdf->Cell(0, 8, 'Total Amount: MK' . number_format($export_data['stats']['total_amount'] ?? 0, 2), 0, 1);
    }
    
    $pdf->Ln(10);
    
    // Output the PDF
    $pdf->Output(ucfirst($dashboard) . '_Report_' . str_replace(' to ', '_', $export_data['date_range']) . '.pdf', 'D');
    exit;
}
?>