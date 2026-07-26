<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\BranchAccountingSetting;
use Illuminate\Support\Facades\DB;

class ControlAccountReconciliationService
{
    public function __construct(
        private TenantContext $tenant,
        private FinancialReportQueryService $queries
    ) {
    }

    public function report(string $type, array $filters): array
    {
        $filters = $this->queries->normalize($filters);
        $accountIds = $this->controlAccounts($type, $filters['branch_id'] ?? null);
        $gl = $this->glBalance($accountIds, $filters, in_array($type, ['suppliers', 'vat_output'], true));
        $operational = match ($type) {
            'customers' => $this->customerBalance($filters),
            'suppliers' => $this->supplierBalance($filters),
            'inventory' => $this->inventoryValue($filters),
            'vat_output' => $this->salesTax($filters),
            'vat_input' => $this->purchaseTax($filters),
            default => throw new BusinessRuleException('Unsupported reconciliation type.'),
        };
        $difference = bcsub($gl, $operational, 4);

        return [
            'type' => $type, 'gl_balance' => $gl, 'operational_balance' => $operational,
            'difference' => $difference, 'balanced' => bccomp($difference, '0', 4) === 0,
            'unposted_documents' => app(UnpostedAccountingSourcesService::class)->count($filters),
        ];
    }

    private function controlAccounts(string $type, ?int $branchId): array
    {
        $column = match ($type) {
            'customers' => 'accounts_receivable_account_id',
            'suppliers' => 'accounts_payable_account_id',
            'inventory' => 'inventory_account_id',
            'vat_output' => 'vat_output_account_id',
            'vat_input' => 'vat_input_account_id',
            default => throw new BusinessRuleException('Unsupported reconciliation type.'),
        };

        return BranchAccountingSetting::query()->where('company_id', $this->tenant->companyId())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->whereNotNull($column)
            ->pluck($column)->unique()->values()->all();
    }

    private function glBalance(array $accounts, array $filters, bool $creditNormal): string
    {
        if ($accounts === []) {
            return '0.0000';
        }
        $row = $this->queries->postedLines([...$filters, 'date_from' => '1900-01-01'])
            ->whereIn('jel.account_id', $accounts)
            ->selectRaw('COALESCE(SUM(jel.base_debit_amount),0) debit, COALESCE(SUM(jel.base_credit_amount),0) credit')->first();

        return $creditNormal
            ? bcsub((string) $row->credit, (string) $row->debit, 4)
            : bcsub((string) $row->debit, (string) $row->credit, 4);
    }

    private function customerBalance(array $filters): string
    {
        return bcadd((string) DB::table('sales_invoices')->where('company_id', $this->tenant->companyId())
            ->whereNotIn('status', ['draft', 'cancelled', 'void'])->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->sum('balance_due'), '0', 4);
    }

    private function supplierBalance(array $filters): string
    {
        return bcadd((string) DB::table('supplier_invoices')->where('company_id', $this->tenant->companyId())
            ->whereNotIn('status', ['draft', 'cancelled'])->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->sum('balance_due'), '0', 4);
    }

    private function inventoryValue(array $filters): string
    {
        $stock = DB::table('stock_balances as sb')->join('products as p', 'p.id', '=', 'sb.product_id')
            ->where('sb.company_id', $this->tenant->companyId())->where('p.tracking_type', '!=', 'roll')
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('sb.branch_id', $id))
            ->selectRaw('COALESCE(SUM(sb.quantity * sb.average_cost),0) value')->value('value');
        $rolls = DB::table('inventory_rolls')->where('company_id', $this->tenant->companyId())->whereNull('deleted_at')
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->selectRaw('COALESCE(SUM(remaining_area * unit_cost_per_area),0) value')->value('value');

        return bcadd((string) $stock, (string) $rolls, 4);
    }

    private function salesTax(array $filters): string
    {
        return bcadd((string) DB::table('sales_invoices as si')
            ->join('accounting_posting_links as apl', function ($join) {
                $join->on('apl.source_id', '=', 'si.id')->where('apl.source_type', '=', \App\Models\SalesInvoice::class)->where('apl.status', '=', 'posted');
            })->where('si.company_id', $this->tenant->companyId())
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('si.branch_id', $id))->sum('si.tax_amount'), '0', 4);
    }

    private function purchaseTax(array $filters): string
    {
        return bcadd((string) DB::table('supplier_invoices as si')
            ->join('accounting_posting_links as apl', function ($join) {
                $join->on('apl.source_id', '=', 'si.id')->where('apl.source_type', '=', \App\Models\SupplierInvoice::class)->where('apl.status', '=', 'posted');
            })->where('si.company_id', $this->tenant->companyId())
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('si.branch_id', $id))->sum('si.tax_amount'), '0', 4);
    }
}
