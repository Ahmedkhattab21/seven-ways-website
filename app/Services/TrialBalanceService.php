<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrialBalanceService
{
    public function __construct(
        private TenantContext $tenant,
        private FinancialReportQueryService $queries,
        private MoneyRoundingService $rounding
    ) {
    }

    public function report(array $filters): array
    {
        $filters = $this->queries->normalize($filters);
        $opening = $this->aggregate($filters, true);
        $period = $this->aggregate($filters, false);
        $rows = DB::table('accounts as a')
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->leftJoin('account_groups as ag', 'ag.id', '=', 'a.account_group_id')
            ->where('a.company_id', $this->tenant->companyId())->where('a.is_posting', true)
            ->when(! empty($filters['account_type_id']), fn ($q) => $q->where('a.account_type_id', $filters['account_type_id']))
            ->when(! empty($filters['account_group_id']), fn ($q) => $q->where('a.account_group_id', $filters['account_group_id']))
            ->select(['a.id', 'a.account_code', 'a.name_ar', 'a.normal_balance', 'a.account_path',
                'at.name_ar as type_name', 'ag.name_ar as group_name'])
            ->orderBy('a.account_code')->get()->map(function ($account) use ($opening, $period) {
                $open = $opening->get($account->id, ['debit' => '0.0000', 'credit' => '0.0000']);
                $move = $period->get($account->id, ['debit' => '0.0000', 'credit' => '0.0000']);
                $closingRaw = bcsub(bcadd($open['debit'], $move['debit'], 4), bcadd($open['credit'], $move['credit'], 4), 4);

                return (object) [
                    ...((array) $account), 'opening_debit' => $open['debit'], 'opening_credit' => $open['credit'],
                    'period_debit' => $move['debit'], 'period_credit' => $move['credit'],
                    'closing_debit' => bccomp($closingRaw, '0', 4) >= 0 ? $closingRaw : '0.0000',
                    'closing_credit' => bccomp($closingRaw, '0', 4) < 0 ? bcmul($closingRaw, '-1', 4) : '0.0000',
                    'is_header' => false,
                ];
            });
        $postingRows = $rows;
        if (empty($filters['include_zero'])) {
            $rows = $rows->filter(fn ($row) => bccomp($row->closing_debit, '0', 4) !== 0
                || bccomp($row->closing_credit, '0', 4) !== 0
                || bccomp($row->period_debit, '0', 4) !== 0 || bccomp($row->period_credit, '0', 4) !== 0)->values();
        }
        $totals = $this->totals($rows);
        if (! empty($filters['include_header'])) {
            $rows = $this->withHeaders($rows, $postingRows, $filters);
        }
        $summary = match ($filters['summary_by'] ?? 'account') {
            'group' => $this->summarize($postingRows, 'group_name'),
            'type' => $this->summarize($postingRows, 'type_name'),
            default => collect(),
        };

        return ['rows' => $rows, 'summary' => $summary, 'totals' => $totals,
            'balanced' => bccomp($totals['closing_debit'], $totals['closing_credit'], 4) === 0, 'filters' => $filters];
    }

    private function aggregate(array $filters, bool $before): Collection
    {
        return $this->queries->postedLines($filters, $before)->groupBy('jel.account_id')
            ->selectRaw('jel.account_id, COALESCE(SUM(jel.base_debit_amount),0) debit, COALESCE(SUM(jel.base_credit_amount),0) credit')
            ->get()->mapWithKeys(fn ($row) => [$row->account_id => [
                'debit' => $this->rounding->round((string) $row->debit, 4),
                'credit' => $this->rounding->round((string) $row->credit, 4),
            ]]);
    }

    private function totals(Collection $rows): array
    {
        $fields = ['opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'];

        return collect($fields)->mapWithKeys(fn ($field) => [$field => $rows->reduce(fn ($sum, $row) => bcadd($sum, $row->{$field}, 4), '0.0000')])->all();
    }

    private function withHeaders(Collection $visibleRows, Collection $postingRows, array $filters): Collection
    {
        $headers = DB::table('accounts as a')
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->leftJoin('account_groups as ag', 'ag.id', '=', 'a.account_group_id')
            ->where('a.company_id', $this->tenant->companyId())->where('a.is_header', true)
            ->when(! empty($filters['account_type_id']), fn ($q) => $q->where('a.account_type_id', $filters['account_type_id']))
            ->when(! empty($filters['account_group_id']), fn ($q) => $q->where('a.account_group_id', $filters['account_group_id']))
            ->select(['a.id', 'a.account_code', 'a.name_ar', 'a.normal_balance', 'a.account_path',
                'at.name_ar as type_name', 'ag.name_ar as group_name'])
            ->get()->map(function ($header) use ($postingRows) {
                $descendants = $postingRows->filter(fn ($row) => str_starts_with((string) $row->account_path, $header->account_path.'/'));

                return (object) [...((array) $header), ...$this->totals($descendants), 'is_header' => true];
            });
        if (empty($filters['include_zero'])) {
            $headers = $headers->filter(fn ($row) => bccomp($row->closing_debit, '0', 4) !== 0
                || bccomp($row->closing_credit, '0', 4) !== 0
                || bccomp($row->period_debit, '0', 4) !== 0 || bccomp($row->period_credit, '0', 4) !== 0);
        }

        return $visibleRows->concat($headers)->sortBy('account_code')->values();
    }

    private function summarize(Collection $rows, string $key): Collection
    {
        return $rows->groupBy(fn ($row) => $row->{$key} ?: 'Unassigned')
            ->map(fn ($items, $name) => (object) ['name' => $name, ...$this->totals($items)])->values();
    }
}
