<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

class GeneralLedgerService
{
    public function __construct(
        private TenantContext $tenant,
        private FinancialReportQueryService $queries,
        private AccountBalanceService $balances
    ) {
    }

    public function report(array $filters, int $perPage = 50): array
    {
        $filters = $this->queries->normalize($filters);
        if (empty($filters['account_id'])) {
            throw new BusinessRuleException('General ledger requires an account.');
        }
        $account = Account::query()->where('company_id', $this->tenant->companyId())
            ->with(['type', 'group'])->findOrFail($filters['account_id']);
        $summary = $this->balances->calculate($account, $filters);
        $openingRaw = bcsub($summary['opening_debit'], $summary['opening_credit'], 4);
        $query = $this->queries->postedLines($filters)
            ->join('accounts as a', 'a.id', '=', 'jel.account_id')
            ->leftJoin('branches as b', 'b.id', '=', DB::raw('COALESCE(jel.branch_id, je.branch_id)'))
            ->leftJoin('cost_centers as cc', 'cc.id', '=', 'jel.cost_center_id')
            ->select([
                'jel.id', 'je.id as journal_entry_id', 'je.journal_number', 'je.posting_date',
                'je.source_type', 'je.source_number', 'jel.description', 'jel.debit_amount',
                'jel.credit_amount', 'jel.base_debit_amount', 'jel.base_credit_amount',
                'jel.currency_id', 'jel.exchange_rate', 'b.name as branch_name', 'cc.name_ar as cost_center_name',
            ])
            ->selectRaw(
                '? + SUM(jel.base_debit_amount - jel.base_credit_amount) OVER (ORDER BY je.posting_date, je.id, jel.line_number) AS running_balance',
                [$openingRaw]
            )
            ->orderBy('je.posting_date')->orderBy('je.id')->orderBy('jel.line_number');

        return ['account' => $account, 'summary' => $summary, 'lines' => $query->paginate(min(max($perPage, 1), 200)), 'filters' => $filters];
    }

    public function export(array $filters): iterable
    {
        return $this->report($filters, 200)['lines']->items();
    }
}
