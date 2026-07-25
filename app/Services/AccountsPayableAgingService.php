<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\SupplierInvoice;

class AccountsPayableAgingService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function report(?int $branchId = null, ?int $currencyId = null): array
    {
        $branches = $this->tenant->accessibleBranches()->pluck('id');
        if ($branchId) {
            abort_unless($branches->contains($branchId), 403);
            $branches = collect([$branchId]);
        }
        $result = [];
        $invoices = SupplierInvoice::where('company_id', $this->tenant->companyId())
            ->whereIn('branch_id', $branches)
            ->whereIn('status', ['posted', 'partially_paid', 'overdue'])
            ->where('balance_due', '>', 0)
            ->when($currencyId, fn ($query) => $query->where('currency_id', $currencyId))
            ->get();
        foreach ($invoices as $invoice) {
            $age = $invoice->due_date ? max(0, $invoice->due_date->diffInDays(today(), false)) : 0;
            $bucket = match (true) {
                $age <= 0 => 'current', $age <= 30 => '1_30', $age <= 60 => '31_60',
                $age <= 90 => '61_90', default => '90_plus',
            };
            $result[$invoice->currency_id] ??= array_fill_keys(['current', '1_30', '31_60', '61_90', '90_plus'], '0.0000');
            $result[$invoice->currency_id][$bucket] = bcadd(
                $result[$invoice->currency_id][$bucket],
                $invoice->balance_due,
                4
            );
        }

        return $result;
    }
}
