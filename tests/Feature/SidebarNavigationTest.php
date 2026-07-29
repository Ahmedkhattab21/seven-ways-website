<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanySetupProgressService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_sees_allowed_sections_and_real_setup_progress(): void
    {
        [$company, $branch] = $this->companyContext();
        $admin = $this->userWithPermissions($company, $branch, 'navigation_admin', [
            'dashboard.view',
            'customers.view',
            'products.view',
            'accounting.accounts.view',
            'reports.financial.view',
            'companies.view',
            'branches.view',
            'users.view',
            'settings.view',
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('إكمال إعداد النظام')
            ->assertSee('من 11 خطوات مكتملة')
            ->assertSee('التشغيل اليومي')
            ->assertSee('المخزون والمشتريات')
            ->assertSee('المالية والمحاسبة')
            ->assertSee('التقارير والإقفالات')
            ->assertSee('الإدارة والتحكم')
            ->assertSee('الإعدادات');
    }

    public function test_setup_section_disappears_after_all_steps_are_complete(): void
    {
        [$company, $branch] = $this->companyContext();
        $admin = $this->userWithPermissions($company, $branch, 'navigation_complete_admin', [
            'dashboard.view',
            'companies.view',
            'branches.view',
            'users.view',
        ]);
        $this->mock(CompanySetupProgressService::class, function ($mock) {
            $mock->shouldReceive('for')->once()->andReturn([
                'steps' => [],
                'completed' => 11,
                'total' => 11,
                'complete' => true,
            ]);
        });

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('إكمال إعداد النظام');
    }

    public function test_accountant_sees_finance_without_system_administration(): void
    {
        [$company, $branch] = $this->companyContext();
        $accountant = $this->userWithPermissions($company, $branch, 'navigation_accountant', [
            'dashboard.view',
            'accounting.accounts.view',
            'accounting.journals.view',
            'reports.financial.view',
        ]);

        $this->actingAs($accountant)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('المالية والمحاسبة')
            ->assertSee('التقارير والإقفالات')
            ->assertSee('دليل الحسابات')
            ->assertDontSee('الإدارة والتحكم')
            ->assertDontSee('إكمال إعداد النظام')
            ->assertDontSee(route('users.index'), false);
    }

    public function test_cashier_sees_daily_work_only_and_cannot_open_accounting_directly(): void
    {
        [$company, $branch] = $this->companyContext();
        $cashier = $this->userWithPermissions($company, $branch, 'navigation_cashier', [
            'dashboard.view',
            'sales_invoices.view',
            'customer_payments.view',
            'treasury.cash_sessions.view',
        ]);

        $this->actingAs($cashier)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('التشغيل اليومي')
            ->assertSee('فواتير المبيعات')
            ->assertSee('استلام المدفوعات')
            ->assertSee('جلسات الخزائن والجرد')
            ->assertDontSee('المالية والمحاسبة')
            ->assertDontSee('الإعدادات')
            ->assertDontSee('الإدارة والتحكم');

        $this->actingAs($cashier)->get(route('accounting.accounts.index'))->assertForbidden();
    }

    public function test_empty_groups_are_hidden_and_active_group_is_open(): void
    {
        [$company, $branch] = $this->companyContext();
        $user = $this->userWithPermissions($company, $branch, 'navigation_active', [
            'dashboard.view',
            'accounting.accounts.view',
        ]);

        $response = $this->actingAs($user)->get(route('accounting.accounts.index'));

        $response->assertOk()
            ->assertSee('data-sidebar-group-key="finance"', false)
            ->assertSee('data-sidebar-group-active="true"', false)
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('data-sidebar-group-key="inventory"', false)
            ->assertDontSee('data-sidebar-group-key="administration"', false);
    }

    public function test_sidebar_routes_exist_and_branch_page_keeps_company_isolation(): void
    {
        foreach (config('sidebar') as $section) {
            foreach ($section['items'] as $item) {
                $this->assertTrue(Route::has($item['route']), $item['route'].' is missing.');
            }
        }

        [$company, $branch] = $this->companyContext();
        $otherCompany = Company::query()->create(['name' => 'Other navigation company']);
        $otherBranch = Branch::query()->create([
            'company_id' => $otherCompany->id,
            'code' => 'OTHER',
            'name' => 'فرع شركة أخرى',
            'is_main' => true,
            'is_active' => true,
        ]);
        $admin = $this->userWithPermissions($company, $branch, 'navigation_branch_admin', [
            'dashboard.view',
            'branches.view',
        ]);

        $this->actingAs($admin)->get(route('branches.index'))
            ->assertOk()
            ->assertSee($branch->name)
            ->assertDontSee($otherBranch->name);
    }

    private function companyContext(): array
    {
        $currency = Currency::query()->create([
            'code' => strtoupper(substr(uniqid(), -3)),
            'name_ar' => 'عملة اختبار',
            'name_en' => 'Test currency',
            'symbol' => 'T',
            'decimal_places' => 2,
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'name' => 'Navigation '.uniqid(),
            'currency_id' => $currency->id,
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'MAIN',
            'name' => 'الفرع الرئيسي',
            'is_main' => true,
            'is_active' => true,
        ]);

        return [$company, $branch];
    }

    private function userWithPermissions(
        Company $company,
        Branch $branch,
        string $roleName,
        array $permissions
    ): User {
        $role = Role::query()->create([
            'company_id' => $company->id,
            'name' => $roleName.'_'.uniqid(),
            'display_name' => $roleName,
            'scope' => 'branch',
            'is_active' => true,
        ]);
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                ['display_name' => $name]
            );
            $role->permissions()->syncWithoutDetaching($permission);
        }
        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, [
            'is_default' => true,
            'can_view' => true,
        ]);

        return $user;
    }
}
