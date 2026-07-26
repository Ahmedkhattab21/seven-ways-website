<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\CostCenter;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FinancialReportQueryService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function normalize(array $filters): array
    {
        $companyId = $this->tenant->companyId();
        if (! $companyId) {
            throw new BusinessRuleException('A tenant company is required.');
        }
        if (! empty($filters['fiscal_year_id'])) {
            $year = FiscalYear::query()->where('company_id', $companyId)->findOrFail($filters['fiscal_year_id']);
            $filters['date_from'] ??= $year->start_date->toDateString();
            $filters['date_to'] ??= $year->end_date->toDateString();
        }
        if (! empty($filters['accounting_period_id'])) {
            $period = AccountingPeriod::query()->where('company_id', $companyId)->findOrFail($filters['accounting_period_id']);
            $filters['date_from'] = $period->start_date->toDateString();
            $filters['date_to'] = $period->end_date->toDateString();
        }
        $filters['date_from'] ??= now()->startOfMonth()->toDateString();
        $filters['date_to'] ??= now()->toDateString();
        if (Carbon::parse($filters['date_from'])->gt(Carbon::parse($filters['date_to']))) {
            throw new BusinessRuleException('Report start date must not be after end date.');
        }
        if (! empty($filters['account_id'])) {
            Account::query()->where('company_id', $companyId)->findOrFail($filters['account_id']);
        }
        if (! empty($filters['cost_center_id'])) {
            CostCenter::query()->where('company_id', $companyId)->findOrFail($filters['cost_center_id']);
        }
        $accessible = $this->tenant->accessibleBranches()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (! empty($filters['branch_id']) && ! in_array((int) $filters['branch_id'], $accessible, true)) {
            throw new BusinessRuleException('Report branch is outside the accessible scope.');
        }
        $filters['accessible_branch_ids'] = $accessible;

        return $filters;
    }

    public function postedLines(array $filters, bool $beforePeriod = false): Builder
    {
        $filters = $this->normalize($filters);
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.company_id', $this->tenant->companyId())
            ->where('je.status', 'posted');
        if ($beforePeriod) {
            $query->whereDate('je.posting_date', '<', $filters['date_from']);
        } else {
            $query->whereBetween('je.posting_date', [$filters['date_from'], $filters['date_to']]);
        }

        return $this->dimensions($query, $filters);
    }

    public function dimensions(Builder $query, array $filters): Builder
    {
        foreach (['account_id', 'cost_center_id', 'customer_id', 'supplier_id', 'employee_id',
            'vehicle_id', 'product_id', 'warehouse_id', 'currency_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where('jel.'.$field, $filters[$field]);
            }
        }
        if (! empty($filters['account_group_id'])) {
            $query->join('accounts as filter_accounts', 'filter_accounts.id', '=', 'jel.account_id')
                ->where('filter_accounts.account_group_id', $filters['account_group_id']);
        }
        if (! empty($filters['account_type_id'])) {
            if (! str_contains(implode(',', $query->joins ? array_map(fn ($join) => $join->table, $query->joins) : []), 'filter_accounts')) {
                $query->join('accounts as filter_accounts', 'filter_accounts.id', '=', 'jel.account_id');
            }
            $query->where('filter_accounts.account_type_id', $filters['account_type_id']);
        }
        if (! empty($filters['branch_id'])) {
            $query->whereRaw('COALESCE(jel.branch_id, je.branch_id) = ?', [$filters['branch_id']]);
        } elseif (! empty($filters['accessible_branch_ids'])) {
            $query->where(function ($scope) use ($filters) {
                $scope->whereNull('jel.branch_id')
                    ->whereNull('je.branch_id')
                    ->orWhereIn(DB::raw('COALESCE(jel.branch_id, je.branch_id)'), $filters['accessible_branch_ids']);
            });
        }
        if (! empty($filters['source_type'])) {
            $query->where('je.source_type', $filters['source_type']);
        }
        if (($filters['view_type'] ?? 'adjusted') === 'unadjusted') {
            $query->where('je.is_adjusting', false);
        }

        return $query;
    }
}
