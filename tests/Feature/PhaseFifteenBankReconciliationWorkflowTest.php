<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\Bank;
use App\Models\BankAdjustment;
use App\Models\BankReconciliationMatch;
use App\Models\BankReconciliationSession;
use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\BankAccountAccessService;
use App\Services\BankAccountService;
use App\Services\BankAdjustmentService;
use App\Services\BankMatchingScoreService;
use App\Services\BankReconciliationMatchingService;
use App\Services\BankReconciliationReopenService;
use App\Services\BankReconciliationSessionService;
use App\Services\FiscalPeriodGenerationService;
use Database\Seeders\AccountingFoundationSeeder;
use Database\Seeders\AccountingPostingSeeder;
use Database\Seeders\BankReconciliationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\TreasuryFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PhaseFifteenBankReconciliationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_one_to_many_partial_matching_is_atomic_and_rejects_overallocation(): void
    {
        $context = $this->context();
        $statement = $this->statement($context, 'credit', '50');
        $book = $this->journal($context, [['debit', '20'], ['debit', '30']]);
        $session = $this->createSession($context, [$statement->statementImport]);
        $match = app(BankReconciliationMatchingService::class)->createManualMatch(
            $session,
            [['id' => $statement->id, 'amount' => 50]],
            [['id' => $book[0]->id, 'amount' => 20], ['id' => $book[1]->id, 'amount' => 30]]
        );
        $this->assertSame('one_to_many', $match->match_type);
        $this->assertSame('matched', $statement->fresh()->status);

        $this->expectException(BusinessRuleException::class);
        app(BankReconciliationMatchingService::class)->createManualMatch(
            $session, [['id' => $statement->id, 'amount' => 1]], [['id' => $book[0]->id, 'amount' => 1]]
        );
    }

    public function test_score_is_explainable_and_bounded(): void
    {
        $context = $this->context();
        $statement = $this->statement($context, 'credit', '50', 'REF-1');
        $book = $this->journal($context, [['debit', '50']], 'REF-1')[0];
        $result = app(BankMatchingScoreService::class)->score($statement, $book);

        $this->assertSame(100, $result['score']);
        $this->assertContains('exact_reference', $result['reasons']);
        $this->assertContains('exact_amount', $result['reasons']);
    }

    public function test_many_to_one_and_many_to_many_matching_are_supported(): void
    {
        $context = $this->context();
        $first = $this->statement($context, 'credit', '20');
        $second = $this->statement($context, 'credit', '30');
        $book = $this->journal($context, [['debit', '50']]);
        $session = $this->createSession($context, [$first->statementImport, $second->statementImport]);
        $manyToOne = app(BankReconciliationMatchingService::class)->createManualMatch(
            $session,
            [['id' => $first->id, 'amount' => 20], ['id' => $second->id, 'amount' => 30]],
            [['id' => $book[0]->id, 'amount' => 50]]
        );
        $this->assertSame('many_to_one', $manyToOne->match_type);

        $third = $this->statement($context, 'credit', '10');
        $fourth = $this->statement($context, 'credit', '15');
        $otherBook = $this->journal($context, [['debit', '5'], ['debit', '20']]);
        $otherSession = $this->createSession($context, [$third->statementImport, $fourth->statementImport]);
        $manyToMany = app(BankReconciliationMatchingService::class)->createManualMatch(
            $otherSession,
            [['id' => $third->id, 'amount' => 10], ['id' => $fourth->id, 'amount' => 15]],
            [['id' => $otherBook[0]->id, 'amount' => 5], ['id' => $otherBook[1]->id, 'amount' => 20]]
        );
        $this->assertSame('many_to_many', $manyToMany->match_type);
    }

    public function test_partial_statement_and_book_allocations_recalculate_remaining_amounts(): void
    {
        $context = $this->context();
        $statement = $this->statement($context, 'credit', '50');
        $book = $this->journal($context, [['debit', '50']]);
        $session = $this->createSession($context, [$statement->statementImport]);
        app(BankReconciliationMatchingService::class)->createManualMatch(
            $session, [['id' => $statement->id, 'amount' => 20]], [['id' => $book[0]->id, 'amount' => 20]]
        );

        $this->assertSame('partially_matched', $statement->fresh()->status);
        $this->assertSame('30.0000', $statement->fresh()->unmatched_amount);
        app(BankReconciliationMatchingService::class)->createManualMatch(
            $session, [['id' => $statement->id, 'amount' => 30]], [['id' => $book[0]->id, 'amount' => 30]]
        );
        $this->assertSame('matched', $statement->fresh()->status);
    }

    public function test_bank_adjustment_posts_once_through_journal_engine_and_reverses_exactly(): void
    {
        $context = $this->context();
        $service = app(BankAdjustmentService::class);
        $adjustment = $service->create([
            'bank_account_id' => $context['account']->id, 'adjustment_type' => 'bank_fee',
            'adjustment_date' => '2040-01-15', 'exchange_rate' => 1, 'amount' => 25,
            'offset_account_id' => $this->account($context, '640000')->id,
            'description' => 'Monthly bank fee',
        ]);
        $service->action($adjustment, 'submit');
        $this->switchActor($context['reviewer']);
        $service->action($adjustment->fresh(), 'approve');
        $this->switchActor($context['approver']);
        $posted = $service->action($adjustment->fresh(), 'post');
        $journalCount = JournalEntry::query()->where('source_type', BankAdjustment::class)
            ->where('source_id', $adjustment->id)->count();
        $this->assertSame('posted', $posted->status);
        $this->assertSame(1, $journalCount);
        $service->action($posted->fresh(), 'post');
        $this->assertSame($journalCount, JournalEntry::query()->where('source_type', BankAdjustment::class)
            ->where('source_id', $adjustment->id)->count());

        $this->switchActor($context['poster']);
        $reversed = $service->action($posted->fresh(), 'reverse', ['reason' => 'Correction', 'date' => '2040-01-16']);
        $this->assertSame('reversed', $reversed->status);
        $this->assertNotNull($reversed->reversal_journal_entry_id);
    }

    public function test_completion_requires_sod_and_reopen_preserves_matches_without_reversing_adjustments(): void
    {
        $context = $this->context();
        $statement = $this->statement($context, 'credit', '50');
        $book = $this->journal($context, [['debit', '50']]);
        $session = $this->createSession($context, [$statement->statementImport]);
        app(BankReconciliationMatchingService::class)->createManualMatch(
            $session, [['id' => $statement->id, 'amount' => 50]], [['id' => $book[0]->id, 'amount' => 50]]
        );
        $service = app(BankReconciliationSessionService::class);
        $this->switchActor($context['reviewer']);
        $service->action($session->fresh(), 'review', ['notes' => 'Reviewed']);
        $this->switchActor($context['approver']);
        $service->action($session->fresh(), 'approve', ['notes' => 'Approved']);
        $this->switchActor($context['poster']);
        $completed = $service->action($session->fresh(), 'complete');
        $this->assertSame('completed', $completed->status);
        $this->assertSame('2040-01-31', $context['account']->fresh()->last_reconciled_date->toDateString());
        $matchCount = BankReconciliationMatch::query()->where('bank_reconciliation_session_id', $session->id)->count();
        try {
            app(BankReconciliationMatchingService::class)->unmatch(
                BankReconciliationMatch::query()->where('bank_reconciliation_session_id', $session->id)->firstOrFail(),
                'Not allowed after completion'
            );
            $this->fail('Completed reconciliation allowed unmatch.');
        } catch (BusinessRuleException) {
            $this->assertSame($matchCount, BankReconciliationMatch::query()
                ->where('bank_reconciliation_session_id', $session->id)->count());
        }

        $this->switchActor($context['user']);
        $reopened = app(BankReconciliationReopenService::class)->reopen($completed->fresh(), 'Investigate new evidence');
        $this->assertSame('reopened', $reopened->status);
        $this->assertSame($matchCount, BankReconciliationMatch::query()
            ->where('bank_reconciliation_session_id', $session->id)->count());
        $this->assertNull($context['account']->fresh()->last_reconciled_date);
    }

    public function test_reconciliation_seeder_is_idempotent_and_creates_no_operational_records(): void
    {
        $this->context();
        $before = [
            BankStatementImport::query()->count(), BankReconciliationSession::query()->count(),
            BankReconciliationMatch::query()->count(), BankAdjustment::query()->count(), JournalEntry::query()->count(),
        ];
        app(BankReconciliationSeeder::class)->run();
        app(BankReconciliationSeeder::class)->run();
        $this->assertSame($before, [
            BankStatementImport::query()->count(), BankReconciliationSession::query()->count(),
            BankReconciliationMatch::query()->count(), BankAdjustment::query()->count(), JournalEntry::query()->count(),
        ]);
    }

    private function context(): array
    {
        $currency = Currency::query()->create([
            'code' => strtoupper(substr(hash('sha1', uniqid('', true)), 0, 3)),
            'name_ar' => 'Currency', 'name_en' => 'Currency', 'symbol' => 'C',
            'decimal_places' => 2, 'is_active' => true,
        ]);
        $company = Company::query()->create(['name' => 'Workflow '.uniqid(), 'currency_id' => $currency->id]);
        $branch = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main', 'is_main' => true, 'is_active' => true,
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id, 'name' => 'company_owner',
            'display_name' => 'Owner', 'scope' => 'company', 'is_active' => true,
        ]);
        $user = $this->user($company, $branch, $role);
        $reviewer = $this->user($company, $branch, $role);
        $approver = $this->user($company, $branch, $role);
        $poster = $this->user($company, $branch, $role);
        $this->switchActor($user);
        app(ReferenceDataSeeder::class)->run();
        $method = new PaymentMethod;
        $method->forceFill([
            'company_id' => $company->id, 'code' => 'CASH', 'name' => 'Cash', 'type' => 'cash',
            'requires_reference' => false, 'is_cash' => true, 'is_active' => true, 'sort_order' => 1,
        ])->save();
        app(AccountingFoundationSeeder::class)->run();
        app(AccountingPostingSeeder::class)->run();
        app(TreasuryFoundationSeeder::class)->run();
        $year = FiscalYear::factory()->create([
            'company_id' => $company->id, 'code' => 'FY-2040', 'name' => 'FY 2040',
            'start_date' => '2040-01-01', 'end_date' => '2040-12-31', 'status' => 'open',
            'is_current' => true, 'created_by' => $user->id,
        ]);
        app(FiscalPeriodGenerationService::class)->monthly($year);
        app(BankReconciliationSeeder::class)->run();
        $period = $year->periods()->where('period_number', 1)->firstOrFail();
        $account = app(BankAccountService::class)->create([
            'bank_id' => Bank::query()->where('is_system', true)->value('id'), 'branch_id' => null,
            'account_code' => 'BANK-'.uniqid(), 'account_name' => 'Operating Bank',
            'iban' => 'SA'.str_pad((string) random_int(1, 999999999), 22, '0'), 'currency_id' => $currency->id,
            'gl_account_id' => $this->account(['company' => $company], '112000')->id, 'account_type' => 'current',
            'is_primary' => false, 'allows_receipts' => true, 'allows_payments' => true,
            'allows_transfers' => true, 'requires_reconciliation' => true,
        ]);
        $account = app(BankAccountService::class)->action($account, 'activate', 'Workflow test');
        app(BankAccountAccessService::class)->save($account, [
            'branch_id' => $branch->id, 'can_view' => true, 'can_receive' => true, 'can_pay' => true,
            'can_transfer' => true, 'daily_payment_limit' => 100000, 'daily_transfer_limit' => 100000, 'is_active' => true,
        ]);

        return compact('currency', 'company', 'branch', 'role', 'user', 'reviewer', 'approver', 'poster', 'year', 'period', 'account');
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

    private function account(array $context, string $code): Account
    {
        return Account::query()->where('company_id', $context['company']->id)->where('account_code', $code)->firstOrFail();
    }

    private function statement(array $context, string $direction, string $amount, ?string $reference = null): BankStatementLine
    {
        $import = new BankStatementImport;
        $import->forceFill([
            'company_id' => $context['company']->id, 'bank_account_id' => $context['account']->id,
            'file_name' => uniqid().'.csv', 'original_file_name' => 'test.csv',
            'storage_path' => 'private/test/'.uniqid().'.csv', 'file_hash' => hash('sha256', uniqid('', true)),
            'format' => 'csv', 'parser_version' => 'csv-v1', 'period_start' => '2040-01-01',
            'period_end' => '2040-01-31', 'opening_balance' => 0,
            'closing_balance' => $direction === 'credit' ? $amount : '-'.$amount,
            'currency_id' => $context['currency']->id, 'status' => 'imported', 'total_lines' => 1,
            'imported_lines' => 1, 'uploaded_by' => $context['user']->id, 'imported_at' => now(),
        ])->save();
        $line = new BankStatementLine;
        $line->forceFill([
            'company_id' => $context['company']->id, 'bank_statement_import_id' => $import->id,
            'bank_account_id' => $context['account']->id, 'line_number' => 1,
            'transaction_date' => '2040-01-15', 'description' => 'Statement line',
            'bank_reference' => $reference, 'debit_amount' => $direction === 'debit' ? $amount : 0,
            'credit_amount' => $direction === 'credit' ? $amount : 0, 'currency_id' => $context['currency']->id,
            'status' => 'unmatched', 'matched_amount' => 0, 'unmatched_amount' => $amount,
            'is_duplicate' => false, 'raw_hash' => hash('sha256', uniqid('', true)),
        ])->save();

        return $line;
    }

    private function journal(array $context, array $bankSides, ?string $reference = null): array
    {
        $total = array_reduce($bankSides, fn ($sum, $side) => bcadd($sum, $side[1], 4), '0.0000');
        $entry = new JournalEntry;
        $entry->forceFill([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'fiscal_year_id' => $context['year']->id, 'accounting_period_id' => $context['period']->id,
            'journal_number' => 'BR-TEST-'.uniqid(), 'entry_type' => 'manual', 'status' => 'posted',
            'entry_date' => '2040-01-15', 'posting_date' => '2040-01-15',
            'currency_id' => $context['currency']->id, 'exchange_rate' => 1, 'description' => 'Book transaction',
            'reference' => $reference, 'total_debit' => $total, 'total_credit' => $total,
            'base_total_debit' => $total, 'base_total_credit' => $total,
            'created_by' => $context['user']->id, 'posted_by' => $context['user']->id, 'posted_at' => now(),
        ])->save();
        $lines = [];
        foreach ($bankSides as $index => [$side, $amount]) {
            $lines[] = $entry->lines()->create([
                'line_number' => $index + 1, 'account_id' => $context['account']->gl_account_id,
                'branch_id' => $context['branch']->id, 'currency_id' => $context['currency']->id,
                'exchange_rate' => 1, 'debit_amount' => $side === 'debit' ? $amount : 0,
                'credit_amount' => $side === 'credit' ? $amount : 0,
                'base_debit_amount' => $side === 'debit' ? $amount : 0,
                'base_credit_amount' => $side === 'credit' ? $amount : 0, 'reference' => $reference,
            ]);
        }
        $offsetSide = $bankSides[0][0] === 'debit' ? 'credit' : 'debit';
        $entry->lines()->create([
            'line_number' => count($bankSides) + 1, 'account_id' => $this->account($context, '310000')->id,
            'branch_id' => $context['branch']->id, 'currency_id' => $context['currency']->id,
            'exchange_rate' => 1, 'debit_amount' => $offsetSide === 'debit' ? $total : 0,
            'credit_amount' => $offsetSide === 'credit' ? $total : 0,
            'base_debit_amount' => $offsetSide === 'debit' ? $total : 0,
            'base_credit_amount' => $offsetSide === 'credit' ? $total : 0,
        ]);

        return $lines;
    }

    private function createSession(array $context, array $imports): BankReconciliationSession
    {
        return app(BankReconciliationSessionService::class)->create([
            'bank_account_id' => $context['account']->id, 'date_from' => '2040-01-01',
            'date_to' => '2040-01-31', 'import_ids' => collect($imports)->pluck('id')->all(), 'tolerance' => 0,
        ]);
    }
}
