<?php

namespace Tests\Feature\PhaseTwentyOne;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\UsesPhaseTwentyOneUat;
use Tests\TestCase;

class PhaseTwentyOnePurchasingInventoryCycleTest extends TestCase
{
    use DatabaseTransactions;
    use UsesPhaseTwentyOneUat;

    public function test_inventory_movements_derive_balance_and_cross_branch_is_rejected(): void
    {
        $this->setUpUatContext('uat.warehouse@sevenways.test');
        $product = Product::query()->where('company_id', $this->uatCompany->id)
            ->where('sku', 'UAT-INSTALL-KIT')->firstOrFail();
        $cairo = Warehouse::query()->where('company_id', $this->uatCompany->id)
            ->where('code', 'UAT-CAI-MAIN')->firstOrFail();
        $giza = Warehouse::query()->where('company_id', $this->uatCompany->id)
            ->where('code', 'UAT-GIZ-MAIN')->firstOrFail();

        $receive = app(InventoryService::class)->receive(
            $cairo, $product, '10', '75', 'uat_goods_receipt', ['type' => 'goods_receipt_item', 'id' => 1]
        );
        $issue = app(InventoryService::class)->issue(
            $cairo, $product, '2', 'uat_work_order_issue', ['type' => 'work_order', 'id' => 2]
        );

        $balance = StockBalance::query()->where('warehouse_id', $cairo->id)
            ->where('product_id', $product->id)->firstOrFail();
        $this->assertSame('8.000000', $balance->quantity);
        $this->assertSame('8.000000', $balance->available_quantity);
        $this->assertSame('750.0000', $receive->total_cost);
        $this->assertSame('150.0000', $issue->total_cost);
        $this->assertSame(2, StockMovement::query()->where('company_id', $this->uatCompany->id)->count());
        $this->assertSame(5, Supplier::query()->where('company_id', $this->uatCompany->id)
            ->where('supplier_code', 'like', 'UAT-%')->count());

        $this->expectException(BusinessRuleException::class);
        app(InventoryService::class)->receive($giza, $product, '1', '75', 'uat_cross_branch');
    }

    public function test_transit_warehouses_are_system_only_and_regular_warehouses_start_empty(): void
    {
        $this->setUpUatContext();

        $this->assertSame(3, Warehouse::query()->where('company_id', $this->uatCompany->id)
            ->where('warehouse_type', 'transit')->where('is_system', true)->count());
        $this->assertSame(0, Warehouse::query()->where('company_id', $this->uatCompany->id)
            ->where('warehouse_type', 'transit')
            ->where(fn ($query) => $query->where('allows_sale_issue', true)
                ->orWhere('allows_work_order_issue', true))->count());
        $this->assertSame(0, StockBalance::query()->where('company_id', $this->uatCompany->id)->count());
        $this->assertSame(0, StockMovement::query()->where('company_id', $this->uatCompany->id)->count());
    }
}
