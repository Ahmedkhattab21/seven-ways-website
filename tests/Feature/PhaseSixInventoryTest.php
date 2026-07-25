<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use App\Services\InventoryReservationService;
use App\Services\InventoryService;
use App\Services\ProductUnitConversionService;
use App\Services\RollConsumptionService;
use App\Services\RollScrapService;
use App\Services\RollService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PhaseSixInventoryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_weighted_average_and_issue_cost_are_recorded_append_only(): void
    {
        [$warehouse, $product] = $this->inventoryContext();
        $service = app(InventoryService::class);
        $service->receive($warehouse, $product, '10', '5', 'opening_balance');
        $service->receive($warehouse, $product, '10', '15', 'manual_receipt');
        $issue = $service->issue($warehouse, $product, '4', 'manual_issue');

        $this->assertSame('10.0000', $warehouse->balances()->first()->average_cost);
        $this->assertSame('10.0000', $issue->unit_cost);
        $this->assertSame('16.000000', $issue->balance_after);
        $this->expectException(BusinessRuleException::class);
        $issue->update(['notes' => 'forbidden']);
    }

    public function test_issue_cannot_exceed_available_and_reversal_restores_balance(): void
    {
        [$warehouse, $product] = $this->inventoryContext();
        $service = app(InventoryService::class);
        $receipt = $service->receive($warehouse, $product, '5', '20', 'opening_balance');
        try {
            $service->issue($warehouse, $product, '6', 'manual_issue');
            $this->fail('Expected negative stock prevention.');
        } catch (BusinessRuleException) {
            $this->assertSame('5.000000', $warehouse->balances()->first()->quantity);
        }
        $reversal = $service->reverse($receipt);
        $this->assertSame('0.000000', $warehouse->balances()->first()->quantity);
        $this->assertSame($receipt->id, $reversal->reversal_of_id);
    }

    public function test_roll_uses_actual_area_specific_cost_and_cannot_be_over_consumed(): void
    {
        [$warehouse, $product] = $this->inventoryContext('roll');
        $roll = app(RollService::class)->receive($warehouse, $product, [
            'width' => '1.5', 'original_length' => '20', 'total_cost' => '300',
        ]);
        app(RollConsumptionService::class)->consume($roll, '5', '7', '0.5');

        $this->assertSame('30.000000', $roll->fresh()->original_area);
        $this->assertSame('22.500000', $roll->fresh()->remaining_area);
        $this->assertSame('10.0000', $roll->fresh()->unit_cost_per_area);
        $this->expectException(BusinessRuleException::class);
        app(RollConsumptionService::class)->consume($roll->fresh(), '16', '24');
    }

    public function test_reservation_never_changes_on_hand_and_release_restores_available(): void
    {
        [$warehouse, $product] = $this->inventoryContext();
        app(InventoryService::class)->receive($warehouse, $product, '10', '5', 'opening_balance');
        $reservation = app(InventoryReservationService::class)->reserve(
            $warehouse, $product, '4', 'manual_inventory', 123
        );
        $balance = $warehouse->balances()->first();
        $this->assertSame('10.000000', $balance->quantity);
        $this->assertSame('6.000000', $balance->available_quantity);
        app(InventoryReservationService::class)->release($reservation);
        $this->assertSame('10.000000', $balance->fresh()->available_quantity);
        $this->assertSame('released', InventoryReservation::find($reservation->id)->status);
    }

    public function test_inventory_routes_require_authentication_and_permission(): void
    {
        $this->get(route('products.index'))->assertRedirect(route('login'));
        [$warehouse, $product, $user] = $this->inventoryContext();
        $this->actingAs($user)->get(route('products.index'))->assertForbidden();
    }

    public function test_product_unit_conversion_is_precise_and_rejects_conflicting_inverse(): void
    {
        [, $product] = $this->inventoryContext();
        $toUnit = Unit::query()->create([
            'company_id' => $product->company_id, 'code' => 'BOX'.uniqid(), 'name' => 'Box',
            'symbol' => 'box', 'unit_type' => 'quantity', 'decimal_places' => 6, 'is_active' => true,
        ]);
        $service = app(ProductUnitConversionService::class);
        $service->save($product, $product->stock_unit_id, $toUnit->id, '2.50000000');

        $this->assertSame('7.500000', $service->convert($product, $product->stock_unit_id, $toUnit->id, '3'));
        $this->expectException(BusinessRuleException::class);
        $service->save($product, $toUnit->id, $product->stock_unit_id, '0.50000000');
    }

    public function test_reusable_scrap_inherits_cost_deducts_roll_and_is_consumed_once(): void
    {
        [$warehouse, $product] = $this->inventoryContext('roll');
        $roll = app(RollService::class)->receive($warehouse, $product, [
            'width' => '1.5', 'original_length' => '20', 'total_cost' => '300',
        ]);
        $service = app(RollScrapService::class);
        $scrap = $service->create($roll, '0.5', '2');

        $this->assertSame('1.000000', $scrap->area);
        $this->assertSame('10.0000', $scrap->unit_cost_per_area);
        $this->assertSame('29.000000', $roll->fresh()->remaining_area);
        $service->consume($scrap);
        $this->expectException(BusinessRuleException::class);
        $service->consume($scrap->fresh());
    }

    private function inventoryContext(string $tracking = 'quantity'): array
    {
        $company = Company::query()->create(['name' => 'Inventory '.uniqid()]);
        $branch = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'MAIN'.uniqid(), 'name' => 'Main',
            'is_main' => true, 'is_active' => true,
        ]);
        $branch->settings()->create(['allow_negative_stock' => false]);
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $role = Role::query()->create([
            'company_id' => $company->id, 'name' => 'test_'.uniqid(),
            'display_name' => 'Test', 'scope' => 'branch', 'is_active' => true,
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);
        app(TenantContext::class)->initialize($user);
        $unit = Unit::query()->create([
            'company_id' => $company->id, 'code' => 'U'.uniqid(), 'name' => 'Unit',
            'symbol' => 'u', 'unit_type' => 'quantity', 'decimal_places' => 6, 'is_active' => true,
        ]);
        $category = ProductCategory::query()->forceCreate([
            'company_id' => $company->id, 'code' => 'CAT'.uniqid(), 'name' => 'Category', 'is_active' => true,
        ]);
        $product = Product::query()->forceCreate([
            'company_id' => $company->id, 'category_id' => $category->id, 'sku' => 'SKU'.uniqid(),
            'name' => 'Product', 'product_type' => $tracking === 'roll' ? 'ppf' : 'consumable',
            'tracking_type' => $tracking, 'purchase_unit_id' => $unit->id, 'stock_unit_id' => $unit->id,
            'sale_unit_id' => $unit->id, 'costing_method' => $tracking === 'roll' ? 'specific' : 'weighted_average',
            'minimum_stock' => 0, 'is_active' => true,
        ]);
        $warehouse = Warehouse::query()->forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'code' => 'WH'.uniqid(),
            'name' => 'Warehouse', 'warehouse_type' => 'main', 'is_main' => true, 'is_active' => true,
        ]);
        foreach (['stock_movement' => 'STK-', 'roll' => 'ROLL-', 'roll_scrap' => 'SCRAP-'] as $type => $prefix) {
            DocumentSequence::query()->forceCreate([
                'company_id' => $company->id, 'branch_id' => $branch->id, 'document_type' => $type,
                'prefix' => $prefix, 'current_number' => 0, 'padding' => 6, 'reset_period' => 'never',
                'scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, $type, null),
                'is_active' => true,
            ]);
        }

        return [$warehouse, $product, $user];
    }
}
