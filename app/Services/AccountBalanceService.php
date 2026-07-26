<?php

namespace App\Services;

use App\Models\Account;

class AccountBalanceService
{
    public function __construct(
        private FinancialReportQueryService $queries,
        private MoneyRoundingService $rounding
    ) {
    }

    public function calculate(Account $account, array $filters): array
    {
        $opening = $this->aggregate($this->queries->postedLines($filters + ['account_id' => $account->id], true));
        $period = $this->aggregate($this->queries->postedLines($filters + ['account_id' => $account->id]));
        $closingDebit = bcadd($opening['debit'], $period['debit'], 4);
        $closingCredit = bcadd($opening['credit'], $period['credit'], 4);
        $normalCredit = $account->normal_balance === 'credit';

        return [
            'opening_debit' => $opening['debit'], 'opening_credit' => $opening['credit'],
            'opening_net' => $this->net($opening['debit'], $opening['credit'], $normalCredit),
            'period_debit' => $period['debit'], 'period_credit' => $period['credit'],
            'period_net' => $this->net($period['debit'], $period['credit'], $normalCredit),
            'closing_debit' => $closingDebit, 'closing_credit' => $closingCredit,
            'closing_net' => $this->net($closingDebit, $closingCredit, $normalCredit),
            'normal_balance_side' => $account->normal_balance,
            'display_balance' => $this->net($closingDebit, $closingCredit, $normalCredit),
        ];
    }

    public function rawNet(Account $account, array $filters, bool $before = false): string
    {
        $amounts = $this->aggregate($this->queries->postedLines($filters + ['account_id' => $account->id], $before));

        return bcsub($amounts['debit'], $amounts['credit'], 4);
    }

    private function aggregate($query): array
    {
        $row = $query->selectRaw(
            'COALESCE(SUM(jel.base_debit_amount), 0) debit, COALESCE(SUM(jel.base_credit_amount), 0) credit'
        )->first();

        return [
            'debit' => $this->rounding->round((string) $row->debit, 4),
            'credit' => $this->rounding->round((string) $row->credit, 4),
        ];
    }

    private function net(string $debit, string $credit, bool $normalCredit): string
    {
        return $normalCredit ? bcsub($credit, $debit, 4) : bcsub($debit, $credit, 4);
    }
}
