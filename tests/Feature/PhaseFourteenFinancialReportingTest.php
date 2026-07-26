<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialReportDefinition;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\BalanceSheetService;
use App\Services\CashFlowStatementService;
use App\Services\ComparativeFinancialReportService;
use App\Services\FinancialReportExportService;
use App\Services\GeneralLedgerService;
use App\Services\IncomeStatementService;
use App\Services\TrialBalanceService;
use App\Services\UnpostedAccountingSourcesService;
use Database\Seeders\AccountingFoundationSeeder;
use Database\Seeders\AccountingPostingSeeder;
use Database\Seeders\FinancialReportingSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseFourteenFinancialReportingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reporting_schema_adds_indexes_and_never_stores_account_balances(): void
    {
        $this->assertTrue(Schema::hasTable('financial_report_definitions'));
        $this->assertTrue(Schema::hasTable('financial_report_sections'));
        $this->assertTrue(Schema::hasTable('financial_report_account_mappings'));
        $this->assertTrue(Schema::hasTable('cash_flow_mappings'));
        $this->assertFalse(Schema::hasColumn('accounts', 'balance'));
    }

    public function test_general_ledger_uses_posted_only_and_calculates_opening_movement_closing_and_running(): void
    {
        $context = $this->context();
        $cash = $this->account($context, '111000');
        $capital = $this->account($context, '310000');
        $expense = $this->account($context, '640000');
        $this->journal($context, '2038-02-28', 'posted', [[$cash, 1000, 0], [$capital, 0, 1000]]);
        $this->journal($context, '2038-03-05', 'posted', [[$expense, 10, 0], [$cash, 0, 10]]);
        $this->journal($context, '2038-03-06', 'draft', [[$cash, 999, 0], [$capital, 0, 999]]);

        $report = app(GeneralLedgerService::class)->report([
            'account_id' => $cash->id, 'date_from' => '2038-03-01', 'date_to' => '2038-03-31',
        ]);

        $this->assertSame('1000.0000', $report['summary']['opening_net']);
        $this->assertSame('10.0000', $report['summary']['period_credit']);
        $this->assertSame('990.0000', $report['summary']['closing_net']);
        $this->assertCount(1, $report['lines']);
        $this->assertEquals('990.0000', $report['lines']->first()->running_balance);
    }

    public function test_original_and_reversal_are_both_visible_and_net_to_zero(): void
    {
        $context = $this->context();
        $cash = $this->account($context, '111000');
        $capital = $this->account($context, '310000');
        $original = $this->journal($context, '2038-03-02', 'posted', [[$cash, 50, 0], [$capital, 0, 50]]);
        $reversal = $this->journal($context, '2038-03-03', 'posted', [[$cash, 0, 50], [$capital, 50, 0]], true);
        $original->forceFill(['reversed_by_entry_id' => $reversal->id, 'reversed_at' => now()])->save();

        $report = app(GeneralLedgerService::class)->report([
            'account_id' => $cash->id, 'date_from' => '2038-03-01', 'date_to' => '2038-03-31',
        ]);
        $this->assertCount(2, $report['lines']);
        $this->assertSame('0.0000', $report['summary']['closing_net']);
    }

    public function test_trial_balance_is_balanced_and_adjusted_view_is_foundation_only(): void
    {
        $context = $this->context();
        $this->financialFixture($context);
        $trial = app(TrialBalanceService::class)->report([
            'date_from' => '2038-03-01', 'date_to' => '2038-03-31', 'include_zero' => true,
        ]);
        $this->assertTrue($trial['balanced']);
        $this->assertSame($trial['totals']['period_debit'], $trial['totals']['period_credit']);
        $withHeaders = app(TrialBalanceService::class)->report([
            'date_from' => '2038-03-01', 'date_to' => '2038-03-31', 'include_zero' => true, 'include_header' => true,
        ]);
        $header = $withHeaders['rows']->firstWhere('is_header', true);
        $descendants = $withHeaders['rows']->filter(fn ($row) => ! $row->is_header
            && str_starts_with($row->account_path, $header->account_path.'/'));
        $this->assertNotNull($header);
        $this->assertSame(
            $descendants->reduce(fn ($sum, $row) => bcadd($sum, $row->closing_debit, 4), '0.0000'),
            $header->closing_debit
        );
        $this->assertSame($trial['totals'], $withHeaders['totals']);
        $unadjusted = app(TrialBalanceService::class)->report([
            'date_from' => '2038-03-01', 'date_to' => '2038-03-31', 'view_type' => 'unadjusted',
        ]);
        $this->assertSame($trial['totals']['period_debit'], $unadjusted['totals']['period_debit']);
    }

    public function test_income_statement_balance_sheet_and_cash_flow_reconcile_without_closing_entry(): void
    {
        $context = $this->context();
        $this->financialFixture($context);
        $filters = ['date_from' => '2038-03-01', 'date_to' => '2038-03-31'];
        $income = app(IncomeStatementService::class)->report($filters);
        $this->assertSame('100.0000', $income['revenue']);
        $this->assertSame('40.0000', $income['cost_of_sales']);
        $this->assertSame('50.0000', $income['net_profit']);
        $balance = app(BalanceSheetService::class)->report($filters);
        $this->assertTrue($balance['balanced']);
        $this->assertSame('50.0000', $balance['current_profit']);
        $cash = app(CashFlowStatementService::class)->report($filters);
        $this->assertSame('1000.0000', $cash['opening_cash']);
        $this->assertSame('-10.0000', $cash['operating']);
        $this->assertSame('-10.0000', $cash['net_change']);
        $this->assertSame('990.0000', $cash['closing_cash']);
        $this->assertSame(0, JournalEntry::query()->where('entry_type', 'closing')->count());
    }

    public function test_unposted_sources_excludes_posted_links_and_seeder_is_idempotent(): void
    {
        $context = $this->context();
        $customer = \App\Models\Customer::factory()->create([
            'company_id' => $context['company']->id, 'created_branch_id' => $context['branch']->id,
            'assigned_branch_id' => $context['branch']->id,
        ]);
        $invoice = SalesInvoice::factory()->create([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'customer_id' => $customer->id, 'currency_id' => $context['currency']->id,
            'status' => 'issued', 'invoice_date' => '2038-03-10', 'created_by' => $context['user']->id,
        ]);
        $this->assertTrue(app(UnpostedAccountingSourcesService::class)->report()->contains('source_id', $invoice->id));
        DB::table('accounting_posting_links')->insert([
            'uuid' => fake()->uuid(), 'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'source_type' => SalesInvoice::class, 'source_id' => $invoice->id, 'source_uuid' => $invoice->uuid,
            'posting_action' => 'post', 'idempotency_key' => fake()->sha256(), 'status' => 'posted',
            'created_by' => $context['user']->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertFalse(app(UnpostedAccountingSourcesService::class)->report()->contains('source_id', $invoice->id));
        $count = FinancialReportDefinition::query()->where('company_id', $context['company']->id)->count();
        app(FinancialReportingSeeder::class)->run();
        $this->assertSame($count, FinancialReportDefinition::query()->where('company_id', $context['company']->id)->count());
    }

    public function test_csv_formula_injection_is_neutralized(): void
    {
        $export = app(FinancialReportExportService::class);
        $this->assertSame("'=SUM(A1:A2)", $export->safe('=SUM(A1:A2)'));
        $this->assertSame("'@cmd", $export->safe('@cmd'));
        $this->assertSame('Normal', $export->safe('Normal'));
    }

    public function test_comparative_report_calculates_period_difference_and_handles_zero_base(): void
    {
        $service = app(ComparativeFinancialReportService::class);
        $filters = $service->previousFilters([
            'date_from' => '2038-03-01', 'date_to' => '2038-03-31',
        ], 'previous_period');
        $this->assertSame('2038-02-01', $filters['date_from']);
        $this->assertSame('2038-02-28', $filters['date_to']);
        $comparison = $service->compare(['net_profit' => '50.0000'], ['net_profit' => '0.0000'], ['net_profit']);
        $this->assertSame('50.0000', $comparison['net_profit']['difference']);
        $this->assertNull($comparison['net_profit']['percentage']);
    }

    private function context(): array
    {
        $currency = Currency::query()->create([
            'code' => strtoupper(substr(hash('sha1', uniqid('', true)), 0, 3)), 'name_ar' => 'عملة',
            'name_en' => 'Currency', 'symbol' => 'C', 'decimal_places' => 2, 'is_active' => true,
        ]);
        $company = Company::query()->create(['name' => 'Reporting '.uniqid(), 'currency_id' => $currency->id]);
        $branch = Branch::query()->create(['company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main', 'is_main' => true, 'is_active' => true]);
        $role = Role::query()->create(['company_id' => $company->id, 'name' => 'company_owner', 'display_name' => 'Owner', 'scope' => 'company', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);
        app(AccountingFoundationSeeder::class)->run();
        app(AccountingPostingSeeder::class)->run();
        app(FinancialReportingSeeder::class)->run();
        $year = FiscalYear::factory()->create([
            'company_id' => $company->id, 'code' => 'FY-2038', 'name' => 'FY 2038',
            'start_date' => '2038-01-01', 'end_date' => '2038-12-31', 'status' => 'open', 'created_by' => $user->id,
        ]);
        $period = AccountingPeriod::factory()->create([
            'company_id' => $company->id, 'fiscal_year_id' => $year->id, 'period_number' => 3,
            'code' => '2038-03', 'name' => 'March', 'start_date' => '2038-03-01', 'end_date' => '2038-03-31', 'status' => 'open',
        ]);
        $this->actingAs($user);
        app(TenantContext::class)->initialize($user);

        return compact('currency', 'company', 'branch', 'user', 'year', 'period');
    }

    private function financialFixture(array $context): void
    {
        $this->journal($context, '2038-02-28', 'posted', [
            [$this->account($context, '111000'), 1000, 0], [$this->account($context, '310000'), 0, 1000],
        ]);
        $this->journal($context, '2038-03-05', 'posted', [
            [$this->account($context, '113000'), 115, 0], [$this->account($context, '410000'), 0, 100],
            [$this->account($context, '212000'), 0, 15],
        ]);
        $this->journal($context, '2038-03-06', 'posted', [
            [$this->account($context, '520000'), 40, 0], [$this->account($context, '114000'), 0, 40],
        ]);
        $this->journal($context, '2038-03-07', 'posted', [
            [$this->account($context, '640000'), 10, 0], [$this->account($context, '111000'), 0, 10],
        ]);
    }

    private function journal(array $context, string $date, string $status, array $lines, bool $reversal = false): JournalEntry
    {
        $debit = collect($lines)->reduce(fn ($sum, $line) => bcadd($sum, (string) $line[1], 4), '0.0000');
        $credit = collect($lines)->reduce(fn ($sum, $line) => bcadd($sum, (string) $line[2], 4), '0.0000');
        $entry = JournalEntry::query()->forceCreate([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'fiscal_year_id' => $context['year']->id, 'accounting_period_id' => $context['period']->id,
            'journal_number' => 'TEST-'.fake()->unique()->numerify('######'), 'entry_type' => $reversal ? 'reversal' : 'manual',
            'status' => $status, 'entry_date' => $date, 'posting_date' => $date,
            'currency_id' => $context['currency']->id, 'exchange_rate' => 1, 'description' => 'Report fixture',
            'total_debit' => $debit, 'total_credit' => $credit, 'base_total_debit' => $debit, 'base_total_credit' => $credit,
            'is_automatic' => false, 'is_reversal' => $reversal, 'is_opening' => false, 'is_adjusting' => false,
            'created_by' => $context['user']->id, 'posted_by' => $status === 'posted' ? $context['user']->id : null,
            'posted_at' => $status === 'posted' ? now() : null,
        ]);
        foreach ($lines as $index => [$account, $lineDebit, $lineCredit]) {
            $entry->lines()->create([
                'line_number' => $index + 1, 'account_id' => $account->id, 'branch_id' => $context['branch']->id,
                'currency_id' => $context['currency']->id, 'exchange_rate' => 1,
                'debit_amount' => $lineDebit, 'credit_amount' => $lineCredit,
                'base_debit_amount' => $lineDebit, 'base_credit_amount' => $lineCredit,
            ]);
        }

        return $entry;
    }

    private function account(array $context, string $code): Account
    {
        return Account::query()->where('company_id', $context['company']->id)->where('account_code', $code)->firstOrFail();
    }
}
