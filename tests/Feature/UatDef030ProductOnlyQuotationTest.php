<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\BranchProductPrice;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\QuotationToSalesInvoiceService;
use App\Services\QuotationVersionService;
use App\Services\SalesInvoiceIssuanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UatDef030ProductOnlyQuotationTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private Branch $alexandria;

    private Branch $cairo;

    private User $manager;

    private Currency $currency;

    private Customer $customer;

    private Vehicle $vehicle;

    private Product $alexProduct;

    private Product $cairoProduct;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::query()->firstOrCreate(['code' => 'EGP'], [
            'name_ar' => 'جنيه مصري', 'name_en' => 'Egyptian Pound', 'symbol' => 'ج.م',
            'decimal_places' => 2, 'is_active' => true,
        ]);
        $this->company = Company::query()->create([
            'name' => 'UAT DEF 030 '.uniqid(), 'currency_id' => $this->currency->id, 'is_active' => true,
        ]);
        $this->alexandria = $this->branch('ALX', 'الإسكندرية', true);
        $this->cairo = $this->branch('CAI', 'القاهرة');
        $this->manager = $this->userWithPermissions([
            'quotations.view', 'quotations.create', 'quotations.update', 'sales_invoices.view',
            'sales_invoices.create', 'sales_invoices.issue', 'sales_invoices.print',
        ]);
        app(TenantContext::class)->initialize($this->manager);

        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'created_branch_id' => $this->alexandria->id,
            'assigned_branch_id' => $this->alexandria->id,
            'status' => 'active',
        ]);
        $brand = VehicleBrand::query()->create(['name_ar' => 'علامة UAT 030', 'is_active' => true]);
        $model = VehicleModel::query()->create([
            'vehicle_brand_id' => $brand->id, 'name_ar' => 'موديل UAT 030', 'is_active' => true,
        ]);
        $this->vehicle = Vehicle::query()->forceCreate([
            'company_id' => $this->company->id, 'customer_id' => $this->customer->id,
            'created_branch_id' => $this->alexandria->id, 'vehicle_brand_id' => $brand->id,
            'vehicle_model_id' => $model->id, 'plate_number' => 'ALX-030',
            'normalized_plate_number' => 'ALX030', 'status' => 'active',
        ]);
        $unit = Unit::query()->forceCreate([
            'company_id' => $this->company->id, 'code' => 'PCS-'.uniqid(), 'name' => 'قطعة',
            'symbol' => 'قطعة', 'unit_type' => 'quantity', 'decimal_places' => 6,
            'is_system' => false, 'is_active' => true,
        ]);
        $category = ProductCategory::query()->forceCreate([
            'company_id' => $this->company->id, 'code' => 'P-'.uniqid(), 'name' => 'منتجات', 'is_active' => true,
        ]);
        $tax = Tax::query()->forceCreate([
            'company_id' => $this->company->id, 'code' => 'VAT14-'.uniqid(), 'name' => 'VAT 14',
            'rate' => 14, 'tax_type' => 'vat', 'is_active' => true,
        ]);
        $this->alexProduct = $this->product($category, $unit, $tax, 'CLEANER-PPF-UAT-001', 'منظف أفلام الحماية PPF UAT', 250);
        $this->cairoProduct = $this->product($category, $unit, $tax, 'CAIRO-ONLY-030', 'منتج القاهرة فقط', 100);
        $this->makeAvailable($this->alexProduct, $this->alexandria);
        $this->makeAvailable($this->cairoProduct, $this->cairo);

        DocumentSequence::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->alexandria->id,
            'document_type' => 'quotation', 'prefix' => 'Q-030-', 'current_number' => 0,
            'padding' => 6, 'reset_period' => 'yearly', 'period_key' => now()->format('Y'),
            'scope_key' => DocumentNumberService::scopeKey(
                $this->company->id, $this->alexandria->id, 'quotation', now()->format('Y')
            ),
            'is_active' => true,
        ]);
        DocumentSequence::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->alexandria->id,
            'document_type' => 'sales_invoice', 'prefix' => 'INV-030-', 'current_number' => 0,
            'padding' => 6, 'reset_period' => 'yearly', 'period_key' => now()->format('Y'),
            'scope_key' => DocumentNumberService::scopeKey(
                $this->company->id, $this->alexandria->id, 'sales_invoice', now()->format('Y')
            ),
            'is_active' => true,
        ]);
        DocumentSequence::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->alexandria->id,
            'document_type' => 'stock_movement', 'prefix' => 'MOV-030-', 'current_number' => 0,
            'padding' => 6, 'reset_period' => 'yearly', 'period_key' => now()->format('Y'),
            'scope_key' => DocumentNumberService::scopeKey(
                $this->company->id, $this->alexandria->id, 'stock_movement', now()->format('Y')
            ),
            'is_active' => true,
        ]);
        $this->warehouse = Warehouse::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->alexandria->id,
            'code' => 'WH-030', 'name' => 'مخزن الإسكندرية', 'warehouse_type' => 'main',
            'is_main' => true, 'is_system' => false, 'is_active' => true, 'allows_sale_issue' => true,
        ]);
    }

    public function test_form_is_product_only_and_uses_product_labels(): void
    {
        $response = $this->actingAs($this->manager)->get(route('quotations.create'))->assertOk();

        $response->assertSee('منتجات عرض السعر')
            ->assertSee('المنتج رقم')
            ->assertSee('إضافة منتج جديد')
            ->assertSee('حذف المنتج')
            ->assertSee('CLEANER-PPF-UAT-001')
            ->assertDontSee('items[0][item_type]', false)
            ->assertDontSee('service_id', false)
            ->assertDontSee('service_package_id', false)
            ->assertDontSee('عنصر مخصص');
    }

    public function test_alexandria_manager_only_receives_alexandria_products(): void
    {
        $response = $this->actingAs($this->manager)->get(route('quotations.create'))->assertOk();

        $response->assertSee($this->alexProduct->sku)->assertDontSee($this->cairoProduct->sku);
        $this->actingAs($this->manager)->getJson(route('quotations.products', [
            'branch_id' => $this->cairo->id,
            'quotation_date' => today()->toDateString(),
        ]))->assertForbidden();
    }

    public function test_unified_price_is_used_when_no_branch_price_exists(): void
    {
        $this->actingAs($this->manager)->postJson(route('quotations.preview'), $this->payload())
            ->assertOk()
            ->assertJsonPath('items.0.base_unit_price', '250.00')
            ->assertJsonPath('items.0.unit_price', '250.00')
            ->assertJsonPath('items.0.price_source', 'unified_product_price')
            ->assertJsonPath('items.0.sale_unit', 'قطعة');
    }

    public function test_branch_price_overrides_unified_price(): void
    {
        $this->branchPrice($this->alexProduct, 300);

        $this->actingAs($this->manager)->postJson(route('quotations.preview'), $this->payload())
            ->assertOk()
            ->assertJsonPath('items.0.base_unit_price', '300.00')
            ->assertJsonPath('items.0.unit_price', '300.00')
            ->assertJsonPath('items.0.price_source', 'branch_product_price');
    }

    public function test_active_product_promotion_changes_final_price(): void
    {
        $promotion = Promotion::query()->forceCreate([
            'company_id' => $this->company->id, 'code' => 'PROMO-030', 'name' => 'خصم الإسكندرية',
            'promotion_type' => 'product', 'discount_type' => 'percentage', 'discount_value' => 20,
            'start_at' => now()->subDay(), 'end_at' => now()->addDay(), 'is_active' => true,
        ]);
        $promotion->products()->attach($this->alexProduct);
        $promotion->branches()->attach($this->alexandria);

        $this->actingAs($this->manager)->postJson(route('quotations.preview'), $this->payload())
            ->assertOk()
            ->assertJsonPath('items.0.base_unit_price', '250.00')
            ->assertJsonPath('items.0.unit_price', '200.00')
            ->assertJsonPath('items.0.price_source', 'product_promotion');
    }

    public function test_backend_rejects_legacy_and_browser_pricing_fields(): void
    {
        foreach ([
            'service_id' => 1,
            'service_package_id' => 1,
            'item_type' => 'custom',
            'manual_unit_price' => 1,
            'unit_price' => 1,
        ] as $field => $value) {
            $payload = $this->payload();
            $payload['items'][0][$field] = $value;
            $this->actingAs($this->manager)->postJson(route('quotations.preview'), $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors("items.0.{$field}");
        }
    }

    public function test_backend_forces_saved_item_type_to_product(): void
    {
        $quotation = $this->storeProductQuotation();

        $this->assertSame('product', $quotation->items->first()->item_type);
        $this->assertSame($this->alexProduct->id, $quotation->items->first()->product_id);
    }

    public function test_historical_items_remain_readable_and_read_only_in_a_new_version(): void
    {
        $quotation = $this->historicalQuotation('sent');
        $legacy = $quotation->items()->create($this->legacyItemRow());

        $this->actingAs($this->manager)->get(route('quotations.show', $quotation))
            ->assertOk()->assertSee($legacy->description);

        $version = app(QuotationVersionService::class)->create($quotation, 'Product-only version');
        $this->actingAs($this->manager)->get(route('quotations.edit', $version))
            ->assertOk()
            ->assertSee($legacy->description)
            ->assertSee('محفوظة للقراءة فقط')
            ->assertDontSee('service_id', false);
        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => $version->id, 'item_type' => 'service', 'description' => $legacy->description,
        ]);
    }

    public function test_quotation_to_invoice_preserves_product_snapshot_without_repricing(): void
    {
        $quotation = $this->storeProductQuotation();
        $source = $quotation->items->first();
        $quotation->forceFill(['status' => 'approved'])->save();
        $this->alexProduct->forceFill(['default_sale_price' => 999])->save();

        $invoice = app(QuotationToSalesInvoiceService::class)->convert($quotation);
        $item = $invoice->items->first();

        $this->assertSame($source->product_id, $item->product_id);
        $this->assertSame($source->description, $item->description);
        $this->assertSame($source->quantity, $item->quantity);
        $this->assertSame($source->unit_id, $item->unit_id);
        $this->assertSame($source->unit_price, $item->unit_price);
        $this->assertSame($source->discount_amount, $item->discount_amount);
        $this->assertSame($source->tax_amount, $item->tax_amount);
        $this->assertSame($source->total, $item->total);
        $this->assertSame(data_get($source->metadata, 'base_unit_price'), data_get($item->metadata, 'base_unit_price'));
    }

    public function test_SalesInvoiceInventory_issues_QuotationToSalesInvoice_product_once_and_exposes_the_movement(): void
    {
        app(InventoryService::class)->receive($this->warehouse, $this->alexProduct, '5', '100', 'stock_opening');
        $invoice = $this->approvedProductInvoice();

        app(SalesInvoiceIssuanceService::class)->issue($invoice);

        $item = $invoice->items()->firstOrFail()->fresh();
        $this->assertSame('quotation', $invoice->fresh()->invoice_type);
        $this->assertNotNull($item->issued_movement_id);
        $this->assertSame('4.000000', StockBalance::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->alexProduct->id)
            ->value('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'id' => $item->issued_movement_id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->alexProduct->id,
            'movement_type' => 'sales_issue',
            'direction' => 'out',
            'reference_type' => 'sales_invoice',
            'reference_id' => $invoice->id,
        ]);

        try {
            app(SalesInvoiceIssuanceService::class)->issue($invoice->fresh());
            $this->fail('Issued invoice must not be processed again.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, StockMovement::query()
                ->where('movement_type', 'sales_issue')
                ->where('reference_type', 'sales_invoice')
                ->where('reference_id', $invoice->id)
                ->count());
        }

        $this->actingAs($this->manager)->get(route('sales-invoices.show', $invoice))
            ->assertOk()
            ->assertSee($this->warehouse->name)
            ->assertSee($item->issuedMovement()->value('movement_number'))
            ->assertDontSee('تحذير إداري');
    }

    public function test_quotation_invoice_with_insufficient_stock_rolls_back_issuance(): void
    {
        $invoice = $this->approvedProductInvoice();

        try {
            app(SalesInvoiceIssuanceService::class)->issue($invoice);
            $this->fail('Insufficient stock must block invoice issuance.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('لا يمكن إصدار الفاتورة', $exception->getMessage());
            $this->assertStringContainsString('المتاح: 0، المطلوب: 1', $exception->getMessage());
        }

        $this->assertSame('approved', $invoice->fresh()->status);
        $this->assertNull($invoice->items()->firstOrFail()->issued_movement_id);
        $this->assertSame(0, StockMovement::query()->where('movement_type', 'sales_issue')->count());
    }

    public function test_issued_historical_invoice_is_reported_without_being_changed(): void
    {
        $invoice = $this->approvedProductInvoice();
        $invoice->forceFill(['status' => 'issued', 'issued_at' => now(), 'issued_by' => $this->manager->id])->save();

        $this->artisan('inventory:audit-sales-invoices', ['--dry-run' => true])
            ->expectsOutputToContain($invoice->invoice_number)
            ->expectsOutputToContain($this->alexProduct->sku)
            ->assertSuccessful();

        $this->assertNull($invoice->items()->firstOrFail()->issued_movement_id);
        $this->actingAs($this->manager)->get(route('sales-invoices.show', $invoice))
            ->assertOk()
            ->assertSee('تحذير إداري');
        $this->actingAs($this->manager)->get(route('sales-invoices.print', $invoice))
            ->assertOk()
            ->assertDontSee('تحذير إداري');
    }

    public function test_invoice_product_cannot_issue_from_another_branch_warehouse(): void
    {
        $cairoWarehouse = Warehouse::query()->forceCreate([
            'company_id' => $this->company->id,
            'branch_id' => $this->cairo->id,
            'code' => 'CAI-WH-037',
            'name' => 'مخزن القاهرة',
            'warehouse_type' => 'main',
            'is_system' => false,
            'is_active' => true,
            'allows_sale_issue' => true,
        ]);
        $invoice = $this->approvedProductInvoice();
        $invoice->items()->firstOrFail()->forceFill(['warehouse_id' => $cairoWarehouse->id])->save();

        $this->expectException(BusinessRuleException::class);

        try {
            app(SalesInvoiceIssuanceService::class)->issue($invoice->fresh());
        } finally {
            $this->assertSame('approved', $invoice->fresh()->status);
            $this->assertSame(0, StockMovement::query()->where('movement_type', 'sales_issue')->count());
        }
    }

    public function test_legacy_non_product_invoice_items_do_not_create_inventory_movements(): void
    {
        $quotation = $this->historicalQuotation('approved');
        $quotation->items()->create($this->legacyItemRow());
        $invoice = app(QuotationToSalesInvoiceService::class)->convert($quotation);
        $invoice->forceFill(['status' => 'approved'])->save();

        app(SalesInvoiceIssuanceService::class)->issue($invoice);

        $this->assertSame('issued', $invoice->fresh()->status);
        $this->assertSame(0, StockMovement::query()->where('movement_type', 'sales_issue')->count());
    }

    private function approvedProductInvoice(): SalesInvoice
    {
        $quotation = $this->storeProductQuotation();
        $quotation->forceFill(['status' => 'approved'])->save();
        $invoice = app(QuotationToSalesInvoiceService::class)->convert($quotation);
        $invoice->forceFill(['status' => 'approved'])->save();

        return $invoice->fresh('items');
    }

    private function storeProductQuotation(): Quotation
    {
        $response = $this->actingAs($this->manager)->post(route('quotations.store'), $this->payload());
        $quotation = Quotation::query()->where('company_id', $this->company->id)->latest('id')->firstOrFail();
        $response->assertRedirect(route('quotations.show', $quotation));

        return $quotation->load('items');
    }

    private function payload(): array
    {
        return [
            'branch_id' => $this->alexandria->id, 'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id, 'currency_id' => $this->currency->id,
            'quotation_date' => today()->toDateString(), 'valid_until' => today()->addDays(7)->toDateString(),
            'discount_value' => 0,
            'items' => [[
                'product_id' => $this->alexProduct->id, 'description' => null,
                'quantity' => 1, 'discount_value' => 0,
            ]],
        ];
    }

    private function historicalQuotation(string $status = 'draft'): Quotation
    {
        return Quotation::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->alexandria->id,
            'quotation_number' => 'LEGACY-'.uniqid(), 'version_number' => 1,
            'customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id,
            'status' => $status, 'quotation_date' => today(), 'valid_until' => today()->addDays(7),
            'currency_id' => $this->currency->id, 'subtotal' => 100, 'discount_amount' => 0,
            'tax_amount' => 14, 'total' => 114, 'created_by' => $this->manager->id,
        ]);
    }

    private function legacyItemRow(): array
    {
        return [
            'item_type' => 'service', 'description' => 'خدمة تاريخية محفوظة', 'quantity' => 1,
            'unit_price' => 100, 'gross_amount' => 100, 'discount_value' => 0,
            'discount_amount' => 0, 'net_amount' => 100, 'tax_rate' => 14,
            'tax_amount' => 14, 'total' => 114, 'price_source' => 'legacy', 'sort_order' => 0,
            'metadata' => ['base_unit_price' => '100.00', 'header_discount_allocation' => '0.00'],
        ];
    }

    private function branch(string $code, string $name, bool $main = false): Branch
    {
        return Branch::query()->create([
            'company_id' => $this->company->id, 'code' => $code.'-'.uniqid(), 'name' => $name,
            'is_main' => $main, 'is_active' => true,
        ]);
    }

    private function product(ProductCategory $category, Unit $unit, Tax $tax, string $sku, string $name, float $price): Product
    {
        return Product::query()->forceCreate([
            'company_id' => $this->company->id, 'category_id' => $category->id,
            'sku' => $sku, 'name' => $name, 'product_type' => 'consumable', 'tracking_type' => 'quantity',
            'purchase_unit_id' => $unit->id, 'stock_unit_id' => $unit->id, 'sale_unit_id' => $unit->id,
            'default_tax_id' => $tax->id, 'costing_method' => 'weighted_average',
            'default_sale_price' => $price, 'minimum_stock' => 0, 'is_sellable' => true,
            'is_purchasable' => true, 'is_consumable' => true, 'is_active' => true,
        ]);
    }

    private function makeAvailable(Product $product, Branch $branch): void
    {
        BranchProduct::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'is_available' => true, 'is_sellable' => true, 'created_by' => $this->manager->id,
        ]);
    }

    private function branchPrice(Product $product, float $price): void
    {
        BranchProductPrice::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->alexandria->id,
            'product_id' => $product->id, 'price' => $price, 'effective_from' => today()->subDay(),
            'priority' => 0, 'is_active' => true, 'created_by' => $this->manager->id,
        ]);
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'branch_id' => $this->alexandria->id, 'status' => 'active',
        ]);
        $role = Role::query()->create([
            'company_id' => $this->company->id, 'name' => 'uat030_'.uniqid(),
            'display_name' => 'UAT 030', 'scope' => 'branch', 'is_active' => true,
        ]);
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['display_name' => $name]);
            $role->permissions()->syncWithoutDetaching($permission);
        }
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($this->alexandria, ['is_default' => true, 'can_view' => true]);

        return $user;
    }
}
