<?php

namespace App\Services;

use App\Models\AccountingClosingChecklist;
use App\Models\AccountingClosingRun;

class AccountingClosingChecklistService
{
    public const PERIOD_CODES = [
        'TRIAL_BALANCE_BALANCED', 'NO_UNPOSTED_JOURNALS', 'NO_UNPOSTED_SOURCES',
        'NO_MISSING_COST_CENTERS', 'NO_FAILED_POSTINGS',
    ];

    public const YEAR_CODES = [
        'TRIAL_BALANCE_BALANCED', 'NO_UNPOSTED_JOURNALS', 'NO_UNPOSTED_SOURCES',
        'AR_RECONCILED', 'AP_RECONCILED', 'INVENTORY_RECONCILED', 'VAT_RECONCILED',
        'NO_MISSING_COST_CENTERS', 'NO_FAILED_POSTINGS', 'ALL_PERIODS_CLOSED',
        'RETAINED_EARNINGS_CONFIGURED', 'NEXT_YEAR_READY',
    ];

    public function create(AccountingClosingRun $run): AccountingClosingChecklist
    {
        $codes = $run->accounting_period_id ? self::PERIOD_CODES : self::YEAR_CODES;
        $checklist = AccountingClosingChecklist::query()->firstOrCreate(
            ['closing_run_id' => $run->id],
            [
                'company_id' => $run->company_id, 'fiscal_year_id' => $run->fiscal_year_id,
                'accounting_period_id' => $run->accounting_period_id,
                'checklist_type' => $run->accounting_period_id ? 'period' : 'year_end',
                'created_by' => $run->started_by, 'total_items' => count($codes),
            ]
        );
        foreach ($codes as $index => $code) {
            $checklist->items()->firstOrCreate(['code' => $code], [
                'name_ar' => str_replace('_', ' ', $code), 'name_en' => str_replace('_', ' ', $code),
                'category' => $this->category($code), 'severity' => 'blocking',
                'is_required' => true, 'is_automated' => true, 'sort_order' => $index + 1,
            ]);
        }

        return $checklist->load('items');
    }

    private function category(string $code): string
    {
        return match (true) {
            str_contains($code, 'TRIAL') => 'trial_balance',
            str_contains($code, 'RECONCILED') => 'reconciliation',
            str_contains($code, 'JOURNAL') => 'journals',
            str_contains($code, 'SOURCE') || str_contains($code, 'POSTING') => 'documents',
            str_contains($code, 'COST_CENTER') => 'cost_centers',
            default => 'other',
        };
    }
}
