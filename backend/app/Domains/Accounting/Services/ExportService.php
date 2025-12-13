<?php

namespace App\Domains\Accounting\Services;

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\AccountingEntry;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Exception;

class ExportService
{
    public function exportSalesToExcel(array $sales, string $filename = 'sales.xlsx')
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = ['Reference', 'Date', 'Customer', 'Mode', 'Subtotal', 'Tax', 'Total', 'Payment', 'Status'];
        $sheet->fromArray([$headers], null, 'A1');

        // Data
        $row = 2;
        foreach ($sales as $sale) {
            $sheet->fromArray([
                [
                    $sale['reference'] ?? '',
                    $sale['completed_at'] ?? '',
                    $sale['customer_name'] ?? '',
                    $sale['mode'] ?? '',
                    $sale['subtotal'] ?? 0,
                    $sale['tax_amount'] ?? 0,
                    $sale['total'] ?? 0,
                    $sale['payment_method'] ?? '',
                    $sale['status'] ?? '',
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // Styling
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(20);

        // Save
        $writer = new Xlsx($spreadsheet);
        $filepath = storage_path("exports/$filename");
        $writer->save($filepath);

        return $filepath;
    }

    public function exportPurchasesToExcel(array $purchases, string $filename = 'purchases.xlsx')
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = ['Reference', 'Supplier', 'Date', 'Subtotal', 'Tax', 'Total', 'Status'];
        $sheet->fromArray([$headers], null, 'A1');

        // Data
        $row = 2;
        foreach ($purchases as $purchase) {
            $sheet->fromArray([
                [
                    $purchase['reference'] ?? '',
                    $purchase['supplier_name'] ?? '',
                    $purchase['created_at'] ?? '',
                    $purchase['subtotal'] ?? 0,
                    $purchase['tax_amount'] ?? 0,
                    $purchase['total'] ?? 0,
                    $purchase['status'] ?? '',
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // Styling
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        // Save
        $writer = new Xlsx($spreadsheet);
        $filepath = storage_path("exports/$filename");
        $writer->save($filepath);

        return $filepath;
    }

    public function exportAccountingReport(array $entries, string $filename = 'accounting_report.xlsx')
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = ['Date', 'Reference', 'Description', 'Account', 'Debit', 'Credit', 'Balance'];
        $sheet->fromArray([$headers], null, 'A1');

        // Data
        $row = 2;
        $balance = 0;
        foreach ($entries as $entry) {
            $debit = $entry['type'] === 'debit' ? $entry['amount'] : 0;
            $credit = $entry['type'] === 'credit' ? $entry['amount'] : 0;
            $balance += $debit - $credit;

            $sheet->fromArray([
                [
                    $entry['entry_date'] ?? '',
                    $entry['reference'] ?? '',
                    $entry['description'] ?? '',
                    $entry['account_code'] ?? '',
                    $debit,
                    $credit,
                    $balance,
                ]
            ], null, 'A' . $row);
            $row++;
        }

        // Styling
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getColumnDimension('E')->setNumFmt('0.00');
        $sheet->getColumnDimension('F')->setNumFmt('0.00');
        $sheet->getColumnDimension('G')->setNumFmt('0.00');

        // Save
        $writer = new Xlsx($spreadsheet);
        $filepath = storage_path("exports/$filename");
        $writer->save($filepath);

        return $filepath;
    }

    public function exportSalesToPdf(array $sales, string $filename = 'sales.pdf'): string
    {
        $html = view('exports.sales_pdf', ['sales' => $sales])->render();
        
        $pdf = Pdf::loadHTML($html);
        $filepath = storage_path("exports/$filename");
        $pdf->save($filepath);

        return $filepath;
    }

    public function generateInvoicePdf(Sale $sale, string $filename = null): string
    {
        $filename = $filename ?? "invoice_{$sale->reference}.pdf";
        
        $html = view('exports.invoice_pdf', ['sale' => $sale])->render();
        
        $pdf = Pdf::loadHTML($html);
        $filepath = storage_path("exports/$filename");
        $pdf->save($filepath);

        return $filepath;
    }

    public function generateReceiptPdf(Sale $sale, string $filename = null): string
    {
        $filename = $filename ?? "receipt_{$sale->reference}.pdf";
        
        $html = view('exports.receipt_pdf', ['sale' => $sale])->render();
        
        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');
        $filepath = storage_path("exports/$filename");
        $pdf->save($filepath);

        return $filepath;
    }
}
