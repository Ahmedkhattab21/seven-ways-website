<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class CashFlowStatementService
{
    public function __construct(
        private TenantContext $tenant,
        private FinancialReportQueryService $queries
    ) {
    }

    public function report(array $filters): array
    {
        $filters = $this->queries->normalize($filters);
        $cashAccounts = DB::table('accounts')->where('company_id', $this->tenant->companyId())
            ->where(fn ($q) => $q->where('is_cash_account', true)->orWhere('is_bank_account', true))->pluck('id');
        $opening = $this->cashTotal($filters, $cashAccounts->all(), true);
        $movement = $this->cashTotal($filters, $cashAccounts->all(), false);
        $closing = bcadd($opening, $movement, 4);
        $categories = $this->categorize($filters, $cashAccounts->all());
        $classified = bcadd(bcadd($categories['operating'], $categories['investing'], 4), $categories['financing'], 4);
        $categories['unclassified'] = bcsub($movement, $classified, 4);

        return [
            ...$categories,
            'net_change' => $movement, 'opening_cash' => $opening, 'closing_cash' => $closing,
            'reconciled' => bccomp(bcadd($classified, $categories['unclassified'], 4), $movement, 4) === 0,
            'warning' => bccomp($categories['unclassified'], '0', 4) === 0
                ? null : 'Cash flow mappings are incomplete or ambiguous; movements are shown as Unclassified.',
            'method' => 'direct_foundation',
        ];
    }

    private function cashTotal(array $filters, array $accountIds, bool $before): string
    {
        if ($accountIds === []) {
            return '0.0000';
        }
        $row = $this->queries->postedLines($filters, $before)->whereIn('jel.account_id', $accountIds)
            ->selectRaw('COALESCE(SUM(jel.base_debit_amount - jel.base_credit_amount),0) amount')->first();

        return bcadd((string) $row->amount, '0', 4);
    }

    private function categorize(array $filters, array $cashAccountIds): array
    {
        $totals = ['operating' => '0.0000', 'investing' => '0.0000', 'financing' => '0.0000'];
        if ($cashAccountIds === []) {
            return [...$totals, 'unclassified' => '0.0000'];
        }
        $cashByJournal = $this->queries->postedLines($filters)
            ->whereIn('jel.account_id', $cashAccountIds)->groupBy('jel.journal_entry_id')
            ->selectRaw('jel.journal_entry_id, SUM(jel.base_debit_amount - jel.base_credit_amount) amount')
            ->get()->keyBy('journal_entry_id');
        $mappings = DB::table('cash_flow_mappings')->where('company_id', $this->tenant->companyId())
            ->where('is_active', true)->get();
        $accountMappings = $mappings->whereNotNull('account_id')->keyBy('account_id');
        $groupMappings = $mappings->whereNotNull('account_group_id')->keyBy('account_group_id');
        $counterparts = $this->queries->postedLines($filters)
            ->join('accounts as cash_flow_accounts', 'cash_flow_accounts.id', '=', 'jel.account_id')
            ->whereNotIn('jel.account_id', $cashAccountIds)
            ->whereIn('jel.journal_entry_id', $cashByJournal->keys())
            ->select(['jel.journal_entry_id', 'jel.account_id', 'cash_flow_accounts.account_group_id'])->get()
            ->groupBy('journal_entry_id');
        foreach ($cashByJournal as $journalId => $cash) {
            $categories = $counterparts->get($journalId, collect())->map(function ($line) use ($accountMappings, $groupMappings) {
                return $accountMappings->get($line->account_id)?->cash_flow_category
                    ?? $groupMappings->get($line->account_group_id)?->cash_flow_category;
            })->filter()->unique();
            if ($categories->count() === 1 && isset($totals[$categories->first()])) {
                $category = $categories->first();
                $totals[$category] = bcadd($totals[$category], (string) $cash->amount, 4);
            }
        }

        return [...$totals, 'unclassified' => '0.0000'];
    }
}
