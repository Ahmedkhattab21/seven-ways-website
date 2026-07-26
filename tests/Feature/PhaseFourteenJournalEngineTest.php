<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AccountingPostingLink;
use App\Models\AccountingSetting;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\OpeningBalanceDocument;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\AccountingPostingService;
use App\Services\JournalEntryService;
use App\Services\JournalEntryValidationService;
use Database\Seeders\AccountingFoundationSeeder;
use Database\Seeders\AccountingPostingSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseFourteenJournalEngineTest extends TestCase
{
    use DatabaseTransactions;

    public function test_schema_has_journal_engine_without_stored_account_balances(): void
    {
        $this->assertTrue(Schema::hasTable('journal_entries'));
        $this->assertTrue(Schema::hasTable('journal_entry_lines'));
        $this->assertTrue(Schema::hasTable('accounting_posting_links'));
        $this->assertTrue(Schema::hasTable('payment_method_account_mappings'));
        $this->assertTrue(Schema::hasTable('product_accounting_mappings'));
        $this->assertFalse(Schema::hasColumn('accounts', 'balance'));
    }

    public function test_posting_seeder_is_idempotent_and_never_creates_journals_or_resets_sequences(): void
    {
        $context = $this->context();
        $counts = $this->postingCounts($context['company']->id);
        $sequence = DocumentSequence::query()->where('company_id', $context['company']->id)
            ->where('branch_id', $context['branch']->id)->where('document_type', 'journal_entry')->firstOrFail();
        $sequence->forceFill(['current_number' => 9])->save();

        app(AccountingPostingSeeder::class)->run();

        $this->assertSame($counts, $this->postingCounts($context['company']->id));
        $this->assertSame(9, $sequence->fresh()->current_number);
        $this->assertSame(0, JournalEntry::query()->where('company_id', $context['company']->id)->count());
    }

    public function test_manual_journal_rejects_unbalanced_posting_and_enforces_state_machine(): void
    {
        $context = $this->context();
        AccountingSetting::query()->where('company_id', $context['company']->id)->update([
            'allow_manual_journals' => true, 'require_journal_approval' => false, 'separation_of_duties' => false,
        ]);
        $cash = $this->account($context, '111000');
        $revenue = $this->account($context, '410000');
        $service = app(JournalEntryService::class);
        $entry = $service->createManual([
            'branch_id' => $context['branch']->id, 'entry_date' => '2038-03-10',
            'description' => 'Manual test', 'lines' => [
                ['account_id' => $cash->id, 'currency_id' => $context['currency']->id, 'debit_amount' => 100, 'credit_amount' => 0],
                ['account_id' => $revenue->id, 'currency_id' => $context['currency']->id, 'debit_amount' => 0, 'credit_amount' => 90],
            ],
        ]);
        try {
            $service->action($entry, 'submit');
            $this->fail('Unbalanced journal was submitted.');
        } catch (BusinessRuleException) {
            $this->assertSame('draft', $entry->fresh()->status);
        }
        $service->updateManual($entry->fresh(), [
            'branch_id' => $context['branch']->id, 'entry_date' => '2038-03-10',
            'description' => 'Balanced', 'lines' => [
                ['account_id' => $cash->id, 'currency_id' => $context['currency']->id, 'debit_amount' => 100, 'credit_amount' => 0],
                ['account_id' => $revenue->id, 'currency_id' => $context['currency']->id, 'debit_amount' => 0, 'credit_amount' => 100],
            ],
        ]);
        $service->action($entry->fresh(), 'submit');
        $service->action($entry->fresh(), 'post');
        $this->assertSame('posted', $entry->fresh()->status);
        $this->assertSame('100.0000', $entry->fresh()->total_debit);
    }

    public function test_manual_control_account_needs_explicit_override_permission(): void
    {
        $context = $this->context();
        $receivable = $this->account($context, '113000');
        $this->expectException(BusinessRuleException::class);
        app(JournalEntryValidationService::class)->assertAccount(
            $receivable, ['customer_id' => 1], $context['company']->id, false
        );
    }

    public function test_opening_balance_posts_once_and_reversal_preserves_original(): void
    {
        $context = $this->context();
        $cash = $this->account($context, '111000');
        $capital = $this->account($context, '310000');
        $document = OpeningBalanceDocument::query()->forceCreate([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'fiscal_year_id' => $context['year']->id, 'document_number' => 'OB-14B',
            'status' => 'ready_for_posting', 'balance_date' => '2038-03-10',
            'total_debit' => 250, 'total_credit' => 250, 'created_by' => $context['user']->id,
        ]);
        $document->lines()->createMany([
            ['account_id' => $cash->id, 'currency_id' => $context['currency']->id, 'exchange_rate' => 1, 'debit_amount' => 250, 'credit_amount' => 0],
            ['account_id' => $capital->id, 'currency_id' => $context['currency']->id, 'exchange_rate' => 1, 'debit_amount' => 0, 'credit_amount' => 250],
        ]);
        $service = app(AccountingPostingService::class);
        $entry = $service->post($document);
        $this->assertSame($entry->id, $service->post($document->fresh())->id);
        $this->assertSame(1, AccountingPostingLink::query()->where('source_id', $document->id)->count());

        $reversal = $service->reverse($document->fresh(), 'Correction', '2038-03-11');
        $this->assertTrue($reversal->is_reversal);
        $this->assertSame('posted', $entry->fresh()->status);
        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id, 'reversed_by_entry_id' => $reversal->id]);
        $this->assertDatabaseCount('opening_balance_lines', 2);
    }

    public function test_sales_invoice_posting_is_idempotent_and_does_not_duplicate_cogs(): void
    {
        $context = $this->context();
        $customer = Customer::factory()->create([
            'company_id' => $context['company']->id,
            'created_branch_id' => $context['branch']->id, 'assigned_branch_id' => $context['branch']->id,
        ]);
        $invoice = SalesInvoice::factory()->create([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'customer_id' => $customer->id, 'currency_id' => $context['currency']->id,
            'status' => 'issued', 'invoice_date' => '2038-03-12', 'created_by' => $context['user']->id,
        ]);
        $service = app(AccountingPostingService::class);
        $first = $service->post($invoice);
        $second = $service->post($invoice->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, JournalEntry::query()->where('source_type', SalesInvoice::class)->where('source_id', $invoice->id)->count());
        $this->assertFalse($first->lines()->where('account_id', $this->account($context, '500000')->id)->exists());
        $this->assertSame('115.0000', $first->total_debit);
        $this->assertSame($first->total_debit, $first->total_credit);
    }

    public function test_stock_transfer_between_same_inventory_account_needs_no_journal(): void
    {
        $context = $this->context();
        $source = new StockTransfer();
        $source->forceFill([
            'id' => 999999, 'uuid' => fake()->uuid(), 'company_id' => $context['company']->id,
            'from_branch_id' => $context['branch']->id, 'to_branch_id' => $context['branch']->id,
            'status' => 'received', 'received_at' => '2038-03-15',
        ]);
        $preview = app(AccountingPostingService::class)->preview($source);
        $this->assertTrue($preview['not_required']);
        $this->assertSame([], $preview['lines']);
    }

    public function test_closed_and_module_locked_periods_reject_posting(): void
    {
        $context = $this->context();
        $context['period']->forceFill(['status' => 'closed'])->save();
        $source = new StockTransfer();
        $source->forceFill([
            'id' => 888888, 'uuid' => fake()->uuid(), 'company_id' => $context['company']->id,
            'from_branch_id' => $context['branch']->id, 'to_branch_id' => $context['branch']->id,
            'status' => 'received', 'received_at' => '2038-03-15',
        ]);
        $this->expectException(BusinessRuleException::class);
        app(AccountingPostingService::class)->preview($source);
    }

    private function context(): array
    {
        $currency = Currency::query()->create([
            'code' => strtoupper(substr(hash('sha1', uniqid('', true)), 0, 3)),
            'name_ar' => 'عملة', 'name_en' => 'Currency', 'symbol' => 'C',
            'decimal_places' => 2, 'is_active' => true,
        ]);
        $company = Company::query()->create(['name' => 'Journal '.uniqid(), 'currency_id' => $currency->id]);
        $branch = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main',
            'is_main' => true, 'is_active' => true,
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id, 'name' => 'company_owner',
            'display_name' => 'Owner', 'scope' => 'company', 'is_active' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);
        app(AccountingFoundationSeeder::class)->run();
        app(AccountingPostingSeeder::class)->run();
        $year = FiscalYear::factory()->create([
            'company_id' => $company->id, 'code' => 'FY-2038', 'name' => 'FY 2038',
            'start_date' => '2038-01-01', 'end_date' => '2038-12-31',
            'status' => 'open', 'created_by' => $user->id,
        ]);
        $period = AccountingPeriod::factory()->create([
            'company_id' => $company->id, 'fiscal_year_id' => $year->id,
            'period_number' => 3, 'code' => '2038-03', 'name' => 'March',
            'start_date' => '2038-03-01', 'end_date' => '2038-03-31', 'status' => 'open',
        ]);
        $this->actingAs($user);
        app(TenantContext::class)->initialize($user);

        return compact('currency', 'company', 'branch', 'user', 'year', 'period');
    }

    private function account(array $context, string $code): Account
    {
        return Account::query()->where('company_id', $context['company']->id)
            ->where('account_code', $code)->firstOrFail();
    }

    private function postingCounts(int $companyId): array
    {
        return [
            AccountingPostingLink::query()->where('company_id', $companyId)->count(),
            \App\Models\PaymentMethodAccountMapping::query()->where('company_id', $companyId)->count(),
            \App\Models\ProductAccountingMapping::query()->where('company_id', $companyId)->count(),
            \App\Models\PostingProfile::query()->where('company_id', $companyId)->count(),
        ];
    }
}
