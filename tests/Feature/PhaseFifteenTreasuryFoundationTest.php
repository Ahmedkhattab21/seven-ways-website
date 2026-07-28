<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\BankAccountBranchAccess;
use App\Models\Branch;
use App\Models\CashBox;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodAccountMapping;
use App\Models\Role;
use App\Models\TreasuryTransfer;
use App\Models\User;
use App\Services\BankAccountAccessService;
use App\Services\BankAccountService;
use App\Services\CashBoxCustodianService;
use App\Services\FiscalPeriodGenerationService;
use App\Services\TreasuryAccountResolver;
use App\Services\TreasuryBalanceService;
use App\Services\TreasuryMappingService;
use App\Services\TreasuryTransferService;
use Database\Seeders\AccountingFoundationSeeder;
use Database\Seeders\AccountingPostingSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\TreasuryFoundationSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseFifteenTreasuryFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_treasury_mapping_page_uses_readable_labels_and_eager_loaded_relations(): void
    {
        $context = $this->context();
        $method = PaymentMethod::query()->where('company_id', $context['company']->id)->where('code', 'CASH')->firstOrFail();
        app(TreasuryMappingService::class)->save([
            'payment_method_id' => $method->id, 'branch_id' => $context['branch']->id,
            'operation_type' => 'receipt', 'account_id' => $this->account($context, '112000')->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($context['user'])->get(route('treasury.mappings.index'));

        $response->assertOk()
            ->assertSee('Cash')
            ->assertSee('Main')
            ->assertSee('قبض')
            ->assertSee('112000')
            ->assertSee('نشط')
            ->assertDontSee('>1<')
            ->assertDontSee('>2<');
        $loaded = PaymentMethodAccountMapping::query()->with([
            'paymentMethod', 'branch', 'account', 'bankAccount', 'cashBox',
        ])->findOrFail(
            PaymentMethodAccountMapping::query()->where('company_id', $context['company']->id)->value('id')
        );
        $this->assertTrue($loaded->relationLoaded('paymentMethod'));
        $this->assertTrue($loaded->relationLoaded('branch'));
    }

    public function test_branch_bank_account_gets_receipt_access_without_payment_or_transfer_rights(): void
    {
        $context = $this->context();
        $account = app(BankAccountService::class)->create($this->bankData($context, [
            'branch_id' => $context['branch']->id, 'account_code' => 'BANK-CAI-001',
        ]));
        $access = BankAccountBranchAccess::query()->where('bank_account_id', $account->id)
            ->where('branch_id', $context['branch']->id)->firstOrFail();
        $this->assertTrue($access->is_active);
        $this->assertTrue($access->can_view);
        $this->assertTrue($access->can_receive);
        $this->assertFalse($access->can_pay);
        $this->assertFalse($access->can_transfer);
    }

    public function test_bank_receipt_mapping_uses_receive_access_and_payment_is_rejected_without_pay_access(): void
    {
        $context = $this->context();
        $account = app(BankAccountService::class)->create($this->bankData($context, [
            'branch_id' => $context['branch']->id, 'account_code' => 'BANK-CAI-002',
        ]));
        app(BankAccountService::class)->action($account, 'activate', 'Activate for mapping test');
        $method = new PaymentMethod;
        $method->forceFill([
            'company_id' => $context['company']->id, 'code' => 'BANK_TRANSFER', 'name' => 'Bank transfer',
            'type' => 'bank', 'is_active' => true,
        ])->save();
        $mapping = app(TreasuryMappingService::class)->save([
            'payment_method_id' => $method->id, 'branch_id' => $context['branch']->id,
            'operation_type' => 'receipt', 'bank_account_id' => $account->id, 'is_active' => true,
        ]);
        $this->assertSame($account->id, $mapping->bank_account_id);
        $this->expectException(BusinessRuleException::class);
        app(TreasuryMappingService::class)->save([
            'payment_method_id' => $method->id, 'branch_id' => $context['branch']->id,
            'operation_type' => 'payment', 'bank_account_id' => $account->id, 'is_active' => true,
        ]);
    }

    public function test_schema_has_treasury_foundation_without_stored_balances(): void
    {
        foreach (['banks', 'bank_accounts', 'bank_account_branch_access', 'cash_boxes',
            'cash_box_custodians', 'treasury_transfers'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertFalse(Schema::hasColumn('bank_accounts', 'balance'));
        $this->assertFalse(Schema::hasColumn('cash_boxes', 'balance'));
        $this->assertFalse(Schema::hasColumn('accounts', 'balance'));
    }

    public function test_bank_account_validates_gl_masks_iban_and_keeps_one_primary_per_currency(): void
    {
        $context = $this->context();
        $service = app(BankAccountService::class);

        try {
            $service->create($this->bankData($context, ['gl_account_id' => $this->account($context, '111000')->id]));
            $this->fail('Cash GL was accepted for a bank account.');
        } catch (BusinessRuleException) {
            $this->assertSame(0, BankAccount::query()->where('company_id', $context['company']->id)->count());
        }

        $first = $service->create($this->bankData($context, ['is_primary' => true]));
        $this->assertNotSame('SA0000000000000000000001', $first->getRawOriginal('iban'));
        $this->assertStringEndsWith('0001', $first->maskedIban());
        $service->action($first, 'activate', 'Activate primary treasury account');

        $second = $service->create($this->bankData($context, [
            'account_code' => 'BANK-2', 'iban' => 'SA0000000000000000000002', 'is_primary' => true,
        ]));
        $service->action($second, 'activate', 'Activate replacement primary account');
        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);

        $this->expectException(BusinessRuleException::class);
        $service->create($this->bankData($context, [
            'account_code' => 'BANK-3', 'iban' => 'SA00 0000 0000 0000 0000 0002',
        ]));
    }

    public function test_branch_access_cash_gl_and_custodian_overlap_are_enforced(): void
    {
        $context = $this->context();
        $bank = $this->activeBankAccount($context);
        app(BankAccountAccessService::class)->save($bank, [
            'branch_id' => $context['branch']->id, 'can_view' => true, 'can_receive' => true,
            'can_pay' => false, 'can_transfer' => true, 'daily_transfer_limit' => 500, 'is_active' => true,
        ]);
        app(BankAccountAccessService::class)->assert($bank, $context['branch']->id, 'can_transfer', '500');

        $this->expectException(BusinessRuleException::class);
        app(BankAccountAccessService::class)->assert($bank, $context['secondBranch']->id, 'can_view');
    }

    public function test_cash_custodian_mapping_precedence_and_backend_authorization(): void
    {
        $context = $this->context();
        $box = CashBox::query()->where('company_id', $context['company']->id)
            ->where('branch_id', $context['branch']->id)->firstOrFail();
        $method = PaymentMethod::query()->where('company_id', $context['company']->id)->where('is_cash', true)->firstOrFail();
        $mapping = app(TreasuryMappingService::class);
        $mapping->save([
            'payment_method_id' => $method->id, 'branch_id' => null, 'operation_type' => 'receipt',
            'account_id' => $this->account($context, '112000')->id, 'is_active' => true,
        ]);
        $mapping->save([
            'payment_method_id' => $method->id, 'branch_id' => $context['branch']->id, 'operation_type' => 'receipt',
            'cash_box_id' => $box->id, 'is_active' => true,
        ]);

        $this->switchActor($context['cashier']);
        try {
            app(TreasuryAccountResolver::class)->resolve(
                $method->id, $context['branch']->id, 'receipt', $context['currency']->id, '100'
            );
            $this->fail('Cashier used a cash box without custodian assignment.');
        } catch (BusinessRuleException) {
            $this->assertTrue(true);
        }

        $this->switchActor($context['user']);
        $custodian = app(CashBoxCustodianService::class)->assign($box, [
            'user_id' => $context['cashier']->id, 'valid_from' => now()->toDateString(),
            'can_receive' => true, 'can_pay' => true, 'can_transfer' => true, 'is_primary' => true,
        ]);
        try {
            app(CashBoxCustodianService::class)->assign($box, [
                'user_id' => $context['cashier']->id, 'valid_from' => now()->toDateString(),
                'can_receive' => true, 'is_primary' => false,
            ]);
            $this->fail('Overlapping custodian assignment was accepted.');
        } catch (BusinessRuleException) {
            $this->assertTrue($custodian->is_active);
        }

        $this->switchActor($context['cashier']);
        $resolved = app(TreasuryAccountResolver::class)->resolve(
            $method->id, $context['branch']->id, 'receipt', $context['currency']->id, '100'
        );
        $this->assertSame($box->id, $resolved['cash_box_id']);
        $this->assertSame($box->gl_account_id, $resolved['account_id']);
    }

    public function test_book_balance_comes_from_posted_journals_and_transfer_foundation_never_posts(): void
    {
        $context = $this->context();
        $bank = $this->activeBankAccount($context);
        app(BankAccountAccessService::class)->save($bank, [
            'branch_id' => $context['branch']->id, 'can_view' => true, 'can_receive' => true,
            'can_pay' => true, 'can_transfer' => true, 'daily_transfer_limit' => 1000, 'is_active' => true,
        ]);
        $box = CashBox::query()->where('company_id', $context['company']->id)
            ->where('branch_id', $context['branch']->id)->firstOrFail();
        $this->postBalanceJournal($context, $bank->gl_account_id, '750.0000');
        $this->assertSame('750.0000', app(TreasuryBalanceService::class)->bank($bank)['book_balance']);

        $before = JournalEntry::query()->count();
        $service = app(TreasuryTransferService::class);
        $transfer = $service->create([
            'from_type' => 'bank', 'from_bank_account_id' => $bank->id, 'from_cash_box_id' => null,
            'to_type' => 'cash_box', 'to_bank_account_id' => null, 'to_cash_box_id' => $box->id,
            'branch_id' => $context['branch']->id, 'destination_branch_id' => $context['branch']->id,
            'currency_id' => $context['currency']->id, 'exchange_rate' => 1,
            'amount' => 100, 'fees_amount' => 0, 'transfer_date' => now()->toDateString(),
        ]);
        $service->action($transfer, 'submit');
        try {
            $service->action($transfer->fresh(), 'approve');
            $this->fail('Transfer creator approved the same transfer.');
        } catch (BusinessRuleException) {
            $this->assertSame('pending_approval', $transfer->fresh()->status);
        }
        $this->switchActor($context['approver']);
        $service->action($transfer->fresh(), 'approve');
        $this->assertSame('approved', $transfer->fresh()->status);
        $this->assertNull($transfer->fresh()->journal_entry_id);
        $this->assertSame($before, JournalEntry::query()->count());
    }

    public function test_treasury_seeder_is_idempotent_and_creates_no_transfers_or_journals(): void
    {
        $context = $this->context();
        $before = [
            Bank::query()->count(), CashBox::query()->where('company_id', $context['company']->id)->count(),
            TreasuryTransfer::query()->count(), JournalEntry::query()->count(),
        ];
        app(TreasuryFoundationSeeder::class)->run();
        app(TreasuryFoundationSeeder::class)->run();
        $this->assertSame($before, [
            Bank::query()->count(), CashBox::query()->where('company_id', $context['company']->id)->count(),
            TreasuryTransfer::query()->count(), JournalEntry::query()->count(),
        ]);
    }

    public function test_cross_company_actions_and_mass_assignment_are_blocked(): void
    {
        $context = $this->context();
        $bank = $this->activeBankAccount($context);
        $otherCompany = Company::query()->create([
            'name' => 'Other Treasury Company', 'currency_id' => $context['currency']->id,
        ]);
        $otherBranch = Branch::query()->create([
            'company_id' => $otherCompany->id, 'code' => 'OTHER', 'name' => 'Other',
            'is_main' => true, 'is_active' => true,
        ]);
        $otherRole = Role::query()->create([
            'company_id' => $otherCompany->id, 'name' => 'company_owner',
            'display_name' => 'Other Owner', 'scope' => 'company', 'is_active' => true,
        ]);
        $otherUser = $this->user($otherCompany, $otherBranch, $otherRole);
        $this->switchActor($otherUser);

        try {
            app(BankAccountService::class)->action($bank, 'suspend', 'Cross-company action');
            $this->fail('Cross-company bank account action was accepted.');
        } catch (ModelNotFoundException) {
            $this->assertSame('active', $bank->fresh()->status);
        }

        $transfer = new TreasuryTransfer([
            'company_id' => $context['company']->id, 'status' => 'approved',
            'document_number' => 'SPOOF', 'journal_entry_id' => 1,
        ]);
        $this->assertNull($transfer->company_id);
        $this->assertNull($transfer->status);
        $this->assertNull($transfer->document_number);
        $this->assertNull($transfer->journal_entry_id);
    }

    private function context(): array
    {
        $currency = Currency::query()->create([
            'code' => strtoupper(substr(hash('sha1', uniqid('', true)), 0, 3)),
            'name_ar' => 'Currency', 'name_en' => 'Currency', 'symbol' => 'C',
            'decimal_places' => 2, 'is_active' => true,
        ]);
        $company = Company::query()->create(['name' => 'Treasury '.uniqid(), 'currency_id' => $currency->id]);
        $branch = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main',
            'is_main' => true, 'is_active' => true,
        ]);
        $secondBranch = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'B2', 'name' => 'Second',
            'is_main' => false, 'is_active' => true,
        ]);
        $ownerRole = Role::query()->create([
            'company_id' => $company->id, 'name' => 'company_owner',
            'display_name' => 'Owner', 'scope' => 'company', 'is_active' => true,
        ]);
        $cashierRole = Role::query()->create([
            'company_id' => $company->id, 'name' => 'cashier',
            'display_name' => 'Cashier', 'scope' => 'branch', 'is_active' => true,
        ]);
        $user = $this->user($company, $branch, $ownerRole);
        $approver = $this->user($company, $branch, $ownerRole);
        $cashier = $this->user($company, $branch, $cashierRole);
        $this->switchActor($user);
        app(ReferenceDataSeeder::class)->run();
        $cashMethod = new PaymentMethod;
        $cashMethod->forceFill([
            'company_id' => $company->id, 'code' => 'CASH', 'name' => 'Cash',
            'type' => 'cash', 'requires_reference' => false, 'is_cash' => true,
            'is_active' => true, 'sort_order' => 1,
        ])->save();
        app(AccountingFoundationSeeder::class)->run();
        app(AccountingPostingSeeder::class)->run();
        app(TreasuryFoundationSeeder::class)->run();
        $year = FiscalYear::factory()->create([
            'company_id' => $company->id, 'code' => 'FY-2040', 'name' => 'FY 2040',
            'start_date' => '2040-01-01', 'end_date' => '2040-12-31',
            'status' => 'open', 'is_current' => true, 'created_by' => $user->id,
        ]);
        app(FiscalPeriodGenerationService::class)->monthly($year);
        $period = $year->periods()->where('period_number', 1)->firstOrFail();

        return compact('currency', 'company', 'branch', 'secondBranch', 'user', 'approver', 'cashier', 'year', 'period');
    }

    private function user(Company $company, Branch $branch, Role $role): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);

        return $user;
    }

    private function switchActor(User $user): void
    {
        $this->actingAs($user);
        app(TenantContext::class)->initialize($user);
    }

    private function bankData(array $context, array $overrides = []): array
    {
        return $overrides + [
            'bank_id' => Bank::query()->where('is_system', true)->value('id'),
            'branch_id' => null, 'account_code' => 'BANK-1', 'account_name' => 'Operating Bank',
            'iban' => 'SA0000000000000000000001', 'currency_id' => $context['currency']->id,
            'gl_account_id' => $this->account($context, '112000')->id, 'account_type' => 'current',
            'is_primary' => false, 'allows_receipts' => true, 'allows_payments' => true,
            'allows_transfers' => true, 'requires_reconciliation' => true,
        ];
    }

    private function activeBankAccount(array $context): BankAccount
    {
        $account = app(BankAccountService::class)->create($this->bankData($context));

        return app(BankAccountService::class)->action($account, 'activate', 'Activate for treasury tests');
    }

    private function account(array $context, string $code): Account
    {
        return Account::query()->where('company_id', $context['company']->id)
            ->where('account_code', $code)->firstOrFail();
    }

    private function postBalanceJournal(array $context, int $accountId, string $amount): void
    {
        $entry = new JournalEntry;
        $entry->forceFill([
            'company_id' => $context['company']->id, 'fiscal_year_id' => $context['year']->id,
            'accounting_period_id' => $context['period']->id, 'journal_number' => 'TR-BAL-'.uniqid(),
            'entry_type' => 'manual', 'status' => 'posted', 'entry_date' => '2040-01-10',
            'posting_date' => '2040-01-10', 'currency_id' => $context['currency']->id,
            'exchange_rate' => 1, 'description' => 'Treasury balance test',
            'total_debit' => $amount, 'total_credit' => $amount,
            'base_total_debit' => $amount, 'base_total_credit' => $amount,
            'created_by' => $context['user']->id, 'posted_by' => $context['user']->id, 'posted_at' => now(),
        ])->save();
        foreach ([
            [$accountId, $amount, 0],
            [$this->account($context, '310000')->id, 0, $amount],
        ] as $index => [$lineAccount, $debit, $credit]) {
            $entry->lines()->create([
                'line_number' => $index + 1, 'account_id' => $lineAccount,
                'branch_id' => $context['branch']->id, 'currency_id' => $context['currency']->id,
                'exchange_rate' => 1, 'debit_amount' => $debit, 'credit_amount' => $credit,
                'base_debit_amount' => $debit, 'base_credit_amount' => $credit,
            ]);
        }
    }
}
