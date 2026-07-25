<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\DocumentSequence;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use App\Services\GoodsReceiptPostingService;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderApprovalService;
use App\Services\PurchaseOrderIssuanceService;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseRequisitionApprovalService;
use App\Services\PurchaseRequisitionService;
use App\Services\PurchaseReturnPostingService;
use App\Services\PurchaseReturnService;
use App\Services\SupplierInvoiceApprovalService;
use App\Services\SupplierInvoicePostingService;
use App\Services\SupplierInvoiceService;
use App\Services\SupplierPaymentAllocationService;
use App\Services\SupplierPaymentService;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseThirteenPurchasingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_requisition_and_purchase_order_do_not_change_stock_and_use_backend_totals(): void
    {
        $context = $this->context();
        $requisition = app(PurchaseRequisitionService::class)->create([
            'request_date' => today()->toDateString(),
            'purpose' => 'Restock',
        ], [[
            'product_id' => $context['product']->id,
            'unit_id' => $context['unit']->id,
            'requested_quantity' => 10,
            'estimated_unit_cost' => 8,
        ]]);
        app(PurchaseRequisitionApprovalService::class)->submit($requisition);
        try {
            app(PurchaseRequisitionApprovalService::class)->approve($requisition->fresh());
            $this->fail('The creator must not approve the requisition.');
        } catch (BusinessRuleException) {
            $this->assertSame('pending_approval', $requisition->fresh()->status);
        }
        $this->asUser($context['approver']);
        app(PurchaseRequisitionApprovalService::class)->approve($requisition->fresh());
        $item = $requisition->items()->first();
        $this->asUser($context['user']);
        $order = app(PurchaseOrderService::class)->fromRequisition($requisition->fresh(), [
            'supplier_id' => $context['supplier']->id,
            'order_date' => today()->toDateString(),
            'currency_id' => $context['currency']->id,
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ], [[
            'purchase_requisition_item_id' => $item->id,
            'product_id' => $context['product']->id,
            'ordered_quantity' => 10,
            'unit_price' => 10,
            'tax_rate' => 15,
        ]]);
        $this->assertSame('100.0000', $order->subtotal);
        $this->assertSame('10.00', $order->discount_amount);
        $this->assertSame('103.5000', $order->total);
        $this->assertSame(0, StockMovement::count());
        $this->assertSame('ordered', $requisition->fresh()->status);
    }

    public function test_partial_receipt_posts_only_accepted_and_free_quantity_once(): void
    {
        $context = $this->context();
        $order = $this->sentOrder($context, 10, 10);
        $this->asUser($context['user']);
        $receipt = app(GoodsReceiptService::class)->create([
            'warehouse_id' => $context['warehouse']->id,
            'supplier_id' => $context['supplier']->id,
            'purchase_order_id' => $order->id,
            'receipt_date' => today()->toDateString(),
        ], [[
            'purchase_order_item_id' => $order->items()->first()->id,
            'product_id' => $context['product']->id,
            'received_quantity' => 10,
            'accepted_quantity' => 8,
            'rejected_quantity' => 2,
            'free_quantity' => 1,
        ]]);
        app(GoodsReceiptService::class)->receive($receipt);
        $this->assertSame('partially_rejected', $receipt->fresh()->status);
        app(GoodsReceiptPostingService::class)->post($receipt->fresh());
        $balance = StockBalance::where('warehouse_id', $context['warehouse']->id)
            ->where('product_id', $context['product']->id)->firstOrFail();
        $this->assertSame('9.000000', $balance->quantity);
        $this->assertSame('8.8888', $balance->average_cost);
        $this->assertSame('8.000000', $order->items()->first()->received_quantity);
        try {
            app(GoodsReceiptPostingService::class)->post($receipt->fresh());
            $this->fail('A receipt must not post twice.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, StockMovement::where('movement_type', 'purchase_receipt')->count());
        }
    }

    public function test_purchase_return_uses_receipt_cost_and_cannot_be_posted_twice(): void
    {
        $context = $this->context();
        $order = $this->sentOrder($context, 5, 12);
        $this->asUser($context['user']);
        $receipt = app(GoodsReceiptService::class)->create([
            'warehouse_id' => $context['warehouse']->id,
            'supplier_id' => $context['supplier']->id,
            'purchase_order_id' => $order->id,
            'receipt_date' => today()->toDateString(),
        ], [[
            'purchase_order_item_id' => $order->items()->first()->id,
            'product_id' => $context['product']->id,
            'received_quantity' => 5,
            'accepted_quantity' => 5,
            'rejected_quantity' => 0,
        ]]);
        app(GoodsReceiptService::class)->receive($receipt);
        app(GoodsReceiptPostingService::class)->post($receipt->fresh());
        $return = app(PurchaseReturnService::class)->create([
            'supplier_id' => $context['supplier']->id,
            'warehouse_id' => $context['warehouse']->id,
            'goods_receipt_id' => $receipt->id,
            'purchase_order_id' => $order->id,
            'return_date' => today()->toDateString(),
            'reason' => 'Damaged',
        ], [[
            'goods_receipt_item_id' => $receipt->items()->first()->id,
            'product_id' => $context['product']->id,
            'unit_id' => $context['unit']->id,
            'quantity' => 2,
            'reason_code' => 'damaged',
        ]]);
        app(PurchaseReturnService::class)->submit($return);
        $this->asUser($context['approver']);
        app(PurchaseReturnService::class)->approve($return->fresh());
        app(PurchaseReturnPostingService::class)->post($return->fresh());
        $this->assertSame('3.000000', StockBalance::where('warehouse_id', $context['warehouse']->id)
            ->where('product_id', $context['product']->id)->value('quantity'));
        $this->assertSame(1, StockMovement::where('movement_type', 'purchase_return')->count());
        $this->expectException(BusinessRuleException::class);
        app(PurchaseReturnPostingService::class)->post($return->fresh());
    }

    public function test_invoice_payment_allocation_and_reversal_rebuild_official_balances_without_stock(): void
    {
        $context = $this->context();
        $before = StockMovement::count();
        $invoice = app(SupplierInvoiceService::class)->create([
            'supplier_id' => $context['supplier']->id,
            'supplier_invoice_number' => 'EXT-'.uniqid(),
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->addDays(30)->toDateString(),
            'currency_id' => $context['currency']->id,
        ], [[
            'description' => 'Operational purchase invoice',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 15,
        ]]);
        app(SupplierInvoiceApprovalService::class)->submit($invoice);
        $this->asUser($context['approver']);
        app(SupplierInvoiceApprovalService::class)->approve($invoice->fresh());
        app(SupplierInvoicePostingService::class)->post($invoice->fresh());
        $payment = app(SupplierPaymentService::class)->create([
            'supplier_id' => $context['supplier']->id,
            'payment_method_id' => $context['paymentMethod']->id,
            'payment_date' => today()->toDateString(),
            'currency_id' => $context['currency']->id,
            'amount' => 115,
        ]);
        app(SupplierPaymentService::class)->approve($payment);
        app(SupplierPaymentService::class)->process($payment->fresh());
        $allocation = app(SupplierPaymentAllocationService::class)->allocate($payment->fresh(), $invoice->fresh(), '115');
        $this->assertSame('paid', $invoice->fresh()->status);
        app(SupplierPaymentAllocationService::class)->reverse($allocation, 'Correction');
        $this->assertSame('posted', $invoice->fresh()->status);
        $this->assertSame('115.0000', $invoice->fresh()->balance_due);
        $this->assertSame($before, StockMovement::count());
        $this->assertFalse(\Schema::hasTable('journal_entries'));
    }

    public function test_supplier_access_is_company_scoped(): void
    {
        $first = $this->context();
        $second = $this->context();

        $this->actingAs($second['user'])
            ->get(route('suppliers.show', $first['supplier']))
            ->assertForbidden();
    }

    public function test_receipt_inspection_attachment_is_private_and_cross_company_download_is_forbidden(): void
    {
        Storage::fake('local');
        $first = $this->context();
        $order = $this->sentOrder($first, 1, 10);
        $this->asUser($first['user']);
        $receipt = app(GoodsReceiptService::class)->create([
            'warehouse_id' => $first['warehouse']->id,
            'supplier_id' => $first['supplier']->id,
            'purchase_order_id' => $order->id,
            'receipt_date' => today()->toDateString(),
        ], [[
            'purchase_order_item_id' => $order->items()->first()->id,
            'product_id' => $first['product']->id,
            'received_quantity' => 1,
            'accepted_quantity' => 1,
            'rejected_quantity' => 0,
        ]]);
        $this->post(route('goods-receipts.attachments.store', $receipt), [
            'category' => 'goods_receipt_damage',
            'file' => UploadedFile::fake()->create('inspection.jpg', 20, 'image/jpeg'),
        ])->assertRedirect();
        $attachment = $receipt->attachments()->firstOrFail();
        $this->assertSame('local', $attachment->disk);
        $this->assertStringStartsWith('private/attachments/', $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);

        $second = $this->context();
        $this->actingAs($second['user'])
            ->get(route('attachments.download', $attachment))
            ->assertForbidden();
    }

    private function sentOrder(array $context, int $quantity, int $unitPrice)
    {
        $order = app(PurchaseOrderService::class)->create([
            'supplier_id' => $context['supplier']->id,
            'order_date' => today()->toDateString(),
            'currency_id' => $context['currency']->id,
        ], [[
            'product_id' => $context['product']->id,
            'ordered_quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate' => 0,
        ]]);
        app(PurchaseOrderApprovalService::class)->submit($order);
        $this->asUser($context['approver']);
        app(PurchaseOrderApprovalService::class)->approve($order->fresh());
        app(PurchaseOrderIssuanceService::class)->send($order->fresh());

        return $order->fresh();
    }

    private function asUser(User $user): void
    {
        $this->actingAs($user);
        app(TenantContext::class)->initialize($user);
    }

    private function context(): array
    {
        $currency = Currency::firstOrCreate(
            ['code' => 'SAR'],
            ['name_ar' => 'Riyal', 'name_en' => 'Riyal', 'symbol' => 'SAR', 'decimal_places' => 2, 'is_active' => true]
        );
        $company = Company::create(['name' => 'Phase 13 '.uniqid(), 'currency_id' => $currency->id, 'is_active' => true]);
        $branch = Branch::create([
            'company_id' => $company->id, 'code' => 'B'.uniqid(),
            'name' => 'Branch', 'is_main' => true, 'is_active' => true,
        ]);
        $branch->settings()->create([
            'working_day_start' => '08:00:00', 'working_day_end' => '20:00:00', 'weekend_days' => [],
        ]);
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $approver = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $role = Role::create([
            'company_id' => $company->id, 'name' => 'purchasing_'.uniqid(),
            'display_name' => 'Purchasing', 'scope' => 'company', 'is_active' => true,
        ]);
        foreach ([
            'suppliers.view', 'suppliers.create', 'suppliers.update',
            'purchase_requisitions.view', 'purchase_requisitions.create', 'purchase_requisitions.submit',
            'purchase_requisitions.approve', 'purchase_orders.view', 'purchase_orders.create',
            'purchase_orders.submit', 'purchase_orders.approve', 'purchase_orders.send',
            'goods_receipts.view', 'goods_receipts.create', 'goods_receipts.receive',
            'goods_receipts.inspect', 'goods_receipts.post',
            'purchase_returns.view', 'purchase_returns.create', 'purchase_returns.approve', 'purchase_returns.post',
            'supplier_invoices.view', 'supplier_invoices.create', 'supplier_invoices.submit',
            'supplier_invoices.approve', 'supplier_invoices.post', 'supplier_payments.view',
            'supplier_payments.create', 'supplier_payments.approve', 'supplier_payments.process',
            'supplier_payments.allocate', 'supplier_payments.reverse_allocation',
        ] as $name) {
            $role->permissions()->syncWithoutDetaching(
                Permission::firstOrCreate(['name' => $name], ['display_name' => $name])
            );
        }
        foreach ([$user, $approver] as $actor) {
            $actor->roles()->attach($role);
            $actor->accessibleBranches()->attach($branch->id, [
                'is_default' => true, 'can_view' => true,
            ]);
        }
        $this->asUser($user);
        $warehouse = Warehouse::query()->forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'code' => 'W'.uniqid(), 'name' => 'Main', 'warehouse_type' => 'main',
            'is_active' => true, 'is_system' => false, 'allows_work_order_issue' => true,
        ]);
        $tax = Tax::query()->forceCreate([
            'company_id' => $company->id, 'code' => 'VAT'.uniqid(), 'name' => 'VAT',
            'rate' => 15, 'tax_type' => 'vat', 'is_active' => true,
        ]);
        $unit = Unit::query()->forceCreate([
            'company_id' => $company->id, 'code' => 'U'.uniqid(), 'name' => 'Piece',
            'symbol' => 'pc', 'unit_type' => 'quantity', 'decimal_places' => 6, 'is_active' => true,
        ]);
        $category = ProductCategory::query()->forceCreate([
            'company_id' => $company->id, 'code' => 'P'.uniqid(), 'name' => 'Products', 'is_active' => true,
        ]);
        $product = Product::query()->forceCreate([
            'company_id' => $company->id, 'category_id' => $category->id,
            'sku' => 'SKU'.uniqid(), 'name' => 'Product', 'product_type' => 'consumable',
            'tracking_type' => 'quantity', 'purchase_unit_id' => $unit->id,
            'stock_unit_id' => $unit->id, 'sale_unit_id' => $unit->id, 'default_tax_id' => $tax->id,
            'costing_method' => 'weighted_average', 'default_sale_price' => 100,
            'is_purchasable' => true, 'is_sellable' => true, 'is_consumable' => true, 'is_active' => true,
        ]);
        $paymentMethod = PaymentMethod::query()->forceCreate([
            'company_id' => $company->id, 'code' => 'CASH'.uniqid(), 'name' => 'Cash',
            'type' => 'cash', 'is_cash' => true, 'is_active' => true,
        ]);
        foreach ([
            'supplier', 'purchase_requisition', 'purchase_order', 'goods_receipt',
            'purchase_return', 'supplier_invoice', 'supplier_payment', 'supplier_credit_note', 'stock_movement',
        ] as $type) {
            DocumentSequence::query()->forceCreate([
                'company_id' => $company->id, 'branch_id' => $branch->id,
                'document_type' => $type, 'prefix' => '{BRANCH}-'.strtoupper($type).'-{YYYY}-',
                'current_number' => 0, 'padding' => 6, 'reset_period' => 'yearly',
                'period_key' => now()->format('Y'),
                'scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, $type, now()->format('Y')),
                'is_active' => true,
            ]);
        }
        $supplier = app(SupplierService::class)->create([
            'name' => 'Supplier '.uniqid(),
            'supplier_type' => 'materials',
            'currency_id' => $currency->id,
        ]);

        return compact(
            'currency', 'company', 'branch', 'user', 'approver', 'warehouse',
            'tax', 'unit', 'product', 'paymentMethod', 'supplier'
        );
    }
}
