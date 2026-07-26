<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\DocumentSequence;
use App\Models\InventoryBatch;
use App\Models\InventoryRoll;
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
use App\Services\AccountsPayableAgingService;
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
use App\Services\RollConsumptionService;
use App\Services\SupplierCreditNoteService;
use App\Services\SupplierInvoiceApprovalService;
use App\Services\SupplierInvoiceBalanceService;
use App\Services\SupplierInvoiceMatchingService;
use App\Services\SupplierInvoicePostingService;
use App\Services\SupplierInvoiceService;
use App\Services\SupplierPaymentAllocationService;
use App\Services\SupplierPaymentService;
use App\Services\SupplierService;
use App\Services\SupplierStatementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
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
        $this->assertSame('8.8889', $balance->average_cost);
        $this->assertSame(
            '80.0000',
            StockMovement::where('movement_type', 'purchase_receipt')->value('total_cost')
        );
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
        try {
            app(PurchaseReturnPostingService::class)->post($return->fresh());
            $this->fail('Return retry must be rejected.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, StockMovement::where('movement_type', 'purchase_return')->count());
        }

        $this->asUser($context['user']);
        $excess = app(PurchaseReturnService::class)->create([
            'supplier_id' => $context['supplier']->id,
            'warehouse_id' => $context['warehouse']->id,
            'goods_receipt_id' => $receipt->id,
            'purchase_order_id' => $order->id,
            'return_date' => today()->toDateString(),
            'reason' => 'Excess return',
        ], [[
            'goods_receipt_item_id' => $receipt->items()->first()->id,
            'product_id' => $context['product']->id,
            'unit_id' => $context['unit']->id,
            'quantity' => 4,
            'reason_code' => 'damaged',
        ]]);
        app(PurchaseReturnService::class)->submit($excess);
        $this->asUser($context['approver']);
        app(PurchaseReturnService::class)->approve($excess->fresh());
        try {
            app(PurchaseReturnPostingService::class)->post($excess->fresh());
            $this->fail('Cumulative return cannot exceed accepted quantity.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, StockMovement::where('movement_type', 'purchase_return')->count());
            $this->assertSame('posted', $receipt->fresh()->status);
        }
    }

    public function test_batch_requirements_aggregate_exact_cost_and_rollback_invalid_posting(): void
    {
        $context = $this->context();
        $order = $this->sentOrder($context, 4, 10, [
            'batch_required' => true,
            'expiry_required' => true,
        ]);
        $this->asUser($context['user']);
        foreach ([[2, 10], [2, 20]] as $index => [$quantity, $cost]) {
            $receipt = app(GoodsReceiptService::class)->create([
                'warehouse_id' => $context['warehouse']->id,
                'supplier_id' => $context['supplier']->id,
                'purchase_order_id' => $order->id,
                'receipt_date' => today()->toDateString(),
            ], [[
                'purchase_order_item_id' => $order->items()->first()->id,
                'product_id' => $context['product']->id,
                'received_quantity' => $quantity,
                'accepted_quantity' => $quantity,
                'rejected_quantity' => 0,
                'unit_cost' => $cost,
                'batch_number' => 'BATCH-1',
                'expiry_date' => today()->addYear()->toDateString(),
            ]]);
            app(GoodsReceiptService::class)->receive($receipt);
            app(GoodsReceiptPostingService::class)->post($receipt->fresh());
        }
        $batch = InventoryBatch::where('batch_number', 'BATCH-1')->firstOrFail();
        $this->assertSame('4.000000', $batch->received_quantity);
        $this->assertSame('4.000000', $batch->available_quantity);
        $this->assertSame('60.0000', $batch->total_cost);
        $this->assertSame('15.0000', $batch->unit_cost);

        $return = app(PurchaseReturnService::class)->create([
            'supplier_id' => $context['supplier']->id,
            'warehouse_id' => $context['warehouse']->id,
            'goods_receipt_id' => $receipt->id,
            'purchase_order_id' => $order->id,
            'return_date' => today()->toDateString(),
            'reason' => 'Batch return',
        ], [[
            'goods_receipt_item_id' => $receipt->items()->first()->id,
            'product_id' => $context['product']->id,
            'batch_id' => $batch->id,
            'unit_id' => $context['unit']->id,
            'quantity' => 1,
            'reason_code' => 'quality_failure',
        ]]);
        app(PurchaseReturnService::class)->submit($return);
        $this->asUser($context['approver']);
        app(PurchaseReturnService::class)->approve($return->fresh());
        app(PurchaseReturnPostingService::class)->post($return->fresh());
        $this->assertSame('3.000000', $batch->fresh()->available_quantity);

        $this->asUser($context['user']);
        $invalidOrder = $this->sentOrder($context, 1, 10, [
            'batch_required' => true,
            'expiry_required' => true,
        ]);
        $this->asUser($context['user']);
        $invalid = app(GoodsReceiptService::class)->create([
            'warehouse_id' => $context['warehouse']->id,
            'supplier_id' => $context['supplier']->id,
            'purchase_order_id' => $invalidOrder->id,
            'receipt_date' => today()->toDateString(),
        ], [[
            'purchase_order_item_id' => $invalidOrder->items()->first()->id,
            'product_id' => $context['product']->id,
            'received_quantity' => 1,
            'accepted_quantity' => 1,
            'rejected_quantity' => 0,
        ]]);
        app(GoodsReceiptService::class)->receive($invalid);
        try {
            app(GoodsReceiptPostingService::class)->post($invalid->fresh());
            $this->fail('Missing batch and expiry must rollback the complete posting.');
        } catch (BusinessRuleException) {
            $this->assertSame('3.000000', StockBalance::where('warehouse_id', $context['warehouse']->id)
                ->where('product_id', $context['product']->id)->value('quantity'));
            $this->assertSame(2, StockMovement::where('movement_type', 'purchase_receipt')->count());
            $this->assertSame('accepted', $invalid->fresh()->status);
        }

        $expiredOrder = $this->sentOrder($context, 1, 10, [
            'batch_required' => true,
            'expiry_required' => true,
        ]);
        $this->asUser($context['user']);
        $expired = app(GoodsReceiptService::class)->create([
            'warehouse_id' => $context['warehouse']->id,
            'supplier_id' => $context['supplier']->id,
            'purchase_order_id' => $expiredOrder->id,
            'receipt_date' => today()->toDateString(),
        ], [[
            'purchase_order_item_id' => $expiredOrder->items()->first()->id,
            'product_id' => $context['product']->id,
            'received_quantity' => 1,
            'accepted_quantity' => 1,
            'rejected_quantity' => 0,
            'batch_number' => 'EXPIRED-1',
            'expiry_date' => today()->subDay()->toDateString(),
        ]]);
        app(GoodsReceiptService::class)->receive($expired);
        $this->expectException(BusinessRuleException::class);
        app(GoodsReceiptPostingService::class)->post($expired->fresh());
    }

    public function test_roll_receipt_allocates_exact_cost_and_retry_creates_no_extra_rolls(): void
    {
        $context = $this->context();
        $rollProduct = $context['product']->replicate(['uuid', 'sku']);
        $rollProduct->forceFill([
            'sku' => 'ROLL-'.uniqid(),
            'name' => 'Film roll',
            'product_type' => 'film',
            'tracking_type' => 'roll',
            'costing_method' => 'specific',
            'standard_cost' => null,
        ])->save();
        $order = $this->sentOrder($context, 2, 10, [], $rollProduct);
        $this->asUser($context['user']);
        $receipt = app(GoodsReceiptService::class)->create([
            'warehouse_id' => $context['warehouse']->id,
            'supplier_id' => $context['supplier']->id,
            'purchase_order_id' => $order->id,
            'receipt_date' => today()->toDateString(),
        ], [[
            'purchase_order_item_id' => $order->items()->first()->id,
            'product_id' => $rollProduct->id,
            'received_quantity' => 3,
            'accepted_quantity' => 2,
            'rejected_quantity' => 1,
            'free_quantity' => 1,
            'rolls' => [
                ['supplier_roll_number' => 'SR-1', 'width' => 2, 'length' => 1],
                ['supplier_roll_number' => 'SR-2', 'width' => 2, 'length' => 1],
                ['supplier_roll_number' => 'SR-3', 'width' => 1, 'length' => 1],
            ],
        ]]);
        app(GoodsReceiptService::class)->receive($receipt);
        app(GoodsReceiptPostingService::class)->post($receipt->fresh());

        $rolls = InventoryRoll::where('goods_receipt_item_id', $receipt->items()->first()->id)->get();
        $this->assertCount(3, $rolls);
        $this->assertSame('5.000000', $rolls->reduce(
            fn (string $area, InventoryRoll $roll) => bcadd($area, $roll->original_area, 6),
            '0.000000'
        ));
        $this->assertSame('20.0000', $rolls->reduce(
            fn (string $cost, InventoryRoll $roll) => bcadd($cost, $roll->total_cost, 4),
            '0.0000'
        ));
        $this->assertTrue($rolls->every(fn (InventoryRoll $roll) => $roll->supplier_id === $context['supplier']->id
            && $roll->purchase_order_item_id === $order->items()->first()->id
            && $roll->goods_receipt_item_id === $receipt->items()->first()->id
            && $roll->warehouse_id === $context['warehouse']->id));
        try {
            app(GoodsReceiptPostingService::class)->post($receipt->fresh());
            $this->fail('Roll receipt retry must be rejected.');
        } catch (BusinessRuleException) {
            $this->assertSame(3, InventoryRoll::where('goods_receipt_item_id', $receipt->items()->first()->id)->count());
            $this->assertSame(3, StockMovement::where('movement_type', 'purchase_roll_receipt')->count());
        }

        $this->asUser($context['user']);
        $unusedReturn = app(PurchaseReturnService::class)->create([
            'supplier_id' => $context['supplier']->id,
            'warehouse_id' => $context['warehouse']->id,
            'goods_receipt_id' => $receipt->id,
            'purchase_order_id' => $order->id,
            'return_date' => today()->toDateString(),
            'reason' => 'Unused roll return',
        ], [[
            'goods_receipt_item_id' => $receipt->items()->first()->id,
            'product_id' => $rollProduct->id,
            'roll_id' => $rolls[0]->id,
            'unit_id' => $context['unit']->id,
            'quantity' => 1,
            'reason_code' => 'quality_failure',
        ]]);
        app(PurchaseReturnService::class)->submit($unusedReturn);
        $this->asUser($context['approver']);
        app(PurchaseReturnService::class)->approve($unusedReturn->fresh());
        app(PurchaseReturnPostingService::class)->post($unusedReturn->fresh());
        $this->assertSame('returned', $rolls[0]->fresh()->status);

        app(RollConsumptionService::class)->consume($rolls[1]->fresh(), '0.5', '1');
        $this->asUser($context['user']);
        $usedReturn = app(PurchaseReturnService::class)->create([
            'supplier_id' => $context['supplier']->id,
            'warehouse_id' => $context['warehouse']->id,
            'goods_receipt_id' => $receipt->id,
            'purchase_order_id' => $order->id,
            'return_date' => today()->toDateString(),
            'reason' => 'Used roll return',
        ], [[
            'goods_receipt_item_id' => $receipt->items()->first()->id,
            'product_id' => $rollProduct->id,
            'roll_id' => $rolls[1]->id,
            'unit_id' => $context['unit']->id,
            'quantity' => 1,
            'reason_code' => 'quality_failure',
        ]]);
        app(PurchaseReturnService::class)->submit($usedReturn);
        $this->asUser($context['approver']);
        app(PurchaseReturnService::class)->approve($usedReturn->fresh());
        try {
            app(PurchaseReturnPostingService::class)->post($usedReturn->fresh());
            $this->fail('A used roll cannot be returned to the supplier.');
        } catch (BusinessRuleException) {
            $this->assertSame('opened', $rolls[1]->fresh()->status);
        }
    }

    public function test_over_receipt_uses_cumulative_accepted_quantity_and_override_permission(): void
    {
        Config::set('purchasing.quantity_tolerance_percentage', 10);
        $context = $this->context();
        $post = function ($order, int $received, int $accepted, int $rejected = 0, int $free = 0) use ($context) {
            $this->asUser($context['user']);
            $receipt = app(GoodsReceiptService::class)->create([
                'warehouse_id' => $context['warehouse']->id,
                'supplier_id' => $context['supplier']->id,
                'purchase_order_id' => $order->id,
                'receipt_date' => today()->toDateString(),
            ], [[
                'purchase_order_item_id' => $order->items()->first()->id,
                'product_id' => $context['product']->id,
                'received_quantity' => $received,
                'accepted_quantity' => $accepted,
                'rejected_quantity' => $rejected,
                'free_quantity' => $free,
            ]]);
            app(GoodsReceiptService::class)->receive($receipt);
            app(GoodsReceiptPostingService::class)->post($receipt->fresh());

            return $receipt;
        };

        $cumulative = $this->sentOrder($context, 10, 10);
        $post($cumulative, 6, 6, 0, 2);
        $post($cumulative, 6, 5, 1);
        $this->assertSame('11.000000', $cumulative->items()->first()->received_quantity);
        $this->assertSame('13.000000', StockBalance::where('warehouse_id', $context['warehouse']->id)
            ->where('product_id', $context['product']->id)->value('quantity'));

        $blocked = $this->sentOrder($context, 10, 10);
        try {
            $post($blocked, 12, 12);
            $this->fail('Receipt above tolerance must be rejected.');
        } catch (BusinessRuleException) {
            $this->assertSame('0.000000', $blocked->items()->first()->received_quantity);
        }

        $permission = Permission::firstOrCreate(
            ['name' => 'goods_receipts.override_tolerance'],
            ['display_name' => 'goods_receipts.override_tolerance']
        );
        $context['user']->roles()->first()->permissions()->syncWithoutDetaching($permission);
        $override = $this->sentOrder($context, 10, 10);
        $post($override, 12, 12);
        $this->assertSame('12.000000', $override->items()->first()->received_quantity);
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
        app(SupplierInvoiceMatchingService::class)->approveVariance(
            $invoice->items()->first()->matches()->firstOrFail(),
            'Approved direct operational invoice',
            $context['approver']->id
        );
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
        $allocation = app(SupplierPaymentAllocationService::class)->allocate($payment->fresh(), $invoice->fresh(), '50');
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        try {
            app(SupplierPaymentAllocationService::class)->allocate($payment->fresh(), $invoice->fresh(), '66');
            $this->fail('Allocation cannot exceed the unallocated payment or invoice balance.');
        } catch (BusinessRuleException) {
            $this->assertSame('65.0000', $payment->fresh()->unallocated_amount);
        }
        app(SupplierPaymentAllocationService::class)->allocate($payment->fresh(), $invoice->fresh(), '65');
        $this->assertSame('paid', $invoice->fresh()->status);
        app(SupplierPaymentAllocationService::class)->reverse($allocation, 'Correction');
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertSame('50.0000', $invoice->fresh()->balance_due);
        try {
            app(SupplierPaymentAllocationService::class)->reverse($allocation->fresh(), 'Duplicate correction');
            $this->fail('Allocation reversal cannot run twice.');
        } catch (BusinessRuleException) {
            $this->assertSame('65.0000', $invoice->fresh()->paid_amount);
        }
        $invoice->forceFill(['paid_amount' => 1, 'balance_due' => 1, 'status' => 'paid'])->save();
        app(SupplierInvoiceBalanceService::class)->rebuild($invoice);
        $this->assertSame('65.0000', $invoice->fresh()->paid_amount);
        $this->assertSame('50.0000', $invoice->fresh()->balance_due);
        $otherSupplier = app(SupplierService::class)->create([
            'name' => 'Other supplier',
            'supplier_type' => 'distributor',
            'currency_id' => $context['currency']->id,
        ]);
        $otherPayment = app(SupplierPaymentService::class)->create([
            'supplier_id' => $otherSupplier->id,
            'payment_method_id' => $context['paymentMethod']->id,
            'payment_date' => today()->toDateString(),
            'currency_id' => $context['currency']->id,
            'amount' => 10,
        ]);
        app(SupplierPaymentService::class)->approve($otherPayment);
        app(SupplierPaymentService::class)->process($otherPayment->fresh());
        try {
            app(SupplierPaymentAllocationService::class)->allocate($otherPayment->fresh(), $invoice->fresh(), '10');
            $this->fail('Cross-supplier allocation must be forbidden.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $otherCurrency = Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name_ar' => 'Dollar', 'name_en' => 'Dollar', 'symbol' => 'USD', 'decimal_places' => 2, 'is_active' => true]
        );
        $currencyPayment = app(SupplierPaymentService::class)->create([
            'supplier_id' => $context['supplier']->id,
            'payment_method_id' => $context['paymentMethod']->id,
            'payment_date' => today()->toDateString(),
            'currency_id' => $otherCurrency->id,
            'amount' => 10,
        ]);
        app(SupplierPaymentService::class)->approve($currencyPayment);
        app(SupplierPaymentService::class)->process($currencyPayment->fresh());
        try {
            app(SupplierPaymentAllocationService::class)->allocate($currencyPayment->fresh(), $invoice->fresh(), '10');
            $this->fail('Cross-currency allocation must be forbidden.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame($before, StockMovement::count());
        $this->assertFalse(\Schema::hasTable('journal_entries'));
    }

    public function test_three_way_matching_blocks_unapproved_variance_and_invoice_retry(): void
    {
        $context = $this->context();
        $order = $this->sentOrder($context, 2, 10);
        $this->asUser($context['user']);
        $receipt = app(GoodsReceiptService::class)->create([
            'warehouse_id' => $context['warehouse']->id,
            'supplier_id' => $context['supplier']->id,
            'purchase_order_id' => $order->id,
            'receipt_date' => today()->toDateString(),
        ], [[
            'purchase_order_item_id' => $order->items()->first()->id,
            'product_id' => $context['product']->id,
            'received_quantity' => 2,
            'accepted_quantity' => 2,
            'rejected_quantity' => 0,
        ]]);
        app(GoodsReceiptService::class)->receive($receipt);
        app(GoodsReceiptPostingService::class)->post($receipt->fresh());
        $movementCount = StockMovement::count();
        $invoice = app(SupplierInvoiceService::class)->create([
            'supplier_id' => $context['supplier']->id,
            'purchase_order_id' => $order->id,
            'goods_receipt_id' => $receipt->id,
            'supplier_invoice_number' => 'VAR-'.uniqid(),
            'invoice_date' => today()->toDateString(),
            'currency_id' => $context['currency']->id,
        ], [[
            'purchase_order_item_id' => $order->items()->first()->id,
            'goods_receipt_item_id' => $receipt->items()->first()->id,
            'product_id' => $context['product']->id,
            'description' => 'Price variance',
            'quantity' => 2,
            'unit_id' => $context['unit']->id,
            'unit_price' => 12,
            'tax_rate' => 0,
        ]]);
        $match = $invoice->items()->first()->matches()->firstOrFail();
        $this->assertSame('price_variance', $match->status);
        app(SupplierInvoiceApprovalService::class)->submit($invoice);
        $this->asUser($context['approver']);
        app(SupplierInvoiceApprovalService::class)->approve($invoice->fresh());
        try {
            app(SupplierInvoicePostingService::class)->post($invoice->fresh());
            $this->fail('Unapproved matching variance must block posting.');
        } catch (BusinessRuleException) {
            $this->assertSame('0.000000', $order->items()->first()->invoiced_quantity);
        }
        app(SupplierInvoiceMatchingService::class)->approveVariance(
            $match->fresh(),
            'Accepted supplier price variance',
            $context['approver']->id
        );
        app(SupplierInvoicePostingService::class)->post($invoice->fresh());
        $this->assertSame('2.000000', $order->items()->first()->invoiced_quantity);
        try {
            app(SupplierInvoicePostingService::class)->post($invoice->fresh());
            $this->fail('Supplier invoice retry must be rejected.');
        } catch (BusinessRuleException) {
            $this->assertSame('2.000000', $order->items()->first()->invoiced_quantity);
            $this->assertSame($movementCount, StockMovement::count());
            $this->assertFalse(\Schema::hasTable('journal_entries'));
        }
    }

    public function test_credit_payment_statement_aging_and_status_priority_rebuild_from_official_records(): void
    {
        $context = $this->context();
        $this->asUser($context['user']);
        $invoice = app(SupplierInvoiceService::class)->create([
            'supplier_id' => $context['supplier']->id,
            'supplier_invoice_number' => 'AGING-'.uniqid(),
            'invoice_date' => today()->subDays(45)->toDateString(),
            'due_date' => today()->subDays(40)->toDateString(),
            'currency_id' => $context['currency']->id,
        ], [[
            'description' => 'Aging invoice',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 0,
        ]]);
        app(SupplierInvoiceApprovalService::class)->submit($invoice);
        $this->asUser($context['approver']);
        app(SupplierInvoiceApprovalService::class)->approve($invoice->fresh());
        app(SupplierInvoiceMatchingService::class)->approveVariance(
            $invoice->items()->first()->matches()->firstOrFail(),
            'Approved unmatched invoice',
            $context['approver']->id
        );
        app(SupplierInvoicePostingService::class)->post($invoice->fresh());
        $this->assertSame('overdue', $invoice->fresh()->status);

        $this->asUser($context['user']);
        $credit = app(SupplierCreditNoteService::class)->create([
            'supplier_id' => $context['supplier']->id,
            'supplier_invoice_id' => $invoice->id,
            'credit_date' => today()->toDateString(),
            'reason' => 'Partial supplier credit',
        ], [[
            'supplier_invoice_item_id' => $invoice->items()->first()->id,
            'description' => 'Partial credit',
            'quantity' => 1,
            'unit_price' => 30,
            'tax_rate' => 0,
        ]]);
        $this->asUser($context['approver']);
        app(SupplierCreditNoteService::class)->approve($credit);
        app(SupplierCreditNoteService::class)->post($credit->fresh());
        try {
            app(SupplierCreditNoteService::class)->post($credit->fresh());
            $this->fail('Credit note retry must be rejected.');
        } catch (BusinessRuleException) {
            $this->assertSame('70.0000', $invoice->fresh()->balance_due);
        }

        $payment = app(SupplierPaymentService::class)->create([
            'supplier_id' => $context['supplier']->id,
            'payment_method_id' => $context['paymentMethod']->id,
            'payment_date' => today()->toDateString(),
            'currency_id' => $context['currency']->id,
            'amount' => 20,
        ]);
        app(SupplierPaymentService::class)->approve($payment);
        app(SupplierPaymentService::class)->process($payment->fresh());
        app(SupplierPaymentAllocationService::class)->allocate($payment->fresh(), $invoice->fresh(), '20');
        $this->assertSame('overdue', $invoice->fresh()->status);
        $this->assertSame('50.0000', $invoice->fresh()->balance_due);

        $statement = app(SupplierStatementService::class)->build(
            $context['supplier'],
            $context['currency']->id,
            $context['branch']->id
        );
        $this->assertSame('50.0000', $statement['balance']);
        $this->assertNotEmpty($statement['entries']->where('type', 'invoice'));
        $this->assertNotEmpty($statement['entries']->where('type', 'credit'));
        $this->assertNotEmpty($statement['entries']->where('type', 'payment'));
        $aging = app(AccountsPayableAgingService::class)->report(
            $context['branch']->id,
            $context['currency']->id
        );
        $this->assertSame('50.0000', $aging[$context['currency']->id]['31_60']);
        $this->artisan('supplier-invoices:mark-overdue')->assertSuccessful();
        $this->assertSame('overdue', $invoice->fresh()->status);
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

    public function test_supplier_type_contract_rejects_unknown_values(): void
    {
        $context = $this->context();
        $this->assertSame(config('purchasing.supplier_types'), [
            'manufacturer', 'distributor', 'wholesaler', 'service_provider', 'other',
        ]);

        $this->expectException(BusinessRuleException::class);
        app(SupplierService::class)->create([
            'name' => 'Invalid supplier',
            'supplier_type' => 'materials',
            'currency_id' => $context['currency']->id,
        ]);
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
            'file' => $this->fakeImage('inspection.png'),
        ])->assertRedirect();
        $attachment = $receipt->attachments()->firstOrFail();
        $this->assertSame('local', $attachment->disk);
        $this->assertStringStartsWith('private/attachments/', $attachment->path);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f-]{36}\.(png|jpg|jpeg|webp|pdf)$/',
            $attachment->stored_name
        );
        Storage::disk('local')->assertExists($attachment->path);

        $otherBranch = Branch::create([
            'company_id' => $first['company']->id,
            'code' => 'B'.uniqid(),
            'name' => 'Other branch',
            'is_main' => false,
            'is_active' => true,
        ]);
        $branchUser = User::factory()->create([
            'company_id' => $first['company']->id,
            'branch_id' => $otherBranch->id,
            'status' => 'active',
        ]);
        $branchUser->roles()->attach($first['user']->roles()->first()->id);
        $branchUser->accessibleBranches()->attach($otherBranch->id, [
            'is_default' => true,
            'can_view' => true,
        ]);
        $this->actingAs($branchUser)
            ->get(route('attachments.download', $attachment))
            ->assertForbidden();

        $second = $this->context();
        $this->actingAs($second['user'])
            ->get(route('attachments.download', $attachment))
            ->assertForbidden();
    }

    public function test_receipt_attachment_rejects_spoofed_content_and_unknown_category(): void
    {
        Storage::fake('local');
        $context = $this->context();
        $order = $this->sentOrder($context, 1, 10);
        $this->asUser($context['user']);
        $receipt = app(GoodsReceiptService::class)->create([
            'warehouse_id' => $context['warehouse']->id,
            'supplier_id' => $context['supplier']->id,
            'purchase_order_id' => $order->id,
            'receipt_date' => today()->toDateString(),
        ], [[
            'purchase_order_item_id' => $order->items()->first()->id,
            'product_id' => $context['product']->id,
            'received_quantity' => 1,
            'accepted_quantity' => 1,
            'rejected_quantity' => 0,
        ]]);
        $path = tempnam(sys_get_temp_dir(), 'spoofed-image-');
        file_put_contents($path, 'plain text, not an image');
        $spoofed = new UploadedFile($path, 'damage.jpg', 'image/jpeg', null, true);

        $this->from(route('goods-receipts.show', $receipt))
            ->post(route('goods-receipts.attachments.store', $receipt), [
                'category' => 'goods_receipt_damage',
                'file' => $spoofed,
            ])
            ->assertSessionHasErrors('file');
        $this->post(route('goods-receipts.attachments.store', $receipt), [
            'category' => 'unknown',
            'file' => $this->fakeImage('inspection.png'),
        ])->assertSessionHasErrors('category');
        $this->assertSame(0, $receipt->attachments()->count());
    }

    private function sentOrder(
        array $context,
        int $quantity,
        int $unitPrice,
        array $itemOverrides = [],
        ?Product $product = null
    ) {
        $product ??= $context['product'];
        $order = app(PurchaseOrderService::class)->create([
            'supplier_id' => $context['supplier']->id,
            'order_date' => today()->toDateString(),
            'currency_id' => $context['currency']->id,
        ], [array_merge([
            'product_id' => $product->id,
            'ordered_quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate' => 0,
        ], $itemOverrides)]);
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
            'purchase_return', 'supplier_invoice', 'supplier_payment', 'supplier_credit_note',
            'stock_movement', 'roll',
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
            'supplier_type' => 'manufacturer',
            'currency_id' => $currency->id,
        ]);

        return compact(
            'currency', 'company', 'branch', 'user', 'approver', 'warehouse',
            'tax', 'unit', 'product', 'paymentMethod', 'supplier'
        );
    }
}
