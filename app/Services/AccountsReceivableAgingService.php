<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\SalesInvoice;
use Carbon\Carbon;

class AccountsReceivableAgingService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function report(?int $branchId = null, ?int $currencyId = null, ?Carbon $asOf = null): array
    {
        $asOf ??= today();
        $query = SalesInvoice::where('company_id', $this->tenant->companyId())
            ->whereIn('branch_id', $this->tenant->accessibleBranches()->pluck('id'))
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])->where('balance_due', '>', 0);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        if ($currencyId) {
            $query->where('currency_id', $currencyId);
        }
        $groups = [];
        foreach ($query->with('customer')->get() as $invoice) {
            $days = $invoice->due_date ? $invoice->due_date->diffInDays($asOf, false) : 0;
            $bucket = $days <= 0 ? 'current' : ($days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : '90_plus')));
            $key = (string) $invoice->currency_id;
            $groups[$key] ??= ['current' => '0.0000', '1_30' => '0.0000', '31_60' => '0.0000', '61_90' => '0.0000', '90_plus' => '0.0000'];
            $groups[$key][$bucket] = bcadd($groups[$key][$bucket], $invoice->balance_due, 4);
        }

        return $groups;
    }
}
