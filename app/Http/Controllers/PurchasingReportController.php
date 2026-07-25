<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\AccountsPayableAgingService;
use App\Services\SupplierStatementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchasingReportController extends Controller
{
    public function statement(Request $request, Supplier $supplier, SupplierStatementService $service): View
    {
        abort_unless($request->user()->hasPermission('supplier_statements.view'), 403);
        $this->authorize('viewBalance', $supplier);
        $currencyId = (int) ($request->integer('currency_id') ?: $supplier->currency_id ?: app(TenantContext::class)->company()->currency_id);

        return view('purchasing-reports.statement', [
            'supplier' => $supplier,
            'statement' => $service->build($supplier, $currencyId, $request->integer('branch_id') ?: null, $request->input('from'), $request->input('to')),
        ]);
    }

    public function aging(Request $request, AccountsPayableAgingService $service): View
    {
        abort_unless($request->user()->hasPermission('accounts_payable.aging'), 403);

        return view('purchasing-reports.aging', [
            'aging' => $service->report($request->integer('branch_id') ?: null, $request->integer('currency_id') ?: null),
        ]);
    }

    public function operational(Request $request, string $report, TenantContext $tenant): View
    {
        abort_unless($request->user()->hasPermission('purchase_orders.view'), 403);
        $branches = $tenant->accessibleBranches()->pluck('id');
        $data = match ($report) {
            'open-orders' => PurchaseOrder::where('company_id', $tenant->companyId())->whereIn('branch_id', $branches)
                ->whereIn('status', ['sent', 'partially_received'])->with('supplier')->get(),
            'pending-receipts' => GoodsReceipt::where('company_id', $tenant->companyId())->whereIn('branch_id', $branches)
                ->whereNotIn('status', ['posted', 'cancelled', 'reversed'])->with('supplier')->get(),
            'unmatched-invoices' => SupplierInvoice::where('company_id', $tenant->companyId())->whereIn('branch_id', $branches)
                ->whereHas('items.matches', fn ($query) => $query->where('status', '!=', 'matched')->whereNull('approved_by'))->with('supplier')->get(),
            'purchase-returns' => PurchaseReturn::where('company_id', $tenant->companyId())->whereIn('branch_id', $branches)->with('supplier')->get(),
        };

        return view('purchasing-reports.operational', compact('report', 'data'));
    }
}
