<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\Bank;
use App\Models\BankStatementImport;
use App\Models\BankStatementImportProfile;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\BankAccountAccessService;
use App\Services\BankAccountService;
use App\Services\BankStatementImportService;
use App\Services\FiscalPeriodGenerationService;
use Database\Seeders\AccountingFoundationSeeder;
use Database\Seeders\AccountingPostingSeeder;
use Database\Seeders\BankReconciliationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\TreasuryFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseFifteenBankReconciliationImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_schema_has_reconciliation_tables_and_no_stored_bank_balance(): void
    {
        foreach ([
            'bank_statement_import_profiles', 'bank_statement_imports', 'bank_statement_lines',
            'bank_reconciliation_sessions', 'bank_reconciliation_session_imports',
            'bank_reconciliation_matches', 'bank_reconciliation_match_items',
            'bank_matching_rules', 'bank_adjustments',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertFalse(Schema::hasColumn('bank_accounts', 'balance'));
        $this->assertFalse(Schema::hasColumn('accounts', 'balance'));
    }

    public function test_csv_import_is_private_streamed_validated_and_immutable(): void
    {
        Storage::fake('local');
        $context = $this->context();
        $import = $this->import($context, "date,description,reference,debit,credit,balance,external_id\n".
            "2040-01-10,Receipt,R-1,,50,150,E-1\n2040-01-11,Fee,R-2,20,,130,E-2\n", 'statement.csv', '100', '130');

        $this->assertSame('imported', $import->status);
        $this->assertCount(2, $import->lines);
        $this->assertStringStartsWith('private/bank-statements/', $import->storage_path);
        $this->assertNotSame($import->original_file_name, $import->file_name);
        Storage::disk('local')->assertExists($import->storage_path);
        Storage::disk('public')->assertMissing($import->storage_path);
        $this->assertSame('50.0000', $import->lines->first()->unmatched_amount);
    }

    public function test_duplicate_file_is_rejected_even_with_another_name(): void
    {
        Storage::fake('local');
        $context = $this->context();
        $csv = "date,description,reference,debit,credit,balance,external_id\n2040-01-10,Receipt,R-1,,50,50,E-1\n";
        $this->import($context, $csv, 'first.csv', '0', '50');

        $this->expectException(BusinessRuleException::class);
        $this->import($context, $csv, 'renamed.csv', '0', '50');
    }

    public function test_invalid_numeric_formula_rolls_back_all_statement_lines(): void
    {
        Storage::fake('local');
        $context = $this->context();
        try {
            $this->import(
                $context,
                "date,description,reference,debit,credit,balance,external_id\n2040-01-10,Formula,R-1,,=2,2,E-1\n",
                'formula.csv',
                '0',
                '2'
            );
            $this->fail('Spreadsheet formula was accepted as a numeric amount.');
        } catch (BusinessRuleException) {
            $failed = BankStatementImport::query()->where('company_id', $context['company']->id)->latest('id')->firstOrFail();
            $this->assertSame('failed', $failed->status);
            $this->assertSame(0, $failed->lines()->count());
        }
    }

    public function test_invalid_statement_closing_balance_rolls_back_all_lines(): void
    {
        Storage::fake('local');
        $context = $this->context();
        try {
            $this->import(
                $context,
                "date,description,reference,debit,credit,balance,external_id\n2040-01-10,Receipt,R-1,,50,50,E-1\n",
                'bad-balance.csv',
                '0',
                '75'
            );
            $this->fail('Invalid closing balance was accepted.');
        } catch (BusinessRuleException) {
            $failed = BankStatementImport::query()->where('company_id', $context['company']->id)->latest('id')->firstOrFail();
            $this->assertSame('failed', $failed->status);
            $this->assertSame(0, $failed->lines()->count());
        }
    }

    public function test_duplicate_line_prefers_external_id_but_different_reference_is_not_automatically_duplicate(): void
    {
        Storage::fake('local');
        $context = $this->context();
        $this->import(
            $context,
            "date,description,reference,debit,credit,balance,external_id\n2040-01-10,Receipt,R-1,,50,50,E-1\n",
            'first.csv',
            '0',
            '50'
        );
        $duplicate = $this->import(
            $context,
            "date,description,reference,debit,credit,balance,external_id\n2040-01-10,Changed,R-X,,50,100,E-1\n",
            'second.csv',
            '50',
            '100'
        );
        $different = $this->import(
            $context,
            "date,description,reference,debit,credit,balance,external_id\n2040-01-10,Receipt,R-2,,50,150,\n",
            'third.csv',
            '100',
            '150'
        );

        $this->assertTrue($duplicate->lines->first()->is_duplicate);
        $this->assertSame('duplicate', $duplicate->lines->first()->status);
        $this->assertFalse($different->lines->first()->is_duplicate);
    }

    private function context(): array
    {
        $currency = Currency::query()->create([
            'code' => strtoupper(substr(hash('sha1', uniqid('', true)), 0, 3)),
            'name_ar' => 'Currency', 'name_en' => 'Currency', 'symbol' => 'C',
            'decimal_places' => 2, 'is_active' => true,
        ]);
        $company = Company::query()->create(['name' => 'Reconciliation '.uniqid(), 'currency_id' => $currency->id]);
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
        $this->actingAs($user);
        app(TenantContext::class)->initialize($user);
        app(ReferenceDataSeeder::class)->run();
        $method = new PaymentMethod;
        $method->forceFill([
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
        app(BankReconciliationSeeder::class)->run();
        $account = app(BankAccountService::class)->create([
            'bank_id' => Bank::query()->where('is_system', true)->value('id'),
            'branch_id' => null, 'account_code' => 'BANK-'.uniqid(), 'account_name' => 'Operating Bank',
            'iban' => 'SA'.str_pad((string) random_int(1, 999999999), 22, '0'),
            'currency_id' => $currency->id,
            'gl_account_id' => Account::query()->where('company_id', $company->id)->where('account_code', '112000')->value('id'),
            'account_type' => 'current', 'is_primary' => false, 'allows_receipts' => true,
            'allows_payments' => true, 'allows_transfers' => true, 'requires_reconciliation' => true,
        ]);
        $account = app(BankAccountService::class)->action($account, 'activate', 'Reconciliation test');
        app(BankAccountAccessService::class)->save($account, [
            'branch_id' => $branch->id, 'can_view' => true, 'can_receive' => true,
            'can_pay' => true, 'can_transfer' => true, 'daily_payment_limit' => 100000,
            'daily_transfer_limit' => 100000, 'is_active' => true,
        ]);
        $profile = BankStatementImportProfile::query()->where('company_id', $company->id)
            ->whereNull('bank_account_id')->where('is_default', true)->firstOrFail();

        return compact('currency', 'company', 'branch', 'role', 'user', 'year', 'account', 'profile');
    }

    private function import(
        array $context,
        string $csv,
        string $name,
        string $opening,
        string $closing
    ): BankStatementImport {
        return app(BankStatementImportService::class)->import(
            $context['account'], $context['profile'], UploadedFile::fake()->createWithContent($name, $csv),
            [
                'statement_reference' => pathinfo($name, PATHINFO_FILENAME),
                'period_start' => '2040-01-01', 'period_end' => '2040-01-31',
                'opening_balance' => $opening, 'closing_balance' => $closing,
                'currency_id' => $context['currency']->id,
            ]
        );
    }
}
