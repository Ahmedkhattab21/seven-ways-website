<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\InventoryCount;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryCountPermissionTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private Branch $alexandria;

    private Branch $cairo;

    private Warehouse $alexWarehouse;

    private Warehouse $cairoWarehouse;

    private Product $product;

    private User $manager;

    private User $owner;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create(['name' => 'Inventory count '.uniqid(), 'is_active' => true]);
        $this->alexandria = $this->branch('ALX', 'فرع الإسكندرية', true);
        $this->cairo = $this->branch('CAI', 'فرع القاهرة');
        $this->alexWarehouse = $this->warehouse($this->alexandria, 'ALEX-WH', 'مخزن الإسكندرية');
        $this->cairoWarehouse = $this->warehouse($this->cairo, 'CAI-WH', 'مخزن القاهرة');

        $unit = Unit::query()->forceCreate([
            'company_id' => $this->company->id,
            'code' => 'PCS-'.uniqid(),
            'name' => 'قطعة',
            'symbol' => 'قطعة',
            'unit_type' => 'quantity',
            'decimal_places' => 6,
            'is_active' => true,
        ]);
        $category = ProductCategory::query()->forceCreate([
            'company_id' => $this->company->id,
            'code' => 'COUNT-'.uniqid(),
            'name' => 'منتجات الجرد',
            'is_active' => true,
        ]);
        $this->product = Product::query()->forceCreate([
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'sku' => 'COUNT-SKU-'.uniqid(),
            'name' => 'منتج الجرد',
            'product_type' => 'consumable',
            'tracking_type' => 'quantity',
            'purchase_unit_id' => $unit->id,
            'stock_unit_id' => $unit->id,
            'sale_unit_id' => $unit->id,
            'costing_method' => 'weighted_average',
            'is_active' => true,
        ]);
        StockBalance::query()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->alexandria->id,
            'warehouse_id' => $this->alexWarehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 7,
            'available_quantity' => 7,
            'average_cost' => 20,
        ]);

        $this->sequence($this->alexandria);
        $this->sequence($this->cairo);
        $this->manager = $this->user('branch_manager', $this->alexandria, ['inventory.view', 'inventory.count']);
        $this->owner = $this->user('company_owner', $this->alexandria, ['inventory.view', 'inventory.count', 'inventory.post']);
        $this->viewer = $this->user('inventory_viewer', $this->alexandria, ['inventory.view']);
    }

    public function test_branch_manager_can_create_see_and_snapshot_own_inventory_count_without_stock_effect(): void
    {
        $this->actingAs($this->manager)->post(route('inventory.documents.store', 'counts'), [
            'warehouse_id' => $this->alexWarehouse->id,
            'date' => today()->toDateString(),
            'scope_type' => 'full',
        ])->assertRedirect();
        $count = InventoryCount::query()->where('branch_id', $this->alexandria->id)->latest('id')->firstOrFail();

        $this->actingAs($this->manager)->get(route('inventory.index', 'counts'))
            ->assertOk()
            ->assertSee('بدء الجرد')
            ->assertSee(route('inventory.counts.snapshot', $count), false)
            ->assertDontSee(route('inventory.counts.post', $count), false);

        $beforeBalance = StockBalance::query()->where('warehouse_id', $this->alexWarehouse->id)->value('quantity');
        $beforeMovements = StockMovement::query()->count();
        $this->actingAs($this->manager)->post(route('inventory.counts.snapshot', $count))
            ->assertRedirect(route('inventory.counts.show', $count))
            ->assertSessionHas('success', 'تم بدء الجرد وأخذ لقطة الأرصدة بنجاح.');

        $this->assertSame('counting', $count->fresh()->status);
        $this->assertSame($beforeBalance, StockBalance::query()->where('warehouse_id', $this->alexWarehouse->id)->value('quantity'));
        $this->assertSame($beforeMovements, StockMovement::query()->count());
        $this->assertDatabaseHas('inventory_count_items', [
            'inventory_count_id' => $count->id,
            'product_id' => $this->product->id,
            'system_quantity' => '7.000000',
        ]);
        $this->actingAs($this->manager)->get(route('inventory.counts.show', $count))
            ->assertOk()
            ->assertSee('الكمية المعدودة')
            ->assertDontSee('ترحيل الجرد');
    }

    public function test_branch_manager_records_counted_quantities_but_cannot_post(): void
    {
        $count = $this->draft($this->alexandria, $this->alexWarehouse);
        $this->actingAs($this->manager)->post(route('inventory.counts.snapshot', $count))->assertRedirect();
        $item = $count->items()->firstOrFail();

        $this->actingAs($this->manager)->put(route('inventory.counts.items.update', $count), [
            'items' => [$item->id => ['counted_quantity' => 6]],
        ])->assertRedirect(route('inventory.counts.show', $count));

        $this->assertSame('6.000000', $item->fresh()->counted_quantity);
        $this->assertSame($this->manager->id, $count->fresh()->counted_by);
        $this->assertNotNull($count->fresh()->counted_at);
        $this->assertFalse($this->manager->hasPermission('inventory.post'));
        $this->actingAs($this->manager)->post(route('inventory.counts.post', $count))->assertForbidden();
        $this->assertSame('counting', $count->fresh()->status);
    }

    public function test_branch_manager_cannot_start_another_branch_or_snapshot_twice(): void
    {
        $cairoCount = $this->draft($this->cairo, $this->cairoWarehouse);
        $this->actingAs($this->manager)->post(route('inventory.counts.snapshot', $cairoCount))->assertForbidden();
        $this->assertSame('draft', $cairoCount->fresh()->status);

        $alexCount = $this->draft($this->alexandria, $this->alexWarehouse);
        $this->actingAs($this->manager)->post(route('inventory.counts.snapshot', $alexCount))->assertRedirect();
        $this->actingAs($this->manager)->post(route('inventory.counts.snapshot', $alexCount))->assertForbidden();
        $this->assertSame(1, $alexCount->items()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_user_without_inventory_count_neither_sees_nor_calls_snapshot(): void
    {
        $count = $this->draft($this->alexandria, $this->alexWarehouse);

        $this->actingAs($this->viewer)->get(route('inventory.index', 'counts'))
            ->assertOk()
            ->assertDontSee('بدء الجرد');
        $this->actingAs($this->viewer)->post(route('inventory.counts.snapshot', $count))->assertForbidden();
        $this->assertSame('draft', $count->fresh()->status);
    }

    public function test_company_owner_can_start_and_post_only_after_count_submission(): void
    {
        $count = $this->draft($this->alexandria, $this->alexWarehouse);
        $this->actingAs($this->owner)->post(route('inventory.counts.snapshot', $count))->assertRedirect();
        $this->assertFalse($this->owner->can('post', $count->fresh()));
        $item = $count->items()->firstOrFail();
        $this->actingAs($this->owner)->put(route('inventory.counts.items.update', $count), [
            'items' => [$item->id => ['counted_quantity' => 7]],
        ])->assertRedirect();

        $this->assertTrue($this->owner->can('post', $count->fresh()));
        $this->actingAs($this->owner)->get(route('inventory.counts.show', $count))
            ->assertOk()
            ->assertSee('ترحيل الجرد');
    }

    public function test_InventoryCountPermission_migration_is_idempotent_and_never_grants_post_to_branch_manager(): void
    {
        $role = $this->manager->roles()->firstOrFail();
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->firstOrCreate(['name' => 'inventory.post'], ['display_name' => 'inventory.post'])
        );
        $migration = require database_path('migrations/2026_08_02_020000_separate_inventory_count_from_post_permission.php');

        $migration->up();
        $migration->up();

        $this->assertTrue($this->manager->hasPermission('inventory.count'));
        $this->assertFalse($this->manager->hasPermission('inventory.post'));
        $this->assertTrue($this->owner->hasPermission('inventory.post'));
    }

    private function draft(Branch $branch, Warehouse $warehouse): InventoryCount
    {
        return InventoryCount::query()->forceCreate([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'document_number' => $branch->code.'-IC-'.uniqid(),
            'status' => 'draft',
            'count_date' => today(),
            'scope_type' => 'full',
            'created_by' => $this->owner->id,
        ]);
    }

    private function user(string $roleName, Branch $branch, array $permissions): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $role = Role::query()->create([
            'company_id' => $this->company->id,
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
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);

        return $user;
    }

    private function branch(string $code, string $name, bool $main = false): Branch
    {
        $branch = Branch::query()->create([
            'company_id' => $this->company->id,
            'code' => $code.'-'.uniqid(),
            'name' => $name,
            'is_main' => $main,
            'is_active' => true,
        ]);
        $branch->settings()->create(['allow_negative_stock' => false]);

        return $branch;
    }

    private function warehouse(Branch $branch, string $code, string $name): Warehouse
    {
        return Warehouse::query()->forceCreate([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'code' => $code.'-'.uniqid(),
            'name' => $name,
            'warehouse_type' => 'main',
            'is_main' => true,
            'is_system' => false,
            'is_active' => true,
        ]);
    }

    private function sequence(Branch $branch): void
    {
        DocumentSequence::query()->forceCreate([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'document_type' => 'inventory_count',
            'prefix' => $branch->code.'-IC-',
            'current_number' => 0,
            'padding' => 6,
            'reset_period' => 'yearly',
            'period_key' => now()->format('Y'),
            'scope_key' => DocumentNumberService::scopeKey(
                $this->company->id,
                $branch->id,
                'inventory_count',
                now()->format('Y')
            ),
            'is_active' => true,
        ]);
    }
}
