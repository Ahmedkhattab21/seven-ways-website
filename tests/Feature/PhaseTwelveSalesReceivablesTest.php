<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\BranchServicePackage;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\SalesProductReturn;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\AccountsReceivableAgingService;
use App\Services\CustomerPaymentService;
use App\Services\CustomerRefundService;
use App\Services\CustomerStatementService;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\OperationalDepositConversionService;
use App\Services\PaymentAllocationService;
use App\Services\QuotationToSalesInvoiceService;
use App\Services\SalesCreditNoteService;
use App\Services\SalesInvoiceApprovalService;
use App\Services\SalesInvoiceBalanceService;
use App\Services\SalesInvoiceIssuanceService;
use App\Services\SalesInvoiceService;
use App\Services\SalesProductReturnService;
use App\Services\WorkOrderToInvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseTwelveSalesReceivablesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_work_order_invoice_requires_delivery_and_keeps_snapshot_without_stock_effect(): void
    {
        $context = $this->context();
        $order = $this->workOrder($context, 'in_progress');
        try {
            app(WorkOrderToInvoiceService::class)->create($order);
            $this->fail('Undelivered order must be rejected.');
        } catch (BusinessRuleException) {
            $this->assertTrue(true);
        }
        $order->forceFill(['status' => 'delivered', 'delivered_at' => now()])->save();
        $before = StockMovement::count();
        $invoice = app(WorkOrderToInvoiceService::class)->create($order->fresh());
        $snapshot = $invoice->customer_name_snapshot;
        $context['customer']->forceFill(['name' => 'Changed later'])->save();
        $this->assertSame($snapshot, $invoice->fresh()->customer_name_snapshot);
        $this->assertSame('100.0000', $invoice->items->first()->unit_price);
        $this->assertSame($before, StockMovement::count());

        $this->asUser($context['approver']);
        app(SalesInvoiceApprovalService::class)->submit($invoice);
        app(SalesInvoiceApprovalService::class)->approve($invoice->fresh());
        app(SalesInvoiceIssuanceService::class)->issue($invoice->fresh());
        $this->expectException(BusinessRuleException::class);
        app(WorkOrderToInvoiceService::class)->create($order->fresh());
    }

    public function test_direct_sale_issues_stock_once_and_backend_calculates_tax_and_discount(): void
    {
        $context = $this->context();
        app(InventoryService::class)->receive($context['warehouse'], $context['product'], '10', '20', 'stock_opening');
        $invoice = app(SalesInvoiceService::class)->createDirect([
            'customer_id' => $context['customer']->id, 'invoice_date' => today()->toDateString(),
            'discount_type' => 'percentage', 'discount_value' => 10,
        ], [[
            'item_type' => 'product', 'product_id' => $context['product']->id,
            'warehouse_id' => $context['warehouse']->id, 'quantity' => 2,
        ]]);
        $invoice->refresh();
        $this->assertSame('20.0000', $invoice->discount_amount);
        $this->assertSame('27.0000', $invoice->tax_amount);
        $this->assertSame('207.0000', $invoice->total);
        $this->assertSame('10.000000', StockBalance::where('warehouse_id', $context['warehouse']->id)->where('product_id', $context['product']->id)->value('quantity'));
        $this->asUser($context['approver']);
        app(SalesInvoiceApprovalService::class)->submit($invoice);
        app(SalesInvoiceApprovalService::class)->approve($invoice->fresh());
        app(SalesInvoiceIssuanceService::class)->issue($invoice->fresh());
        $this->assertSame('8.000000', StockBalance::where('warehouse_id', $context['warehouse']->id)->where('product_id', $context['product']->id)->value('quantity'));
        try {
            app(SalesInvoiceIssuanceService::class)->issue($invoice->fresh());
            $this->fail('The same direct sale must not issue stock twice.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, StockMovement::where('reference_type', 'sales_invoice')->count());
        }
        $returnKey = (string) Str::uuid();
        $note = app(SalesProductReturnService::class)->return(
            $invoice->items->first(),
            $context['warehouse'],
            '1',
            'Customer return',
            $returnKey
        );
        $this->assertSame('9.000000', StockBalance::where('warehouse_id', $context['warehouse']->id)->where('product_id', $context['product']->id)->value('quantity'));
        $this->assertSame('product_return', $note->reason_code);
        $retry = app(SalesProductReturnService::class)->return(
            $invoice->items->first(),
            $context['warehouse'],
            '1',
            'Customer return',
            $returnKey
        );
        $this->assertTrue($note->is($retry));
        $this->assertSame(1, SalesProductReturn::where('sales_invoice_item_id', $invoice->items->first()->id)->count());
        $this->assertSame(1, StockMovement::where('movement_type', 'sales_return')->count());
        $this->assertSame('9.000000', StockBalance::where('warehouse_id', $context['warehouse']->id)->where('product_id', $context['product']->id)->value('quantity'));
        try {
            app(SalesProductReturnService::class)->return(
                $invoice->items->first(),
                $context['warehouse'],
                '2',
                'Excess return',
                (string) Str::uuid()
            );
            $this->fail('Returned quantity cannot exceed the sold quantity.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, StockMovement::where('movement_type', 'sales_return')->count());
        }
    }

    public function test_payment_allocation_updates_status_and_reverse_preserves_history(): void
    {
        $context = $this->context();
        $invoice = $this->issuedInvoice($context, 115);
        $payment = app(CustomerPaymentService::class)->record([
            'customer_id' => $context['customer']->id, 'payment_method_id' => $context['paymentMethod']->id,
            'payment_date' => today()->toDateString(), 'amount' => 115,
        ]);
        app(CustomerPaymentService::class)->approve($payment);
        $allocation = app(PaymentAllocationService::class)->allocate($payment->fresh(), $invoice->fresh(), '50');
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        app(PaymentAllocationService::class)->allocate($payment->fresh(), $invoice->fresh(), '65');
        $this->assertSame('paid', $invoice->fresh()->status);
        try {
            app(PaymentAllocationService::class)->allocate($payment->fresh(), $invoice->fresh(), '1');
            $this->fail('A fully allocated payment cannot be allocated again.');
        } catch (BusinessRuleException) {
            $this->assertSame(2, $payment->allocations()->count());
        }
        app(PaymentAllocationService::class)->reverse($allocation, 'Correction');
        $this->assertNotNull($allocation->fresh()->reversed_at);
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->expectException(BusinessRuleException::class);
        app(PaymentAllocationService::class)->reverse($allocation->fresh(), 'Repeated correction');
    }

    public function test_deposit_converts_once_and_excess_remains_unallocated(): void
    {
        $context = $this->context();
        $invoice = $this->issuedInvoice($context, 115);
        $deposit = AppointmentDeposit::query()->forceCreate([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'appointment_id' => $context['appointment']->id, 'receipt_number' => 'DEP-'.uniqid(),
            'amount' => 150, 'payment_method_id' => $context['paymentMethod']->id, 'received_at' => now(),
            'status' => 'recorded', 'received_by' => $context['user']->id,
        ]);
        $payment = app(OperationalDepositConversionService::class)->convert($deposit, $invoice);
        $this->assertSame('35.0000', $payment->fresh()->unallocated_amount);
        $this->assertSame('paid', $invoice->fresh()->status);
        try {
            app(OperationalDepositConversionService::class)->convert($deposit->fresh(), $invoice->fresh());
            $this->fail('A deposit cannot create two payments.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, \App\Models\CustomerPayment::where('appointment_deposit_id', $deposit->id)->count());
        }
        $cancelled = $deposit->replicate(['receipt_number', 'uuid']);
        $cancelled->forceFill([
            'receipt_number' => 'DEP-'.uniqid(),
            'status' => 'cancelled',
            'converted_payment_id' => null,
            'converted_at' => null,
        ])->save();
        try {
            app(OperationalDepositConversionService::class)->convert($cancelled, $invoice->fresh());
            $this->fail('A cancelled deposit cannot be converted.');
        } catch (BusinessRuleException) {
            $this->assertNull($cancelled->fresh()->converted_payment_id);
        }
    }

    public function test_credit_note_refund_statement_aging_and_overdue_are_operational(): void
    {
        $context = $this->context();
        $invoice = $this->issuedInvoice($context, 115);
        $credits = app(SalesCreditNoteService::class);
        $note = $credits->create($invoice, [
            'credit_note_date' => today()->toDateString(), 'reason_code' => 'service_refund', 'reason' => 'Service adjustment',
        ], [['sales_invoice_item_id' => $invoice->items()->first()->id, 'quantity' => 0.5]]);
        $credits->approve($note);
        $credits->issue($note->fresh());
        $this->assertSame('57.5000', $invoice->fresh()->credited_amount);
        $refunds = app(CustomerRefundService::class);
        $refund = $refunds->create([
            'sales_credit_note_id' => $note->id, 'payment_method_id' => $context['paymentMethod']->id,
            'refund_date' => today()->toDateString(), 'amount' => 20, 'reason' => 'Customer refund',
        ]);
        $refunds->approve($refund);
        $refunds->process($refund->fresh());
        $this->assertSame('processed', $refund->fresh()->status);
        $this->assertSame('57.5000', $invoice->fresh()->balance_due);
        $this->assertSame('20.0000', $invoice->fresh()->refunded_amount);
        try {
            $refunds->process($refund->fresh());
            $this->fail('A processed refund cannot be processed twice.');
        } catch (BusinessRuleException) {
            $this->assertSame('20.0000', $note->fresh()->refunded_amount);
            $this->assertSame('57.5000', $invoice->fresh()->balance_due);
        }
        $statement = app(CustomerStatementService::class)->build($context['customer'], $context['currency']->id);
        $this->assertNotEmpty($statement['entries']);
        $invoice->forceFill(['status' => 'issued', 'due_date' => today()->subDays(40), 'balance_due' => 50])->save();
        $aging = app(AccountsReceivableAgingService::class)->report($context['branch']->id, $context['currency']->id);
        $this->assertSame('50.0000', $aging[$context['currency']->id]['31_60']);
        $this->artisan('invoices:mark-overdue')->assertSuccessful();
        $this->assertSame('overdue', $invoice->fresh()->status);
        $this->assertSame(0, \DB::table('journal_entries')->count());
    }

    public function test_balance_is_rebuilt_from_official_records_and_overlapping_credit_drafts_cannot_both_issue(): void
    {
        $context = $this->context();
        $invoice = $this->issuedInvoice($context, 115);
        $payment = app(CustomerPaymentService::class)->record([
            'customer_id' => $context['customer']->id,
            'payment_method_id' => $context['paymentMethod']->id,
            'payment_date' => today()->toDateString(),
            'amount' => 50,
        ]);
        app(CustomerPaymentService::class)->approve($payment);
        app(PaymentAllocationService::class)->allocate($payment->fresh(), $invoice, '50');
        $invoice->forceFill(['paid_amount' => 1, 'credited_amount' => 2, 'refunded_amount' => 3, 'balance_due' => 4])->save();
        app(SalesInvoiceBalanceService::class)->rebuild($invoice);
        $this->assertSame('50.0000', $invoice->fresh()->paid_amount);
        $this->assertSame('65.0000', $invoice->fresh()->balance_due);

        $credits = app(SalesCreditNoteService::class);
        $creditData = [
            'credit_note_date' => today()->toDateString(),
            'reason_code' => 'service_refund',
            'reason' => 'Full correction',
        ];
        $creditItems = [['sales_invoice_item_id' => $invoice->items()->first()->id, 'quantity' => 1]];
        $first = $credits->create($invoice->fresh(), $creditData, $creditItems);
        $second = $credits->create($invoice->fresh(), $creditData, $creditItems);
        $credits->approve($first);
        $credits->approve($second);
        $credits->issue($first->fresh());
        $this->expectException(BusinessRuleException::class);
        $credits->issue($second->fresh());
    }

    public function test_cross_company_invoice_access_is_forbidden(): void
    {
        $first = $this->context();
        $invoice = app(SalesInvoiceService::class)->createDirect([
            'customer_id' => $first['customer']->id, 'invoice_date' => today()->toDateString(),
        ], [['item_type' => 'custom', 'description' => 'Service', 'quantity' => 1, 'unit_price' => 100]]);
        $second = $this->context();
        $this->actingAs($second['user'])->get(route('sales-invoices.show', $invoice))->assertForbidden();
    }

    public function test_approved_quotation_converts_directly_to_one_invoice_without_work_order(): void
    {
        $context = $this->context();
        $quotation = Quotation::query()->forceCreate([
            'uuid' => (string) Str::uuid(),
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'quotation_number' => 'QT-'.uniqid(),
            'customer_id' => $context['customer']->id,
            'vehicle_id' => $context['vehicle']->id,
            'status' => 'accepted',
            'quotation_date' => today(),
            'valid_until' => today()->addWeek(),
            'currency_id' => $context['currency']->id,
            'subtotal' => 100,
            'tax_amount' => 15,
            'total' => 115,
            'created_by' => $context['user']->id,
        ]);
        $quotation->items()->create([
            'item_type' => 'service',
            'service_id' => $context['service']->id,
            'description' => 'Service snapshot',
            'quantity' => 1,
            'unit_price' => 100,
            'gross_amount' => 100,
            'net_amount' => 100,
            'tax_rate' => 15,
            'tax_amount' => 15,
            'total' => 115,
            'price_source' => 'service_price',
        ]);

        $first = app(QuotationToSalesInvoiceService::class)->convert($quotation);
        $second = app(QuotationToSalesInvoiceService::class)->convert($quotation->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame($quotation->id, $first->quotation_id);
        $this->assertNull($first->work_order_id);
        $this->assertSame('draft', $first->status);
        $this->assertSame('converted', $quotation->fresh()->status);
        $this->assertSame(1, SalesInvoice::query()->where('quotation_id', $quotation->id)->count());
    }

    public function test_direct_invoice_accepts_product_service_package_and_custom_items(): void
    {
        $context = $this->context();
        BranchService::query()->forceCreate([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'service_id' => $context['service']->id,
            'is_available' => true,
            'default_price' => 80,
            'is_active' => true,
        ]);
        $package = ServicePackage::query()->forceCreate([
            'uuid' => (string) Str::uuid(),
            'company_id' => $context['company']->id,
            'code' => 'PKG'.uniqid(),
            'name' => 'Service package',
            'package_type' => 'fixed',
            'is_active' => true,
        ]);
        $package->items()->create([
            'service_id' => $context['service']->id,
            'quantity' => 1,
            'is_required' => true,
        ]);
        BranchServicePackage::query()->forceCreate([
            'branch_id' => $context['branch']->id,
            'service_package_id' => $package->id,
            'price' => 70,
            'is_available' => true,
            'effective_from' => today(),
        ]);

        $invoice = app(SalesInvoiceService::class)->createDirect([
            'customer_id' => $context['customer']->id,
            'vehicle_id' => $context['vehicle']->id,
            'invoice_date' => today()->toDateString(),
        ], [
            ['item_type' => 'product', 'product_id' => $context['product']->id, 'warehouse_id' => $context['warehouse']->id, 'quantity' => 1],
            ['item_type' => 'service', 'service_id' => $context['service']->id, 'quantity' => 1],
            ['item_type' => 'package', 'service_package_id' => $package->id, 'quantity' => 1],
            ['item_type' => 'custom', 'description' => 'Custom item', 'quantity' => 1, 'unit_price' => 20],
        ]);

        $this->assertSame(['product', 'service', 'package', 'custom'], $invoice->items->pluck('item_type')->all());
        $this->assertSame('80.0000', $invoice->items->firstWhere('item_type', 'service')->unit_price);
        $this->assertSame('70.0000', $invoice->items->firstWhere('item_type', 'package')->unit_price);
    }

    private function issuedInvoice(array $context, float $amount): SalesInvoice
    {
        $invoice = app(SalesInvoiceService::class)->createDirect([
            'customer_id' => $context['customer']->id, 'invoice_date' => today()->toDateString(),
        ], [['item_type' => 'custom', 'description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 15]]);
        $this->asUser($context['approver']);
        app(SalesInvoiceApprovalService::class)->submit($invoice);
        app(SalesInvoiceApprovalService::class)->approve($invoice->fresh());
        app(SalesInvoiceIssuanceService::class)->issue($invoice->fresh());
        $this->asUser($context['user']);

        return $invoice->fresh();
    }

    private function workOrder(array $context, string $status): WorkOrder
    {
        $order = WorkOrder::query()->forceCreate([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'warehouse_id' => $context['warehouse']->id, 'work_order_number' => 'WO-'.uniqid(),
            'customer_id' => $context['customer']->id, 'vehicle_id' => $context['vehicle']->id,
            'status' => 'draft', 'created_by' => $context['user']->id,
        ]);
        $order->services()->create([
            'service_id' => $context['service']->id, 'description' => 'Service snapshot',
            'quantity' => 1, 'status' => 'completed', 'unit_price_snapshot' => 100,
            'total_snapshot' => 100, 'actual_total_cost' => 30,
        ]);
        $order->forceFill(['status' => $status])->save();

        return $order;
    }

    private function asUser(User $user): void
    {
        $this->actingAs($user);
        app(TenantContext::class)->initialize($user);
    }

    private function context(): array
    {
        $currency = Currency::firstOrCreate(['code' => 'EGP'], ['name_ar' => 'جنيه مصري', 'name_en' => 'Egyptian Pound', 'symbol' => 'ج.م', 'decimal_places' => 2, 'is_active' => true]);
        $company = Company::create(['name' => 'Phase 12 '.uniqid(), 'currency_id' => $currency->id, 'is_active' => true]);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'B'.uniqid(), 'name' => 'Branch', 'is_main' => true, 'is_active' => true]);
        $branch->settings()->create(['working_day_start' => '08:00:00', 'working_day_end' => '20:00:00', 'weekend_days' => []]);
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $approver = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $role = Role::create(['company_id' => $company->id, 'name' => 'finance_'.uniqid(), 'display_name' => 'Finance', 'scope' => 'company', 'is_active' => true]);
        foreach (['sales_invoices.view', 'sales_invoices.create', 'sales_invoices.direct_sale', 'sales_invoices.submit', 'sales_invoices.approve', 'sales_invoices.issue', 'sales_invoices.print', 'customer_payments.view', 'customer_payments.record', 'customer_payments.approve', 'customer_payments.allocate', 'customer_payments.reverse_allocation', 'customer_payments.print', 'sales_credit_notes.view', 'sales_credit_notes.create', 'sales_credit_notes.approve', 'sales_credit_notes.issue', 'sales_credit_notes.print', 'customer_refunds.view', 'customer_refunds.create', 'customer_refunds.approve', 'customer_refunds.process', 'customer_statements.view', 'accounts_receivable.aging'] as $name) {
            $role->permissions()->syncWithoutDetaching(Permission::firstOrCreate(['name' => $name], ['display_name' => $name]));
        }
        $user->roles()->attach($role);
        $approver->roles()->attach($role);
        foreach ([$user, $approver] as $actor) {
            $actor->accessibleBranches()->attach($branch->id, ['is_default' => true, 'can_view' => true]);
        }
        $this->asUser($user);
        $customer = Customer::factory()->create(['company_id' => $company->id, 'created_branch_id' => $branch->id, 'assigned_branch_id' => $branch->id]);
        $brand = VehicleBrand::create(['name_ar' => 'Brand', 'is_active' => true]);
        $model = VehicleModel::create(['vehicle_brand_id' => $brand->id, 'name_ar' => 'Model', 'is_active' => true]);
        $vehicle = Vehicle::query()->forceCreate(['company_id' => $company->id, 'customer_id' => $customer->id, 'created_branch_id' => $branch->id, 'vehicle_brand_id' => $brand->id, 'vehicle_model_id' => $model->id, 'plate_number' => 'P'.uniqid(), 'normalized_plate_number' => 'P'.uniqid(), 'status' => 'active']);
        $warehouse = Warehouse::query()->forceCreate(['company_id' => $company->id, 'branch_id' => $branch->id, 'code' => 'W'.uniqid(), 'name' => 'Main', 'warehouse_type' => 'main', 'is_active' => true, 'is_system' => false, 'allows_work_order_issue' => true]);
        $tax = Tax::query()->forceCreate(['company_id' => $company->id, 'code' => 'VAT'.uniqid(), 'name' => 'VAT', 'rate' => 15, 'tax_type' => 'vat', 'is_active' => true]);
        $unit = Unit::query()->forceCreate(['company_id' => $company->id, 'code' => 'U'.uniqid(), 'name' => 'Piece', 'symbol' => 'pc', 'unit_type' => 'quantity', 'decimal_places' => 6, 'is_active' => true]);
        $productCategory = ProductCategory::query()->forceCreate(['company_id' => $company->id, 'code' => 'P'.uniqid(), 'name' => 'Products', 'is_active' => true]);
        $product = Product::query()->forceCreate(['company_id' => $company->id, 'category_id' => $productCategory->id, 'sku' => 'SKU'.uniqid(), 'name' => 'Product', 'product_type' => 'consumable', 'tracking_type' => 'quantity', 'purchase_unit_id' => $unit->id, 'stock_unit_id' => $unit->id, 'sale_unit_id' => $unit->id, 'default_tax_id' => $tax->id, 'costing_method' => 'weighted_average', 'default_sale_price' => 100, 'is_sellable' => true, 'is_consumable' => true, 'is_active' => true]);
        $serviceCategory = ServiceCategory::query()->forceCreate(['company_id' => $company->id, 'code' => 'S'.uniqid(), 'name' => 'Services', 'is_active' => true]);
        $service = Service::query()->forceCreate(['company_id' => $company->id, 'service_category_id' => $serviceCategory->id, 'code' => 'SV'.uniqid(), 'name' => 'Service', 'service_type' => 'ppf', 'pricing_type' => 'fixed', 'default_duration_minutes' => 60, 'default_tax_id' => $tax->id, 'requires_vehicle' => true, 'is_active' => true]);
        $paymentMethod = PaymentMethod::query()->forceCreate(['company_id' => $company->id, 'code' => 'CASH'.uniqid(), 'name' => 'Cash', 'type' => 'cash', 'is_cash' => true, 'is_active' => true]);
        $appointment = Appointment::query()->forceCreate(['company_id' => $company->id, 'branch_id' => $branch->id, 'appointment_number' => 'APT'.uniqid(), 'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'status' => 'completed', 'scheduled_start' => now(), 'scheduled_end' => now()->addHour(), 'estimated_duration_minutes' => 60, 'booking_source' => 'walk_in', 'priority' => 'normal', 'deposit_required' => false, 'deposit_amount' => 0, 'deposit_status' => 'not_required', 'created_by' => $user->id]);
        foreach (['sales_invoice' => '{BRANCH}-INV-{YYYY}-', 'customer_payment' => '{BRANCH}-PAY-{YYYY}-', 'sales_credit_note' => '{BRANCH}-CN-{YYYY}-', 'customer_refund' => '{BRANCH}-REF-{YYYY}-', 'stock_movement' => '{BRANCH}-MOV-{YYYY}-'] as $type => $prefix) {
            DocumentSequence::query()->forceCreate(['company_id' => $company->id, 'branch_id' => $branch->id, 'document_type' => $type, 'prefix' => $prefix, 'current_number' => 0, 'padding' => 6, 'reset_period' => 'yearly', 'period_key' => now()->format('Y'), 'scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, $type, now()->format('Y')), 'is_active' => true]);
        }

        return compact('currency', 'company', 'branch', 'user', 'approver', 'customer', 'vehicle', 'warehouse', 'product', 'service', 'paymentMethod', 'appointment');
    }
}
