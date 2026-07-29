<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_focused_sidebar_shows_enabled_core_modules(): void
    {
        [$company, $branch] = $this->companyContext();
        $user = $this->userWithPermissions($company, $branch, [
            'dashboard.view', 'customers.view', 'products.view', 'sales_invoices.view',
            'suppliers.view', 'warehouses.view', 'accounting.accounts.view',
            'companies.view', 'branches.view', 'users.view',
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('customers.index'), false)
            ->assertSee(route('catalog.index'), false)
            ->assertSee(route('sales-invoices.index'), false)
            ->assertSee(route('suppliers.index'), false)
            ->assertSee(route('warehouses.index'), false)
            ->assertSee(route('accounting.accounts.index'), false)
            ->assertSee(route('company.edit'), false);
    }

    public function test_permissions_keep_unavailable_sidebar_groups_hidden(): void
    {
        [$company, $branch] = $this->companyContext();
        $user = $this->userWithPermissions($company, $branch, [
            'dashboard.view', 'accounting.accounts.view',
        ]);

        $this->actingAs($user)->get(route('accounting.accounts.index'))
            ->assertOk()
            ->assertSee('data-sidebar-group-key="finance"', false)
            ->assertSee('data-sidebar-group-active="true"', false)
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('data-sidebar-group-key="purchasing_inventory"', false)
            ->assertDontSee('data-sidebar-group-key="settings"', false)
            ->assertDontSee(route('users.index'), false);
    }

    public function test_disabled_workshop_modules_are_hidden_and_blocked_directly(): void
    {
        [$company, $branch] = $this->companyContext();
        $user = $this->userWithPermissions($company, $branch, [
            'dashboard.view', 'appointments.view', 'work_orders.view', 'leads.view',
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('appointments.index'), false)
            ->assertDontSee(route('work-orders.index'), false)
            ->assertDontSee(route('leads.index'), false);

        $this->actingAs($user)->get(route('appointments.index'))->assertNotFound();
        $this->actingAs($user)->get(route('work-orders.index'))->assertNotFound();
        $this->actingAs($user)->get(route('leads.index'))->assertNotFound();
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
            'name' => 'Other branch',
            'is_main' => true,
            'is_active' => true,
        ]);
        $user = $this->userWithPermissions($company, $branch, ['dashboard.view', 'branches.view']);

        $this->actingAs($user)->get(route('branches.index'))
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
            'code' => 'MAIN'.uniqid(),
            'name' => 'Main branch',
            'is_main' => true,
            'is_active' => true,
        ]);

        return [$company, $branch];
    }

    private function userWithPermissions(Company $company, Branch $branch, array $permissions): User
    {
        $role = Role::query()->create([
            'company_id' => $company->id,
            'name' => 'navigation_'.uniqid(),
            'display_name' => 'Navigation',
            'scope' => 'branch',
            'is_active' => true,
        ]);
        foreach ($permissions as $name) {
            $role->permissions()->syncWithoutDetaching(
                Permission::query()->firstOrCreate(['name' => $name], ['display_name' => $name])
            );
        }
        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);

        return $user;
    }
}
