<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Purchase;
use App\Domains\Accounting\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    private ExportService $exportService;

    public function __construct()
    {
        $this->exportService = new ExportService();
        $this->middleware('auth:sanctum');
    }

    public function exportSalesExcel(Request $request): Response
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;

        $sales = Sale::where('tenant_id', $tenant_id)
            ->whereBetween('created_at', [$validated['start_date'], $validated['end_date']])
            ->with('items')
            ->get()
            ->toArray();

        $filepath = $this->exportService->exportSalesToExcel($sales);

        return response()->download($filepath, 'sales.xlsx')->deleteFileAfterSend();
    }

    public function exportSalesPdf(Request $request): Response
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;

        $sales = Sale::where('tenant_id', $tenant_id)
            ->whereBetween('created_at', [$validated['start_date'], $validated['end_date']])
            ->with('items', 'user')
            ->get();

        $filepath = $this->exportService->exportSalesToPdf($sales->toArray());

        return response()->download($filepath, 'sales.pdf')->deleteFileAfterSend();
    }

    public function exportPurchasesExcel(Request $request): Response
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;

        $purchases = Purchase::where('tenant_id', $tenant_id)
            ->whereBetween('created_at', [$validated['start_date'], $validated['end_date']])
            ->with('items')
            ->get()
            ->toArray();

        $filepath = $this->exportService->exportPurchasesToExcel($purchases);

        return response()->download($filepath, 'purchases.xlsx')->deleteFileAfterSend();
    }

    public function generateInvoice(Sale $sale): Response
    {
        if ($sale->tenant_id !== auth()->guard('sanctum')->user()->tenant_id) {
            abort(403);
        }

        $filepath = $this->exportService->generateInvoicePdf($sale);

        return response()->download($filepath, "invoice_{$sale->reference}.pdf")->deleteFileAfterSend();
    }

    public function generateReceipt(Sale $sale): Response
    {
        if ($sale->tenant_id !== auth()->guard('sanctum')->user()->tenant_id) {
            abort(403);
        }

        $filepath = $this->exportService->generateReceiptPdf($sale);

        return response()->download($filepath, "receipt_{$sale->reference}.pdf")->deleteFileAfterSend();
    }
}
