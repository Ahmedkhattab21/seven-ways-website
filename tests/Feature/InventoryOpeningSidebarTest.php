<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockOpeningDocument;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryOpeningSidebarTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_sees_opening_balances_link_in_order_and_can_open_list_and_create_pages(): void
    {
        [$owner] = $this->context(['inventory.opening'], 'company_owner');

        $response = $this->actingAs($owner)->get(route('inventory.index', 'openings'));

        $response->assertOk()
            ->assertSee('الأرصدة الافتتاحية للمخزون')
            ->assertSee('إضافة رصيد افتتاحي')
            ->assertSee('data-sidebar-group-key="purchasing_inventory"', false)
            ->assertSee('data-sidebar-group-active="true"', false)
            ->assertSee('aria-current="page"', false);

        $this->assertSidebarOrder();

        $this->actingAs($owner)->get(route('inventory.documents.create', 'openings'))
            ->assertOk()
            ->assertSee('إضافة رصيد افتتاحي')
            ->assertSee('aria-current="page"', false);
    }

    public function test_branch_manager_without_opening_permission_cannot_see_or_open_openings(): void
    {
        [$manager] = $this->context(['dashboard.view', 'inventory.view'], 'branch_manager');

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('inventory.index', 'openings'), false);

        $this->actingAs($manager)->get(route('inventory.index', 'openings'))->assertForbidden();
        $this->actingAs($manager)->get(route('inventory.documents.create', 'openings'))->assertForbidden();
    }

    public function test_opening_details_keep_sidebar_item_active(): void
    {
        [$owner, $company, $branch, $warehouse] = $this->context(['inventory.opening'], 'company_owner');
        $opening = StockOpeningDocument::query()->forceCreate([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'document_number' => 'OPEN-'.uniqid(),
            'status' => 'draft',
            'opening_date' => today(),
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)->get(route('inventory.openings.show', $opening))
            ->assertOk()
            ->assertSee($opening->document_number)
            ->assertSee('data-sidebar-group-active="true"', false)
            ->assertSee('aria-current="page"', false);
    }

    private function assertSidebarOrder(): void
    {
        $items = collect(config('sidebar'))->firstWhere('key', 'purchasing_inventory')['items'];
        $routes = collect($items)->map(fn (array $item) => [$item['route'], $item['params'] ?? []])->values();
        $positions = [
            $routes->search(fn (array $item) => $item[0] === 'purchase-orders.index'),
            $routes->search(fn (array $item) => $item[0] === 'goods-receipts.index'),
            $routes->search(fn (array $item) => $item[0] === 'warehouses.index'),
            $routes->search(fn (array $item) => $item[0] === 'inventory.index' && $item[1] === ['openings']),
            $routes->search(fn (array $item) => $item[0] === 'inventory.index' && $item[1] === ['balances']),
            $routes->search(fn (array $item) => $item[0] === 'inventory.index' && $item[1] === ['counts']),
        ];

        $this->assertSame($positions, collect($positions)->sort()->values()->all());
    }

    private function context(array $permissions, string $roleName): array
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
            'name' => 'Opening '.uniqid(),
            'currency_id' => $currency->id,
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'BR'.uniqid(),
            'name' => 'Opening branch',
            'is_main' => true,
            'is_active' => true,
        ]);
        $warehouse = Warehouse::query()->forceCreate([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'code' => 'WH'.uniqid(),
            'name' => 'Opening warehouse',
            'warehouse_type' => 'main',
            'is_active' => true,
            'is_system' => false,
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id,
            'name' => $roleName,
            'display_name' => $roleName,
            'scope' => $roleName === 'branch_manager' ? 'branch' : 'company',
            'is_active' => true,
        ]);
        foreach ($permissions as $permission) {
            $role->permissions()->syncWithoutDetaching(
                Permission::query()->firstOrCreate(['name' => $permission], ['display_name' => $permission])
            );
        }
        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);

        return [$user, $company, $branch, $warehouse];
    }
}
