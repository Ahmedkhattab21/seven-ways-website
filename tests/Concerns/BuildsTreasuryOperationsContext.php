<?php

namespace Tests\Concerns;

use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\BankAccountAccessService;
use App\Services\BankAccountService;
use App\Services\FiscalPeriodGenerationService;
use Database\Seeders\AccountingFoundationSeeder;
use Database\Seeders\AccountingPostingSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\TreasuryFoundationSeeder;
use Database\Seeders\TreasuryOperationsSeeder;

trait BuildsTreasuryOperationsContext
{
    protected function treasuryContext(): array
    {
        $currency = Currency::query()->create([
            'code' => strtoupper(substr(hash('sha1', uniqid('', true)), 0, 3)),
            'name_ar' => 'Test currency', 'name_en' => 'Test currency', 'symbol' => 'T',
            'decimal_places' => 2, 'is_active' => true,
        ]);
        $company = Company::query()->create(['name' => 'Treasury Operations '.uniqid(), 'currency_id' => $currency->id]);
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
        $user = $this->treasuryUser($company, $branch, $ownerRole);
        $approver = $this->treasuryUser($company, $branch, $ownerRole);
        $cashier = $this->treasuryUser($company, $branch, $cashierRole);
        $this->switchTreasuryActor($user);
        app(ReferenceDataSeeder::class)->run();
        $method = new PaymentMethod;
        $method->forceFill([
            'company_id' => $company->id, 'code' => 'CARD', 'name' => 'Card',
            'type' => 'card', 'requires_reference' => true, 'is_cash' => false,
            'is_active' => true, 'sort_order' => 1,
        ])->save();
        app(AccountingFoundationSeeder::class)->run();
        app(AccountingPostingSeeder::class)->run();
        app(TreasuryFoundationSeeder::class)->run();
        app(TreasuryOperationsSeeder::class)->run();
        $year = FiscalYear::factory()->create([
            'company_id' => $company->id, 'code' => 'FY-2040', 'name' => 'FY 2040',
            'start_date' => '2040-01-01', 'end_date' => '2040-12-31',
            'status' => 'open', 'is_current' => true, 'created_by' => $user->id,
        ]);
        app(FiscalPeriodGenerationService::class)->monthly($year);
        $period = $year->periods()->where('period_number', 1)->firstOrFail();

        return compact(
            'currency', 'company', 'branch', 'secondBranch', 'user', 'approver',
            'cashier', 'method', 'year', 'period'
        );
    }

    protected function treasuryUser(Company $company, Branch $branch, Role $role): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);

        return $user;
    }

    protected function switchTreasuryActor(User $user): void
    {
        $this->actingAs($user);
        app(TenantContext::class)->initialize($user);
    }

    protected function treasuryAccount(array $context, string $code): Account
    {
        return Account::query()->where('company_id', $context['company']->id)
            ->where('account_code', $code)->firstOrFail();
    }

    protected function activeTreasuryBank(array $context, string $code = 'BANK-1'): BankAccount
    {
        $service = app(BankAccountService::class);
        $account = $service->create([
            'bank_id' => Bank::query()->where('is_system', true)->value('id'),
            'branch_id' => null, 'account_code' => $code, 'account_name' => $code,
            'iban' => null, 'currency_id' => $context['currency']->id,
            'gl_account_id' => $this->treasuryAccount($context, '112000')->id,
            'bank_fees_account_id' => $this->treasuryAccount($context, '651000')->id,
            'account_type' => 'current', 'is_primary' => false,
            'allows_receipts' => true, 'allows_payments' => true,
            'allows_transfers' => true, 'requires_reconciliation' => true,
        ]);
        $account = $service->action($account, 'activate', 'Activate treasury test account');
        app(BankAccountAccessService::class)->save($account, [
            'branch_id' => $context['branch']->id, 'can_view' => true, 'can_receive' => true,
            'can_pay' => true, 'can_transfer' => true, 'daily_transfer_limit' => 100000,
            'is_active' => true,
        ]);

        return $account;
    }

    protected function secondTreasuryBank(array $context): BankAccount
    {
        $parent = $this->treasuryAccount($context, '112000');
        $gl = $parent->replicate(['uuid', 'account_code', 'account_path', 'created_at', 'updated_at']);
        $gl->forceFill([
            'account_code' => '112-TEST-'.substr(uniqid(), -5), 'name_ar' => 'Second bank test',
            'parent_account_id' => $parent->parent_account_id, 'is_bank_account' => true,
            'is_cash_account' => false, 'is_header' => false, 'is_posting' => true,
            'created_by' => $context['user']->id,
        ])->save();
        $gl->forceFill(['account_path' => $parent->parent->account_path.'/'.$gl->id])->saveQuietly();
        $service = app(BankAccountService::class);
        $account = $service->create([
            'bank_id' => Bank::query()->where('is_system', true)->value('id'),
            'branch_id' => null, 'account_code' => 'BANK-2', 'account_name' => 'Second bank',
            'iban' => null, 'currency_id' => $context['currency']->id, 'gl_account_id' => $gl->id,
            'bank_fees_account_id' => $this->treasuryAccount($context, '651000')->id,
            'account_type' => 'current', 'is_primary' => false,
            'allows_receipts' => true, 'allows_payments' => true, 'allows_transfers' => true,
            'requires_reconciliation' => true,
        ]);
        $account = $service->action($account, 'activate', 'Activate second treasury account');
        app(BankAccountAccessService::class)->save($account, [
            'branch_id' => $context['branch']->id, 'can_view' => true, 'can_receive' => true,
            'can_pay' => true, 'can_transfer' => true, 'daily_transfer_limit' => 100000,
            'is_active' => true,
        ]);

        return $account;
    }
}
