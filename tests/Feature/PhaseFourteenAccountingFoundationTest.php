<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\AccountCreated;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\AccountingPeriod;
use App\Models\AccountingSetting;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\OpeningBalanceDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingSettingsService;
use App\Services\BranchAccountingSettingsService;
use App\Services\ChartOfAccountsService;
use App\Services\CostCenterHierarchyService;
use App\Services\FiscalPeriodGenerationService;
use App\Services\FiscalYearService;
use App\Services\OpeningBalanceService;
use App\Services\PostingProfileService;
use Database\Seeders\AccountingFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseFourteenAccountingFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_accounting_seeder_is_idempotent_and_creates_no_posting_data(): void
    {
        $context = $this->context();
        $counts = $this->accountingCounts($context['company']->id);
        app(AccountingFoundationSeeder::class)->run();

        $this->assertSame($counts, $this->accountingCounts($context['company']->id));
        $this->assertSame(5, \App\Models\AccountType::whereNull('company_id')->count());
        $this->assertSame(0, OpeningBalanceDocument::where('company_id', $context['company']->id)->count());
        $this->assertSame(0, DB::table('journal_entries')->count());
        $this->assertFalse(Schema::hasTable('general_ledger'));
        $this->assertFalse(Schema::hasColumn('accounts', 'balance'));
        $system = Account::where('company_id', $context['company']->id)->where('account_code', '100000')->firstOrFail();
        $this->expectException(BusinessRuleException::class);
        app(ChartOfAccountsService::class)->save(
            $system,
            $this->accountData('100001', $system->account_type_id, $system->account_group_id, true)
        );
    }

    public function test_chart_hierarchy_enforces_header_posting_type_and_cycle_rules(): void
    {
        $context = $this->context();
        $service = app(ChartOfAccountsService::class);
        $type = \App\Models\AccountType::where('code', 'ASSET')->firstOrFail();
        $group = AccountGroup::where('company_id', $context['company']->id)->where('code', '100')->firstOrFail();
        $header = $service->save(new Account, $this->accountData('790000', $type->id, $group->id, true));
        $posting = $service->save(new Account, $this->accountData('791000', $type->id, $group->id, false, $header->id));
        $this->assertSame($header->id.'/'.$posting->id, $posting->account_path);
        try {
            $invalid = $this->accountData('791500', $type->id, $group->id, true);
            $invalid['is_posting'] = true;
            $service->save(new Account, $invalid);
            $this->fail('Header and posting cannot both be enabled.');
        } catch (BusinessRuleException) {
            $this->assertDatabaseMissing('accounts', ['company_id' => $context['company']->id, 'account_code' => '791500']);
        }

        $this->expectException(BusinessRuleException::class);
        $service->save(new Account, $this->accountData('792000', $type->id, $group->id, false, $posting->id));
    }

    public function test_account_move_prevents_cycles_and_cross_company_parent(): void
    {
        $context = $this->context();
        $service = app(ChartOfAccountsService::class);
        $type = \App\Models\AccountType::where('code', 'ASSET')->firstOrFail();
        $group = AccountGroup::where('company_id', $context['company']->id)->where('code', '100')->firstOrFail();
        $root = $service->save(new Account, $this->accountData('780000', $type->id, $group->id, true));
        $child = $service->save(new Account, $this->accountData('781000', $type->id, $group->id, true, $root->id));

        try {
            $service->move($root, $child);
            $this->fail('Cycle must be rejected.');
        } catch (BusinessRuleException) {
            $this->assertNull($root->fresh()->parent_account_id);
        }

        $other = Company::query()->create(['name' => 'Other '.uniqid()]);
        $foreign = Account::query()->forceCreate([
            'company_id' => $other->id, 'account_type_id' => $type->id, 'account_code' => '100',
            'name_ar' => 'Foreign', 'account_level' => 0, 'account_path' => 'x',
            'is_header' => true, 'is_posting' => false, 'normal_balance' => 'debit',
            'created_by' => $context['user']->id,
        ]);
        $this->expectException(BusinessRuleException::class);
        $service->move($child, $foreign);
    }

    public function test_fiscal_period_generation_handles_non_january_leap_year_and_is_idempotent(): void
    {
        $context = $this->context();
        $year = app(FiscalYearService::class)->save(new FiscalYear, $context['company']->id, $context['user'], [
            'code' => 'FY-LEAP', 'name' => 'Leap', 'start_date' => '2031-07-01',
            'end_date' => '2032-06-30', 'is_current' => false,
        ]);
        $periods = app(FiscalPeriodGenerationService::class)->monthly($year);
        $this->assertCount(12, $periods);
        $february = $periods->first(fn (AccountingPeriod $period) => $period->start_date->toDateString() === '2032-02-01');
        $this->assertSame('2032-02-29', $february->end_date->toDateString());
        $this->assertCount(12, app(FiscalPeriodGenerationService::class)->monthly($year));
        app(FiscalYearService::class)->open($year->fresh(), $context['user']);
        $this->assertSame('open', $year->fresh()->status);

        $this->expectException(ValidationException::class);
        app(FiscalYearService::class)->save(new FiscalYear, $context['company']->id, $context['user'], [
            'code' => 'OVERLAP', 'name' => 'Overlap', 'start_date' => '2032-01-01',
            'end_date' => '2032-12-31', 'is_current' => false,
        ]);
    }

    public function test_cost_center_tree_prevents_cycles(): void
    {
        $context = $this->context();
        $root = CostCenter::where('company_id', $context['company']->id)->where('code', 'COMPANY')->firstOrFail();
        $branch = CostCenter::where('company_id', $context['company']->id)->where('branch_id', $context['branch']->id)->firstOrFail();
        $this->expectException(BusinessRuleException::class);
        app(CostCenterHierarchyService::class)->move($root, $branch);
    }

    public function test_settings_and_branch_mappings_reject_invalid_accounts(): void
    {
        $context = $this->context();
        $settings = AccountingSetting::where('company_id', $context['company']->id)->firstOrFail();
        app(AccountingSettingsService::class)->update([
            'base_currency_id' => $context['currency']->id, 'current_fiscal_year_id' => null,
            'default_rounding_precision' => 4, 'allow_manual_journals' => false,
            'require_journal_approval' => true, 'enforce_balanced_dimensions' => true,
            'enforce_cost_center_on_expense' => false, 'enforce_branch_on_posting' => true,
            'allow_posting_to_soft_closed_period' => false, 'separation_of_duties' => true,
        ]);
        $this->assertFalse($settings->fresh()->auto_post_sales);

        $expense = Account::where('company_id', $context['company']->id)->where('account_code', '610000')->firstOrFail();
        $this->expectException(BusinessRuleException::class);
        app(BranchAccountingSettingsService::class)->update($context['branch'], [
            'accounts_receivable_account_id' => $expense->id,
        ]);
    }

    public function test_posting_profile_versions_and_activation_create_no_journal(): void
    {
        $context = $this->context();
        $debit = Account::where('company_id', $context['company']->id)->where('account_code', '113000')->firstOrFail();
        $credit = Account::where('company_id', $context['company']->id)->where('account_code', '410000')->firstOrFail();
        $service = app(PostingProfileService::class);
        $profile = $service->create([
            'code' => 'SALES', 'name' => 'Sales profile', 'source_type' => 'sales_invoice', 'is_default' => true,
        ], [
            ['entry_side' => 'debit', 'account_source' => 'fixed_account', 'fixed_account_id' => $debit->id, 'amount_source' => 'total', 'tax_component' => 'none'],
            ['entry_side' => 'credit', 'account_source' => 'fixed_account', 'fixed_account_id' => $credit->id, 'amount_source' => 'total', 'tax_component' => 'none'],
        ]);
        $service->activate($profile);
        $next = $service->create([
            'code' => 'SALES', 'name' => 'Sales profile v2', 'source_type' => 'sales_invoice', 'is_default' => true,
        ], [
            ['entry_side' => 'debit', 'account_source' => 'fixed_account', 'fixed_account_id' => $debit->id, 'amount_source' => 'total'],
            ['entry_side' => 'credit', 'account_source' => 'fixed_account', 'fixed_account_id' => $credit->id, 'amount_source' => 'total'],
        ]);
        $this->assertSame(2, $next->version);
        $this->assertSame(0, DB::table('journal_entries')->count());
    }

    public function test_opening_balance_requires_balance_separation_and_becomes_immutable_ready_foundation(): void
    {
        $context = $this->context();
        $year = app(FiscalYearService::class)->save(new FiscalYear, $context['company']->id, $context['user'], [
            'code' => 'FY-OB', 'name' => 'Opening', 'start_date' => '2035-01-01',
            'end_date' => '2035-12-31', 'is_current' => false,
        ]);
        $service = app(OpeningBalanceService::class);
        $document = $service->create([
            'branch_id' => $context['branch']->id, 'fiscal_year_id' => $year->id,
            'balance_date' => '2035-01-01', 'description' => 'Opening',
        ]);
        $cash = Account::where('company_id', $context['company']->id)->where('account_code', '111000')->firstOrFail();
        $capital = Account::where('company_id', $context['company']->id)->where('account_code', '310000')->firstOrFail();
        $service->addLine($document, [
            'account_id' => $cash->id, 'currency_id' => $context['currency']->id,
            'exchange_rate' => 1, 'debit_amount' => 100, 'credit_amount' => 0,
        ]);
        try {
            $service->action($document, 'submit');
            $this->fail('Unbalanced opening balance cannot be submitted.');
        } catch (BusinessRuleException) {
            $this->assertSame('draft', $document->fresh()->status);
        }
        $service->addLine($document, [
            'account_id' => $capital->id, 'currency_id' => $context['currency']->id,
            'exchange_rate' => 1, 'debit_amount' => 0, 'credit_amount' => 100,
        ]);
        $service->action($document, 'submit');
        try {
            $service->action($document->fresh(), 'approve');
            $this->fail('Creator cannot approve under separation of duties.');
        } catch (BusinessRuleException) {
            $this->assertSame('pending_approval', $document->fresh()->status);
        }
        $this->asUser($context['approver']);
        $service->action($document->fresh(), 'approve');
        $service->action($document->fresh(), 'mark_ready');
        $this->assertSame('ready_for_posting', $document->fresh()->status);
        $this->assertNull($document->fresh()->posted_at);
        $this->expectException(BusinessRuleException::class);
        $service->addLine($document->fresh(), [
            'account_id' => $cash->id, 'currency_id' => $context['currency']->id,
            'exchange_rate' => 1, 'debit_amount' => 1, 'credit_amount' => 0,
        ]);
    }

    public function test_mass_assignment_and_cross_company_routes_are_protected(): void
    {
        $context = $this->context();
        $account = Account::where('company_id', $context['company']->id)->firstOrFail();
        $account->fill(['company_id' => 999999, 'account_level' => 99, 'status' => 'posted']);
        $this->assertSame($context['company']->id, $account->company_id);
        $this->assertNotSame(99, $account->account_level);

        $outsider = User::factory()->create(['company_id' => null, 'branch_id' => null, 'status' => 'active']);
        $this->actingAs($outsider)->get(route('accounting.accounts.edit', $account))->assertForbidden();
    }

    public function test_account_event_is_not_dispatched_when_outer_transaction_rolls_back(): void
    {
        $context = $this->context();
        Event::fake([AccountCreated::class]);
        $type = \App\Models\AccountType::where('code', 'ASSET')->firstOrFail();
        $group = AccountGroup::where('company_id', $context['company']->id)->where('code', '100')->firstOrFail();
        DB::beginTransaction();
        app(ChartOfAccountsService::class)->save(new Account, $this->accountData('799000', $type->id, $group->id, false));
        DB::rollBack();
        Event::assertNotDispatched(AccountCreated::class);
        $this->assertDatabaseMissing('accounts', ['company_id' => $context['company']->id, 'account_code' => '799000']);
    }

    private function context(): array
    {
        $currency = Currency::query()->create([
            'code' => strtoupper(substr(md5(uniqid()), 0, 3)), 'name_ar' => 'عملة',
            'name_en' => 'Currency', 'symbol' => 'C', 'decimal_places' => 2, 'is_active' => true,
        ]);
        $company = Company::query()->create(['name' => 'Accounting '.uniqid(), 'currency_id' => $currency->id]);
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
        $approver = User::factory()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $approver->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);
        $approver->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);
        app(AccountingFoundationSeeder::class)->run();
        $this->asUser($user);

        return compact('currency', 'company', 'branch', 'user', 'approver');
    }

    private function asUser(User $user): void
    {
        $this->actingAs($user);
        app(TenantContext::class)->initialize($user);
    }

    private function accountData(string $code, int $typeId, int $groupId, bool $header, ?int $parentId = null): array
    {
        return [
            'account_type_id' => $typeId, 'account_group_id' => $groupId,
            'parent_account_id' => $parentId, 'account_code' => $code, 'name_ar' => $code,
            'is_header' => $header, 'is_posting' => ! $header, 'allows_multi_currency' => false,
            'requires_cost_center' => false, 'requires_branch' => false, 'requires_customer' => false,
            'requires_supplier' => false, 'requires_employee' => false, 'requires_vehicle' => false,
            'is_control_account' => false, 'is_bank_account' => false, 'is_cash_account' => false,
            'is_inventory_account' => false, 'is_tax_account' => false, 'is_active' => true,
            'allow_manual_entry' => ! $header,
        ];
    }

    private function accountingCounts(int $companyId): array
    {
        return [
            AccountGroup::where('company_id', $companyId)->count(),
            Account::where('company_id', $companyId)->count(),
            CostCenter::where('company_id', $companyId)->count(),
            \App\Models\BranchAccountingSetting::where('company_id', $companyId)->count(),
            AccountingSetting::where('company_id', $companyId)->count(),
        ];
    }
}
