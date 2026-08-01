<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\OpeningBalanceDocument;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CompanySetupProgressService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SetupCompletionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_inventory_step_is_included_only_when_basic_inventory_is_enabled(): void
    {
        [$company] = $this->completeContext();

        Config::set('modules.basic_inventory.enabled', true);
        $enabled = app(CompanySetupProgressService::class)->for($company);
        $this->assertSame(11, $enabled['total']);
        $this->assertNotNull(collect($enabled['steps'])->firstWhere('label', 'المستودعات'));

        Config::set('modules.basic_inventory.enabled', false);
        $disabled = app(CompanySetupProgressService::class)->for($company);
        $this->assertSame(10, $disabled['total']);
        $this->assertNull(collect($disabled['steps'])->firstWhere('label', 'المستودعات'));
    }

    public function test_starting_from_zero_completes_the_step_without_fake_entries(): void
    {
        [$company, , $manager] = $this->completeContext(['opening_balances_decision' => 'pending']);
        $documents = OpeningBalanceDocument::query()->count();
        $journals = JournalEntry::query()->count();

        $this->actingAs($manager)
            ->post(route('accounting.opening-balances.start-from-zero'))
            ->assertRedirect();

        $this->assertSame('start_from_zero', $company->fresh()->opening_balances_decision);
        $this->assertSame($documents, OpeningBalanceDocument::query()->count());
        $this->assertSame($journals, JournalEntry::query()->count());
        $this->assertTrue(AuditLog::query()
            ->where('event', 'company.opening_balances_decision_changed')
            ->where('auditable_id', $company->id)
            ->exists());
        $step = collect(app(CompanySetupProgressService::class)->for($company->fresh())['steps'])
            ->firstWhere('label', 'الأرصدة الافتتاحية');
        $this->assertTrue($step['complete']);
        $this->assertSame('البدء من أرصدة صفرية', $step['details']['status_label']);
    }

    public function test_branch_responsible_cannot_change_opening_balance_decision(): void
    {
        [$company, , , $branchResponsible] = $this->completeContext(['opening_balances_decision' => 'pending']);
        $this->grant($branchResponsible->roles()->first(), 'companies.update');

        $this->actingAs($branchResponsible)
            ->post(route('accounting.opening-balances.start-from-zero'))
            ->assertForbidden();

        $this->assertSame('pending', $company->fresh()->opening_balances_decision);
    }

    public function test_missing_active_branch_responsible_keeps_roles_step_incomplete(): void
    {
        [$company, $branch] = $this->completeContext();
        $branch->forceFill(['responsible_user_id' => null])->save();

        $step = collect(app(CompanySetupProgressService::class)->for($company)['steps'])
            ->firstWhere('label', 'المستخدمون والأدوار');

        $this->assertFalse($step['complete']);
        $this->assertSame([$branch->name], $step['details']['missing_branch_responsibles']);
    }

    public function test_all_applicable_steps_reach_one_hundred_percent(): void
    {
        Config::set('modules.basic_inventory.enabled', true);
        [$company] = $this->completeContext();

        $progress = app(CompanySetupProgressService::class)->for($company);

        $this->assertTrue($progress['complete']);
        $this->assertTrue($progress['ready']);
        $this->assertSame(11, $progress['completed']);
        $this->assertSame(11, $progress['total']);
        $this->assertSame(100, $progress['percentage']);
    }

    public function test_tax_step_accepts_explicit_non_taxable_decision_without_a_tax_record(): void
    {
        [$company] = $this->completeContext(['is_taxable' => false]);

        $step = collect(app(CompanySetupProgressService::class)->for($company)['steps'])
            ->firstWhere('label', 'الضرائب');

        $this->assertTrue($step['complete']);
        $this->assertSame('الشركة غير خاضعة للضريبة', $step['details']['status_label']);
    }

    public function test_products_are_counted_but_do_not_block_core_readiness(): void
    {
        [$company] = $this->completeContext();
        Product::query()->where('company_id', $company->id)->delete();

        $progress = app(CompanySetupProgressService::class)->for($company);
        $step = collect($progress['steps'])->firstWhere('label', 'المنتجات');

        $this->assertFalse($step['complete']);
        $this->assertTrue($progress['ready']);
    }

    public function test_sidebar_displays_descriptive_full_completion(): void
    {
        [$company, , $manager] = $this->completeContext();
        foreach ([
            'dashboard.view', 'companies.view', 'branches.view', 'users.view', 'settings.view',
            'taxes.view', 'payment_methods.view', 'accounting.fiscal_years.view',
            'document_sequences.view', 'warehouses.view', 'products.view',
            'accounting.opening_balances.view',
        ] as $permission) {
            $this->grant($manager->roles()->first(), $permission);
        }

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('100%')
            ->assertSee('11 من 11 خطوات مكتملة')
            ->assertSee('البدء من أرصدة صفرية');
        $this->assertSame('start_from_zero', $company->fresh()->opening_balances_decision);
    }

    private function completeContext(array $companyOverrides = []): array
    {
        $currency = Currency::query()->firstOrCreate(
            ['code' => 'EGP'],
            [
                'name_ar' => 'جنيه مصري',
                'name_en' => 'Egyptian Pound',
                'symbol' => 'ج.م',
                'decimal_places' => 2,
                'is_active' => true,
            ]
        );
        $company = Company::query()->create(array_merge([
            'name' => 'Setup '.uniqid(),
            'country_code' => 'EG',
            'timezone' => 'Africa/Cairo',
            'currency_id' => $currency->id,
            'currency_code' => 'EGP',
            'is_taxable' => false,
            'opening_balances_decision' => 'start_from_zero',
            'is_active' => true,
        ], $companyOverrides));
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'MAIN'.uniqid(),
            'name' => 'الفرع الرئيسي',
            'is_main' => true,
            'is_active' => true,
        ]);

        $managerRole = $this->role($company, 'company_owner', 'company');
        $accountantRole = $this->role($company, 'accountant', 'company');
        $branchRole = $this->role($company, 'branch_manager', 'branch');
        $this->grant($managerRole, 'companies.update');
        $manager = $this->user($company, $branch, $managerRole);
        $this->user($company, $branch, $accountantRole);
        $branchResponsible = $this->user($company, $branch, $branchRole);
        $branch->forceFill([
            'responsible_user_id' => $branchResponsible->id,
            'responsible_assigned_at' => now(),
        ])->save();

        $payment = new PaymentMethod([
            'code' => 'CASH',
            'name' => 'نقدي',
            'type' => 'cash',
            'is_cash' => true,
            'is_active' => true,
        ]);
        $payment->company()->associate($company);
        $payment->save();

        $year = new FiscalYear([
            'code' => 'FY-'.uniqid(),
            'name' => 'السنة الحالية',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => 'open',
            'is_current' => true,
        ]);
        $year->company()->associate($company);
        $year->save();
        AccountingPeriod::query()->forceCreate([
            'company_id' => $company->id,
            'fiscal_year_id' => $year->id,
            'period_number' => 1,
            'code' => 'P01',
            'name' => 'فترة مفتوحة',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => 'open',
        ]);
        DocumentSequence::query()->forceCreate([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'document_type' => 'customer',
            'prefix' => 'CUS-',
            'current_number' => 0,
            'padding' => 6,
            'reset_period' => 'yearly',
            'period_key' => now()->format('Y'),
            'scope_key' => 'setup-'.uniqid(),
            'is_active' => true,
        ]);
        $unit = Unit::query()->forceCreate([
            'company_id' => $company->id,
            'code' => 'UNIT-'.uniqid(),
            'name' => 'وحدة',
            'symbol' => 'u',
            'unit_type' => 'quantity',
            'decimal_places' => 6,
            'is_system' => false,
            'is_active' => true,
        ]);
        $category = ProductCategory::query()->forceCreate([
            'company_id' => $company->id,
            'code' => 'CATEGORY-'.uniqid(),
            'name' => 'منتجات',
            'is_active' => true,
        ]);
        Product::query()->forceCreate([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'sku' => 'PRODUCT-'.uniqid(),
            'name' => 'منتج نشط',
            'product_type' => 'consumable',
            'tracking_type' => 'quantity',
            'purchase_unit_id' => $unit->id,
            'stock_unit_id' => $unit->id,
            'sale_unit_id' => $unit->id,
            'costing_method' => 'weighted_average',
            'minimum_stock' => 0,
            'is_sellable' => true,
            'is_purchasable' => true,
            'is_consumable' => true,
            'is_active' => true,
        ]);
        Warehouse::query()->forceCreate([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'code' => 'WH-'.uniqid(),
            'name' => 'المستودع الرئيسي',
            'warehouse_type' => 'main',
            'is_active' => true,
        ]);
        app(TenantContext::class)->initialize($manager);

        return [$company, $branch, $manager, $branchResponsible];
    }

    private function role(Company $company, string $name, string $scope): Role
    {
        return Role::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'display_name' => $name,
            'scope' => $scope,
            'is_active' => true,
        ]);
    }

    private function user(Company $company, Branch $branch, Role $role): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);

        return $user;
    }

    private function grant(Role $role, string $permission): void
    {
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->firstOrCreate(
                ['name' => $permission],
                ['display_name' => $permission]
            )
        );
    }
}
