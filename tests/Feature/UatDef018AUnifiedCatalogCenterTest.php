<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UatDef018AUnifiedCatalogCenterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_catalog_center_displays_products_only(): void
    {
        [$company, $branch] = $this->companyContext();
        $user = $this->userWithPermissions($company, $branch, [
            'products.view', 'products.create', 'product_categories.manage', 'product_brands.manage',
            'services.view', 'services.create', 'service_categories.view', 'service_categories.manage',
            'service_packages.view', 'service_packages.create',
        ]);

        $this->actingAs($user)->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('المنتجات')
            ->assertSee(route('products.create'), false)
            ->assertDontSee(route('services.create'), false)
            ->assertDontSee(route('service-packages.create'), false)
            ->assertDontSee(route('service-categories.create'), false)
            ->assertDontSee(route('product-references.index', 'categories'), false)
            ->assertDontSee(route('product-references.index', 'brands'), false);
    }

    public function test_catalog_hides_unauthorized_tabs_and_actions(): void
    {
        [$company, $branch] = $this->companyContext();
        $user = $this->userWithPermissions($company, $branch, ['products.view']);

        $this->actingAs($user)->get(route('catalog.index'))
            ->assertOk()
            ->assertSee(route('products.index'), false)
            ->assertDontSee(route('services.index'), false)
            ->assertDontSee(route('service-packages.index'), false)
            ->assertDontSee(route('products.create'), false);
    }

    public function test_catalog_rejects_user_without_any_catalog_view_permission(): void
    {
        [$company, $branch] = $this->companyContext();
        $user = $this->userWithPermissions($company, $branch, ['dashboard.view']);

        $this->actingAs($user)->get(route('catalog.index'))->assertForbidden();
        $this->actingAs($user)->get(route('products.index'))->assertForbidden();
        $this->actingAs($user)->get(route('services.index'))->assertForbidden();
    }

    public function test_sidebar_uses_one_catalog_entry_and_keeps_all_catalog_routes_active(): void
    {
        $items = collect(config('sidebar'))->flatMap(fn (array $section) => $section['items']);
        $catalogItems = $items->where('route', 'catalog.index');

        $this->assertCount(1, $catalogItems);
        $this->assertSame('المنتجات', $catalogItems->first()['label']);
        $this->assertSame(
            ['catalog.*', 'products.*', 'product-references.*'],
            $catalogItems->first()['active']
        );
        $this->assertFalse($items->contains('route', 'products.index'));
        $this->assertFalse($items->contains('route', 'services.index'));
    }

    public function test_product_pages_do_not_expose_other_catalog_sections(): void
    {
        foreach ([
            resource_path('views/inventory/products/index.blade.php'),
            resource_path('views/inventory/products/form.blade.php'),
        ] as $view) {
            $this->assertStringNotContainsString('<x-catalog-navigation', file_get_contents($view), $view);
        }
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
            'name' => 'Catalog '.uniqid(),
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

    private function userWithPermissions(Company $company, Branch $branch, array $permissions): User
    {
        $role = Role::query()->create([
            'company_id' => $company->id,
            'name' => 'catalog_'.uniqid(),
            'display_name' => 'Catalog tester',
            'scope' => 'branch',
            'is_active' => true,
        ]);
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['display_name' => $name]);
            $role->permissions()->syncWithoutDetaching($permission);
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
