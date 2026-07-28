<?php

namespace Tests\Feature;

use App\Analytics\ReportFilterData;
use App\Core\Tenancy\TenantContext;
use App\Services\AnalyticsReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsAnalyticsContext;
use Tests\TestCase;

class PhaseNineteenFinancialReportsTest extends TestCase
{
    use BuildsAnalyticsContext;
    use DatabaseTransactions;

    public function test_financial_summary_uses_posted_balanced_journals_only(): void
    {
        $context = $this->analyticsContext();
        $cash = $this->treasuryAccount($context, '111000');
        $capital = $this->treasuryAccount($context, '310000');
        $this->analyticsJournal($context, '2040-01-05', 'posted', [[$cash, 100, 0], [$capital, 0, 100]]);
        $this->analyticsJournal($context, '2040-01-06', 'draft', [[$cash, 999, 0], [$capital, 0, 999]]);
        $report = app(AnalyticsReportService::class)->run('financial', $this->filters($context));

        $this->assertSame('100.0000', $report->summary['period_debit']);
        $this->assertSame('100.0000', $report->summary['period_credit']);
        $this->assertTrue($report->summary['trial_balance_balanced']);
        $this->assertTrue($report->summary['balance_sheet_balanced']);
        $this->assertCount(1, $report->rows);
    }

    public function test_treasury_position_is_derived_from_posted_ledger_not_stored_columns(): void
    {
        $context = $this->analyticsContext();
        $cash = $this->treasuryAccount($context, '111000');
        $capital = $this->treasuryAccount($context, '310000');
        $this->analyticsJournal($context, '2040-01-05', 'posted', [[$cash, 250, 0], [$capital, 0, 250]]);
        $report = app(AnalyticsReportService::class)->run('treasury', $this->filters($context));

        $this->assertSame('250.0000', $report->summary['cash_book_balance']);
        $this->assertStringContainsString('general-ledger', $report->meta['data_source']);
    }

    public function test_existing_official_financial_routes_remain_available(): void
    {
        $context = $this->analyticsContext();
        foreach ([
            'accounting.reports.trial-balance',
            'accounting.reports.general-ledger',
            'accounting.reports.income-statement',
            'accounting.reports.balance-sheet',
            'accounting.reports.cash-flow',
        ] as $name) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($name));
        }
        $this->get(route('analytics.reports.show', [
            'financial', 'date_from' => '2040-01-01', 'date_to' => '2040-01-31',
        ]))->assertOk();
    }

    private function filters(array $context): ReportFilterData
    {
        return ReportFilterData::from([
            'branch_id' => $context['branch']->id,
            'currency_id' => $context['currency']->id,
            'date_from' => '2040-01-01',
            'date_to' => '2040-01-31',
        ], app(TenantContext::class));
    }
}
