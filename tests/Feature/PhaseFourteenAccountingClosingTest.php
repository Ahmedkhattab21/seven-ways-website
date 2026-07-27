<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\AccountingClosingRun;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingAdjustmentService;
use App\Services\AccountingClosingExceptionService;
use App\Services\AccountingModuleLockService;
use App\Services\AccountingPeriodClosingService;
use App\Services\AccountingReopenService;
use App\Services\FiscalPeriodGenerationService;
use App\Services\ScheduledJournalReversalService;
use App\Services\YearEndClosingService;
use Database\Seeders\AccountingClosingSeeder;
use Database\Seeders\AccountingFoundationSeeder;
use Database\Seeders\AccountingPostingSeeder;
use Database\Seeders\FinancialReportingSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseFourteenAccountingClosingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_closing_schema_does_not_add_stored_account_balances(): void
    {
        foreach (['accounting_closing_runs', 'accounting_closing_checklists', 'accounting_closing_checklist_items',
            'accounting_closing_exceptions', 'accounting_adjustments', 'scheduled_journal_reversals',
            'year_end_closing_settings'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertFalse(Schema::hasColumn('accounts', 'balance'));
    }

    public function test_soft_and_hard_close_require_validation_and_separate_actors_without_journals(): void
    {
        $context = $this->context();
        $service = app(AccountingPeriodClosingService::class);
        $before = JournalEntry::query()->count();
        $run = $service->start($context['period'], 'period_soft_close', 'Monthly close review');
        $this->assertSame('ready_for_review', $run->status);
        $this->switchActor($context['reviewer']);
        $service->review($run, 'Reviewed');
        $this->switchActor($context['approver']);
        $service->approve($run->fresh(), 'Approved');
        $this->assertSame('soft_closed', $context['period']->fresh()->status);
        $this->assertSame($before, JournalEntry::query()->count());

        $this->switchActor($context['user']);
        $hard = $service->start($context['period']->fresh(), 'period_hard_close', 'Final monthly close');
        $this->switchActor($context['reviewer']);
        $service->review($hard, 'Reviewed');
        $this->switchActor($context['approver']);
        $service->approve($hard->fresh(), 'Approved');
        $this->assertSame('closed', $context['period']->fresh()->status);
        $this->assertSame($before, JournalEntry::query()->count());
        $this->assertSame(2, AccountingClosingRun::query()->where('accounting_period_id', $context['period']->id)->count());
    }

    public function test_module_lock_is_enforced_by_adjustment_backend(): void
    {
        $context = $this->context();
        app(AccountingModuleLockService::class)->update($context['period'], ['adjustments'], 'Adjustment review lock');

        $this->expectException(BusinessRuleException::class);
        app(AccountingAdjustmentService::class)->create($this->adjustmentData($context));
    }

    public function test_blocking_exception_needs_independent_waiver_and_updates_checklist(): void
    {
        $context = $this->context();
        JournalEntry::factory()->create([
            'company_id' => $context['company']->id,
            'fiscal_year_id' => $context['year']->id,
            'accounting_period_id' => $context['period']->id,
            'currency_id' => $context['currency']->id,
            'status' => 'draft',
            'entry_date' => '2039-03-10',
            'created_by' => $context['user']->id,
        ]);
        $run = app(AccountingPeriodClosingService::class)
            ->start($context['period'], 'period_soft_close', 'Close with reviewed exception');
        $exception = $run->checklist->items->firstWhere('code', 'NO_UNPOSTED_JOURNALS');
        $closingException = \App\Models\AccountingClosingException::query()
            ->where('closing_run_id', $run->id)->where('exception_type', $exception->code)->firstOrFail();

        try {
            app(AccountingClosingExceptionService::class)->action($closingException, 'waive', 'Self waiver denied');
            $this->fail('Exception owner waived their own blocking exception.');
        } catch (BusinessRuleException) {
            $this->assertSame('open', $closingException->fresh()->status);
        }

        $this->switchActor($context['reviewer']);
        app(AccountingClosingExceptionService::class)->action($closingException, 'waive', 'Independent approved waiver');
        $this->assertSame('waived', $closingException->fresh()->status);
        $this->assertSame('waived', $exception->fresh()->status);
    }

    public function test_adjustment_posts_once_and_scheduled_reversal_is_exact_and_idempotent(): void
    {
        $context = $this->context();
        $adjustment = app(AccountingAdjustmentService::class)->create($this->adjustmentData($context));
        app(AccountingAdjustmentService::class)->action($adjustment, 'submit');
        $this->switchActor($context['reviewer']);
        app(AccountingAdjustmentService::class)->action($adjustment->fresh(), 'approve');
        $this->switchActor($context['approver']);
        $adjustment = app(AccountingAdjustmentService::class)->action($adjustment->fresh(), 'post');
        $this->assertSame('posted', $adjustment->status);
        $this->assertTrue($adjustment->journalEntry->is_adjusting);

        $scheduler = app(ScheduledJournalReversalService::class);
        $scheduled = $scheduler->schedule($adjustment->journalEntry, '2039-03-20');
        $this->assertSame($scheduled->id, $scheduler->schedule($adjustment->journalEntry, '2039-03-20')->id);
        $processed = $scheduler->process($scheduled);
        $this->assertSame('processed', $processed->status);
        $this->assertSame(
            $adjustment->journalEntry->base_total_debit,
            $processed->fresh()->originalJournal->fresh()->base_total_debit
        );
        $this->assertSame($processed->id, $scheduler->process($processed)->id);
        $this->assertSame(1, JournalEntry::query()->where('reversal_of_id', $adjustment->journal_entry_id)->count());
    }

    public function test_year_end_requires_separate_starter_reviewer_approver_and_executor(): void
    {
        $context = $this->context();
        $run = AccountingClosingRun::factory()->create([
            'company_id' => $context['company']->id,
            'fiscal_year_id' => $context['year']->id,
            'accounting_period_id' => null,
            'closing_type' => 'year_end_close',
            'status' => 'ready_for_review',
            'started_by' => $context['user']->id,
        ]);
        $service = app(YearEndClosingService::class);

        try {
            $service->review($run, 'Self review');
            $this->fail('Starter was able to review the same year-end run.');
        } catch (BusinessRuleException) {
            $this->assertSame('ready_for_review', $run->fresh()->status);
        }

        $this->switchActor($context['reviewer']);
        $service->review($run->fresh(), 'Independent review');
        $this->switchActor($context['approver']);
        $service->approve($run->fresh(), 'Independent approval');

        $this->expectException(BusinessRuleException::class);
        $service->execute($run->fresh());
    }

    public function test_year_end_closes_profit_and_carries_balance_sheet_forward_once(): void
    {
        $context = $this->context();
        AccountingPeriod::query()->where('fiscal_year_id', $context['year']->id)->update(['status' => 'closed']);
        $context['year']->forceFill(['status' => 'soft_closed'])->save();
        $entry = new JournalEntry;
        $entry->forceFill([
            'company_id' => $context['company']->id,
            'fiscal_year_id' => $context['year']->id,
            'accounting_period_id' => $context['period']->id,
            'journal_number' => 'YE-'.uniqid(),
            'entry_type' => 'manual',
            'status' => 'posted',
            'entry_date' => '2039-12-31',
            'posting_date' => '2039-12-31',
            'currency_id' => $context['currency']->id,
            'exchange_rate' => 1,
            'description' => 'Year-end test activity',
            'total_debit' => 140,
            'total_credit' => 140,
            'base_total_debit' => 140,
            'base_total_credit' => 140,
            'created_by' => $context['user']->id,
            'posted_by' => $context['user']->id,
            'posted_at' => now(),
        ])->save();
        foreach ([
            ['111000', 100, 0],
            ['410000', 0, 100],
            ['640000', 40, 0],
            ['111000', 0, 40],
        ] as $index => [$code, $debit, $credit]) {
            $entry->lines()->create([
                'line_number' => $index + 1,
                'account_id' => $this->account($context, $code)->id,
                'currency_id' => $context['currency']->id,
                'exchange_rate' => 1,
                'debit_amount' => $debit,
                'credit_amount' => $credit,
                'base_debit_amount' => $debit,
                'base_credit_amount' => $credit,
            ]);
        }

        $service = app(YearEndClosingService::class);
        $run = $service->start($context['year']->fresh(), 'Annual statutory close');
        $this->assertSame('ready_for_review', $run->status);
        $this->switchActor($context['reviewer']);
        $service->review($run, 'Reviewed');
        $this->switchActor($context['approver']);
        $service->approve($run->fresh(), 'Approved');
        $this->switchActor($context['executor']);
        $completed = $service->execute($run->fresh());

        $this->assertSame('completed', $completed->status);
        $this->assertSame('closed', $context['year']->fresh()->status);
        $this->assertSame(4, JournalEntry::query()->where('closing_run_id', $run->id)->count());
        $this->assertSame(1, JournalEntry::query()->where('closing_run_id', $run->id)
            ->where('closing_subtype', 'opening_carry_forward')->count());
        foreach (['410000', '640000', '320000'] as $code) {
            $accountId = $this->account($context, $code)->id;
            $balance = \DB::table('journal_entry_lines as lines')
                ->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')
                ->where('entries.fiscal_year_id', $context['year']->id)
                ->where('entries.status', 'posted')
                ->where('lines.account_id', $accountId)
                ->selectRaw('COALESCE(SUM(lines.base_debit_amount - lines.base_credit_amount), 0) balance')
                ->value('balance');
            $this->assertSame(0, bccomp((string) $balance, '0', 4));
        }
        $this->switchActor($context['user']);
        $this->assertSame($run->id, $service->start($context['year']->fresh(), 'Retry close')->id);
        $this->assertSame(4, JournalEntry::query()->where('closing_run_id', $run->id)->count());

        $reopenService = app(AccountingReopenService::class);
        $reopenRun = $reopenService->startFiscalYear($context['year']->fresh(), 'Auditor-approved correction');
        $this->switchActor($context['reviewer']);
        $reopenService->approveFiscalYear($reopenRun, 'Approved independently');
        $this->assertSame('soft_closed', $context['year']->fresh()->status);
        $this->assertSame('reopened', $run->fresh()->status);
        $this->assertSame(4, JournalEntry::query()->whereNotNull('reversal_of_id')
            ->whereIn('reversal_of_id', JournalEntry::query()->where('closing_run_id', $run->id)
                ->whereNull('reversal_of_id')->select('id'))->count());
        $this->assertSame(8, JournalEntry::query()->where('closing_run_id', $run->id)->count());
    }

    private function context(): array
    {
        $currency = Currency::query()->create([
            'code' => strtoupper(substr(hash('sha1', uniqid('', true)), 0, 3)), 'name_ar' => 'Currency',
            'name_en' => 'Currency', 'symbol' => 'C', 'decimal_places' => 2, 'is_active' => true,
        ]);
        $company = Company::query()->create(['name' => 'Closing '.uniqid(), 'currency_id' => $currency->id]);
        $branch = Branch::query()->create(['company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main', 'is_main' => true, 'is_active' => true]);
        $role = Role::query()->create(['company_id' => $company->id, 'name' => 'company_owner', 'display_name' => 'Owner', 'scope' => 'company', 'is_active' => true]);
        $user = $this->user($company, $branch, $role);
        $reviewer = $this->user($company, $branch, $role);
        $approver = $this->user($company, $branch, $role);
        $executor = $this->user($company, $branch, $role);
        $this->switchActor($user);
        app(AccountingFoundationSeeder::class)->run();
        app(AccountingPostingSeeder::class)->run();
        app(FinancialReportingSeeder::class)->run();
        app(AccountingClosingSeeder::class)->run();
        $year = FiscalYear::factory()->create([
            'company_id' => $company->id, 'code' => 'FY-2039', 'name' => 'FY 2039',
            'start_date' => '2039-01-01', 'end_date' => '2039-12-31', 'status' => 'open',
            'is_current' => true, 'created_by' => $user->id,
        ]);
        app(FiscalPeriodGenerationService::class)->monthly($year);
        $period = AccountingPeriod::query()->where('fiscal_year_id', $year->id)->where('period_number', 3)->firstOrFail();

        return compact('currency', 'company', 'branch', 'role', 'user', 'reviewer', 'approver', 'executor', 'year', 'period');
    }

    private function user(Company $company, Branch $branch, Role $role): User
    {
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);

        return $user;
    }

    private function switchActor(User $user): void
    {
        $this->actingAs($user);
        app(TenantContext::class)->initialize($user);
    }

    private function adjustmentData(array $context): array
    {
        return [
            'entry_date' => '2039-03-10', 'branch_id' => $context['branch']->id,
            'description' => 'Accrued expense adjustment', 'adjustment_type' => 'accrual',
            'supporting_reference' => 'SUP-2039-03', 'reversal_policy' => 'scheduled',
            'scheduled_reversal_date' => '2039-03-20',
            'lines' => [
                ['account_id' => $this->account($context, '640000')->id, 'debit_amount' => 100, 'credit_amount' => 0],
                ['account_id' => $this->account($context, '211000')->id, 'debit_amount' => 0, 'credit_amount' => 100],
            ],
        ];
    }

    private function account(array $context, string $code): Account
    {
        return Account::query()->where('company_id', $context['company']->id)->where('account_code', $code)->firstOrFail();
    }
}
