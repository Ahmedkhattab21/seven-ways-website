<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\InventoryDocumentController;
use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\InventoryReservation;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\RollScrapService;
use App\Services\RollService;
use App\Services\StockTransferApprovalService;
use App\Services\StockTransferCancellationService;
use App\Services\StockTransferPreparationService;
use App\Services\StockTransferReceivingService;
use App\Services\StockTransferReversalService;
use App\Services\StockTransferService;
use App\Services\StockTransferShipmentService;
use App\Services\WarehouseService;
use Database\Seeders\StockTransferSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PhaseSevenStockTransferTest extends TestCase
{
    use DatabaseTransactions;

    public function test_draft_has_no_stock_effect_and_invalid_warehouse_scopes_are_rejected(): void
    {
        $context = $this->context();
        app(InventoryService::class)->receive($context['source'], $context['product'], '10', '5', 'opening_balance');
        $transfer = $this->draft($context, '3');

        $this->assertSame('draft', $transfer->status);
        $this->assertSame('10.000000', $this->balance($context['source'], $context['product'])->quantity);
        $this->assertSame('0.000000', $this->balance($context['destination'], $context['product'])->quantity);

        $this->expectException(BusinessRuleException::class);
        app(StockTransferService::class)->create([
            'from_warehouse_id' => $context['source']->id,
            'to_warehouse_id' => $context['source']->id,
            'items' => [['product_id' => $context['product']->id, 'item_type' => 'quantity', 'requested_quantity' => 1]],
        ]);
    }

    public function test_cross_company_destination_is_rejected(): void
    {
        $context = $this->context();
        $foreignCompany = Company::query()->create(['name' => 'Foreign '.uniqid()]);
        $foreignBranch = Branch::query()->create([
            'company_id' => $foreignCompany->id, 'code' => 'F'.uniqid(), 'name' => 'Foreign',
            'is_main' => true, 'is_active' => true,
        ]);
        $foreignWarehouse = Warehouse::query()->forceCreate([
            'company_id' => $foreignCompany->id, 'branch_id' => $foreignBranch->id,
            'code' => 'FWH'.uniqid(), 'name' => 'Foreign', 'warehouse_type' => 'main',
            'is_main' => true, 'is_active' => true, 'is_system' => false,
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(StockTransferService::class)->create([
            'from_warehouse_id' => $context['source']->id,
            'to_warehouse_id' => $foreignWarehouse->id,
            'items' => [['product_id' => $context['product']->id, 'item_type' => 'quantity', 'requested_quantity' => 1]],
        ]);
    }

    public function test_approval_reserves_once_and_cancellation_releases(): void
    {
        $context = $this->context();
        app(InventoryService::class)->receive($context['source'], $context['product'], '10', '5', 'opening_balance');
        $transfer = $this->submitted($context, '4');
        app(StockTransferApprovalService::class)->approve($transfer);

        $balance = $this->balance($context['source'], $context['product']);
        $this->assertSame('4.000000', $balance->reserved_quantity);
        $this->assertSame('6.000000', $balance->available_quantity);
        $this->assertSame(1, InventoryReservation::query()->where('reference_type', 'stock_transfer')
            ->where('reference_id', $transfer->id)->where('status', 'active')->count());

        try {
            app(StockTransferApprovalService::class)->approve($transfer->fresh());
            $this->fail('Expected duplicate approval to fail.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, InventoryReservation::query()->where('reference_id', $transfer->id)->count());
        }

        app(StockTransferCancellationService::class)->cancel($transfer->fresh(), 'Cancelled before shipment.');
        $this->assertSame('0.000000', $balance->fresh()->reserved_quantity);
        $this->assertSame('released', InventoryReservation::query()->where('reference_id', $transfer->id)->value('status'));
    }

    public function test_partial_preparation_stays_preparing_until_the_full_approved_quantity_is_ready(): void
    {
        $context = $this->context();
        app(InventoryService::class)->receive($context['source'], $context['product'], '5', '5', 'opening_balance');
        $transfer = $this->submitted($context, '5');
        app(StockTransferApprovalService::class)->approve($transfer);
        $item = $transfer->items()->first();

        app(StockTransferPreparationService::class)->prepare($transfer->fresh(), [$item->id => '2']);
        $this->assertSame('preparing', $transfer->fresh()->status);
        app(StockTransferPreparationService::class)->prepare($transfer->fresh(), [$item->id => '5']);
        $this->assertSame('ready_to_ship', $transfer->fresh()->status);
    }

    public function test_quantity_ships_through_transit_and_supports_partial_receiving(): void
    {
        $context = $this->context();
        app(InventoryService::class)->receive($context['source'], $context['product'], '10', '7.5', 'opening_balance');
        $transfer = $this->ready($context, '6');
        app(StockTransferShipmentService::class)->ship($transfer, 'SHIP-1');
        $transit = $transfer->fresh()->transitWarehouse;

        $this->assertSame('4.000000', $this->balance($context['source'], $context['product'])->quantity);
        $this->assertSame('6.000000', $this->balance($transit, $context['product'])->quantity);
        $this->assertSame('10.000000', $this->companyQuantity($context['company']->id, $context['product']->id));
        $this->assertSame('7.5000', $transfer->items()->first()->fresh()->unit_cost);

        $item = $transfer->items()->first();
        app(StockTransferReceivingService::class)->receive($transfer->fresh(), [
            $item->id => ['received_quantity' => '2'],
        ]);
        $this->assertSame('partially_received', $transfer->fresh()->status);
        $this->assertSame('4.000000', $this->balance($transit, $context['product'])->quantity);

        app(StockTransferReceivingService::class)->receive($transfer->fresh(), [
            $item->id => ['received_quantity' => '4'],
        ]);
        $this->assertSame('received', $transfer->fresh()->status);
        $this->assertSame('6.000000', $this->balance($context['destination'], $context['product'])->quantity);
        $this->assertSame('0.000000', $this->balance($transit, $context['product'])->quantity);
        $this->assertSame('10.000000', $this->companyQuantity($context['company']->id, $context['product']->id));
    }

    public function test_shortage_creates_discrepancy_and_cannot_over_receive(): void
    {
        $context = $this->context();
        app(InventoryService::class)->receive($context['source'], $context['product'], '3', '9', 'opening_balance');
        $transfer = $this->ready($context, '3');
        app(StockTransferShipmentService::class)->ship($transfer);
        $item = $transfer->items()->first();
        app(StockTransferReceivingService::class)->receive($transfer->fresh(), [
            $item->id => ['received_quantity' => '2', 'shortage_quantity' => '1'],
        ]);

        $this->assertSame('received', $transfer->fresh()->status);
        $this->assertDatabaseHas('stock_transfer_discrepancies', [
            'stock_transfer_id' => $transfer->id, 'stock_transfer_item_id' => $item->id,
            'discrepancy_type' => 'shortage', 'status' => 'open',
        ]);
        $this->assertSame('0.000000', $this->balance($transfer->fresh()->transitWarehouse, $context['product'])->quantity);

        $this->expectException(BusinessRuleException::class);
        app(StockTransferReceivingService::class)->receive($transfer->fresh(), [
            $item->id => ['received_quantity' => '1'],
        ]);
    }

    public function test_roll_transfer_preserves_specific_cost_and_can_be_reversed_once(): void
    {
        $context = $this->context('roll');
        $roll = app(RollService::class)->receive($context['source'], $context['product'], [
            'width' => '1.5', 'original_length' => '20', 'total_cost' => '300',
        ]);
        $transfer = $this->ready($context, '1', 'roll', $roll->id);
        app(StockTransferShipmentService::class)->ship($transfer);
        $this->assertSame($transfer->fresh()->transit_warehouse_id, $roll->fresh()->warehouse_id);

        $item = $transfer->items()->first();
        app(StockTransferReceivingService::class)->receive($transfer->fresh(), [
            $item->id => ['received_quantity' => '1'],
        ]);
        $this->assertSame($context['destination']->id, $roll->fresh()->warehouse_id);
        $this->assertSame('300.0000', $item->fresh()->unit_cost);

        $reversal = app(StockTransferReversalService::class)->reverse($transfer->fresh());
        $this->assertSame('reversed', $transfer->fresh()->status);
        $this->assertSame('received', $reversal->status);
        $this->assertSame($context['source']->id, $roll->fresh()->warehouse_id);
        $this->assertGreaterThan(0, StockMovement::query()->where('reference_id', $reversal->id)
            ->whereNotNull('reversal_of_id')->count());

        $this->expectException(BusinessRuleException::class);
        app(StockTransferReversalService::class)->reverse($transfer->fresh());
    }

    public function test_scrap_moves_as_one_tracked_item_without_changing_quantity_balance(): void
    {
        $context = $this->context('roll');
        $roll = app(RollService::class)->receive($context['source'], $context['product'], [
            'width' => '1.5', 'original_length' => '20', 'total_cost' => '300',
        ]);
        $scrap = app(RollScrapService::class)->create($roll, '0.5', '2');
        $sourceQuantity = $this->balance($context['source'], $context['product'])->quantity;
        $transfer = $this->ready($context, '1', 'scrap', null, $scrap->id);
        app(StockTransferShipmentService::class)->ship($transfer);
        $this->assertSame($transfer->fresh()->transit_warehouse_id, $scrap->fresh()->warehouse_id);

        $item = $transfer->items()->first();
        app(StockTransferReceivingService::class)->receive($transfer->fresh(), [
            $item->id => ['received_quantity' => '1'],
        ]);
        $this->assertSame($context['destination']->id, $scrap->fresh()->warehouse_id);
        $this->assertSame($sourceQuantity, $this->balance($context['source'], $context['product'])->quantity);
    }

    public function test_transfer_seeder_is_idempotent_per_branch(): void
    {
        $context = $this->context();
        $seeder = new StockTransferSeeder;

        $seeder->run();
        $seeder->run();

        $this->assertSame(1, Warehouse::query()
            ->where('branch_id', $context['sourceBranch']->id)
            ->where('warehouse_type', 'transit')->where('is_system', true)->count());
        $this->assertSame(1, Warehouse::query()
            ->where('branch_id', $context['destinationBranch']->id)
            ->where('warehouse_type', 'transit')->where('is_system', true)->count());
    }

    public function test_system_transit_is_hidden_from_normal_inventory_documents_and_cannot_be_disabled(): void
    {
        $context = $this->context();
        (new StockTransferSeeder)->run();
        $permission = Permission::query()->firstOrCreate(
            ['name' => 'inventory.opening'],
            ['display_name' => 'inventory.opening']
        );
        $context['user']->roles()->first()->permissions()->syncWithoutDetaching([$permission->id]);
        $this->actingAs($context['user']);

        $view = app(InventoryDocumentController::class)->create('openings', app(TenantContext::class));
        $warehouses = $view->getData()['warehouses'];

        $this->assertTrue($warehouses->contains('id', $context['source']->id));
        $this->assertFalse($warehouses->contains('id', $context['transit']->id));
        $this->expectException(BusinessRuleException::class);
        app(WarehouseService::class)->disable($context['transit']);
    }

    public function test_rejected_quantity_stays_in_transit_and_transfer_remains_partially_received(): void
    {
        $context = $this->context();
        app(InventoryService::class)->receive($context['source'], $context['product'], '5', '8', 'opening_balance');
        $transfer = $this->ready($context, '5');
        app(StockTransferShipmentService::class)->ship($transfer);
        $item = $transfer->items()->first();

        app(StockTransferReceivingService::class)->receive($transfer->fresh(), [
            $item->id => ['received_quantity' => '4', 'rejected_quantity' => '1'],
        ]);

        $this->assertSame('partially_received', $transfer->fresh()->status);
        $this->assertSame('0.000000', $this->balance($context['source'], $context['product'])->available_quantity);
        $this->assertSame('4.000000', $this->balance($context['destination'], $context['product'])->available_quantity);
        $this->assertSame('1.000000', $this->balance($context['transit'], $context['product'])->quantity);
        $this->assertDatabaseHas('stock_transfer_discrepancies', [
            'stock_transfer_id' => $transfer->id,
            'stock_transfer_item_id' => $item->id,
            'discrepancy_type' => 'rejection',
            'status' => 'open',
        ]);
    }

    private function ready(array $context, string $quantity, string $type = 'quantity', ?int $rollId = null, ?int $scrapId = null): StockTransfer
    {
        $transfer = $this->submitted($context, $quantity, $type, $rollId, $scrapId);
        app(StockTransferApprovalService::class)->approve($transfer);
        app(StockTransferPreparationService::class)->prepare($transfer->fresh());

        return $transfer->fresh();
    }

    private function submitted(array $context, string $quantity, string $type = 'quantity', ?int $rollId = null, ?int $scrapId = null): StockTransfer
    {
        $transfer = $this->draft($context, $quantity, $type, $rollId, $scrapId);
        app(StockTransferService::class)->submit($transfer);

        return $transfer->fresh();
    }

    private function draft(array $context, string $quantity, string $type = 'quantity', ?int $rollId = null, ?int $scrapId = null): StockTransfer
    {
        return app(StockTransferService::class)->create([
            'from_warehouse_id' => $context['source']->id,
            'to_warehouse_id' => $context['destination']->id,
            'items' => [[
                'product_id' => $context['product']->id, 'item_type' => $type,
                'requested_quantity' => $quantity, 'roll_id' => $rollId, 'scrap_id' => $scrapId,
            ]],
        ]);
    }

    private function context(string $tracking = 'quantity'): array
    {
        $company = Company::query()->create(['name' => 'Transfer '.uniqid()]);
        $sourceBranch = $this->branch($company, 'SRC', true);
        $destinationBranch = $this->branch($company, 'DST');
        $user = User::factory()->create([
            'company_id' => $company->id, 'branch_id' => $sourceBranch->id, 'status' => 'active',
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id, 'name' => 'company_owner',
            'display_name' => 'Owner', 'scope' => 'company', 'is_active' => true,
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach([
            $sourceBranch->id => ['is_default' => true, 'can_view' => true],
            $destinationBranch->id => ['is_default' => false, 'can_view' => true],
        ]);
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
        $source = $this->warehouse($company, $sourceBranch, 'SOURCE', 'main');
        $destination = $this->warehouse($company, $destinationBranch, 'DEST', 'main');
        $transit = $this->warehouse($company, $sourceBranch, 'TRANSIT', 'transit', true);
        foreach ([$sourceBranch, $destinationBranch] as $branch) {
            foreach (['stock_movement', 'stock_transfer', 'roll', 'roll_scrap'] as $type) {
                $this->sequence($company, $branch, $type);
            }
        }

        return compact('company', 'sourceBranch', 'destinationBranch', 'user', 'product', 'source', 'destination', 'transit');
    }

    private function branch(Company $company, string $prefix, bool $main = false): Branch
    {
        $branch = Branch::query()->create([
            'company_id' => $company->id, 'code' => $prefix.uniqid(), 'name' => $prefix,
            'is_main' => $main, 'is_active' => true,
        ]);
        $branch->settings()->create(['allow_negative_stock' => false]);

        return $branch;
    }

    private function warehouse(Company $company, Branch $branch, string $code, string $type, bool $system = false): Warehouse
    {
        return Warehouse::query()->forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'code' => $system ? 'TRANSIT' : $code.uniqid(), 'name' => $code, 'warehouse_type' => $type,
            'is_main' => $type === 'main', 'is_active' => true, 'is_system' => $system,
        ]);
    }

    private function sequence(Company $company, Branch $branch, string $type): void
    {
        DocumentSequence::query()->forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'document_type' => $type,
            'prefix' => strtoupper(substr($type, 0, 3)).'-'.$branch->id.'-', 'current_number' => 0,
            'padding' => 6, 'reset_period' => 'never',
            'scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, $type, null),
            'is_active' => true,
        ]);
    }

    private function balance(Warehouse $warehouse, Product $product): StockBalance
    {
        return StockBalance::query()->firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'product_id' => $product->id],
            ['company_id' => $warehouse->company_id, 'branch_id' => $warehouse->branch_id]
        )->refresh();
    }

    private function companyQuantity(int $companyId, int $productId): string
    {
        return number_format((float) StockBalance::query()->where('company_id', $companyId)
            ->where('product_id', $productId)->sum('quantity'), 6, '.', '');
    }
}
