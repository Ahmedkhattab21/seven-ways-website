<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\BranchAccountingSetting;
use App\Models\CashBox;
use App\Models\CashBoxCount;
use App\Models\CashBoxSession;
use App\Models\CashReceipt;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DocumentSequence;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\SalesInvoice;
use App\Services\BranchAccountingSettingsService;
use App\Services\CustomerPaymentService;
use App\Services\DocumentNumberService;
use App\Services\TreasuryBalanceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class CashCustomerPaymentTreasuryTest extends TestCase
{
    use BuildsTreasuryOperationsContext;
    use DatabaseTransactions;

    public function test_cash_payment_request_requires_cash_context_invoice_and_allocation(): void
    {
        $data = $this->context();

        $this->actingAs($data['user'])->post(route('customer-payments.store'), [
            'customer_id' => $data['customer']->id,
            'payment_method_id' => $data['cashMethod']->id,
            'payment_date' => '2040-01-10',
            'amount' => 570,
        ])->assertSessionHasErrors([
            'cash_box_id', 'cash_box_session_id', 'sales_invoice_id', 'allocation_amount',
        ]);

        $response = $this->actingAs($data['user'])->post(route('customer-payments.store'), [
            'customer_id' => $data['customer']->id,
            'payment_method_id' => $data['method']->id,
            'payment_date' => '2040-01-10',
            'amount' => 100,
            'reference_number' => 'CARD-REF-1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customer_payments', [
            'customer_id' => $data['customer']->id,
            'payment_method_id' => $data['method']->id,
            'cash_box_id' => null,
            'cash_box_session_id' => null,
        ]);
    }

    public function test_cash_payment_rejects_another_branch_box_and_invalid_sessions(): void
    {
        $data = $this->context();
        $otherBox = CashBox::query()
            ->where('company_id', $data['company']->id)
            ->where('branch_id', $data['secondBranch']->id)
            ->firstOrFail();
        $payload = $this->cashPayload($data);

        try {
            app(CustomerPaymentService::class)->record(array_replace($payload, ['cash_box_id' => $otherBox->id]));
            $this->fail('Another branch cash box must be rejected.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $data['session']->forceFill(['status' => 'closed', 'active_guard' => null, 'closed_at' => now()])->save();
        $this->expectException(BusinessRuleException::class);
        app(CustomerPaymentService::class)->record($payload);
    }

    public function test_approval_creates_one_posted_receipt_updates_cash_balance_and_allocates_invoice(): void
    {
        $data = $this->context();
        $balances = app(TreasuryBalanceService::class);
        $before = $balances->cashBox($data['cashBox'])['book_balance'];
        $payment = app(CustomerPaymentService::class)->record($this->cashPayload($data));

        $this->assertSame('recorded', $payment->status);
        $this->assertSame(0, CashReceipt::query()->where('customer_payment_id', $payment->id)->count());

        $approved = app(CustomerPaymentService::class)->approve($payment);

        $this->assertSame('allocated', $approved->status);
        $this->assertSame('570.0000', $approved->allocated_amount);
        $this->assertSame('0.0000', $approved->unallocated_amount);
        $this->assertSame('paid', $data['invoice']->fresh()->status);
        $this->assertSame('570.0000', $data['invoice']->fresh()->paid_amount);
        $this->assertSame('0.0000', $data['invoice']->fresh()->balance_due);
        $this->assertDatabaseHas('cash_receipts', [
            'customer_payment_id' => $payment->id,
            'cash_box_id' => $data['cashBox']->id,
            'cash_box_session_id' => $data['session']->id,
            'status' => 'posted',
            'amount' => 570,
        ]);
        $this->assertSame(
            '570.0000',
            bcsub($balances->cashBox($data['cashBox'])['book_balance'], $before, 4)
        );

        try {
            app(CustomerPaymentService::class)->approve($payment->fresh());
            $this->fail('The same payment cannot be approved twice.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, CashReceipt::query()->where('customer_payment_id', $payment->id)->count());
        }
    }

    public function test_real_store_show_and_approve_routes_persist_and_post_cash_context(): void
    {
        $data = $this->context();
        $data['user']->roles->first()->forceFill(['name' => 'branch_manager'])->save();
        $this->switchTreasuryActor($data['user']->fresh());
        $advanceAccount = Account::query()
            ->where('company_id', $data['company']->id)
            ->where('control_type', 'customer_advances')
            ->where('is_active', true)
            ->where('is_posting', true)
            ->firstOrFail();
        BranchAccountingSetting::query()->where('branch_id', $data['branch']->id)
            ->update(['customer_advance_account_id' => null]);
        app(BranchAccountingSettingsService::class)->update($data['branch'], [
            'customer_advance_account_id' => $advanceAccount->id,
        ]);

        $response = $this->actingAs($data['user'])->post(route('customer-payments.store'), [
            'customer_id' => $data['customer']->id,
            'payment_method_id' => $data['cashMethod']->id,
            'payment_date' => '2040-01-10',
            'amount' => 570,
            'cash_box_id' => $data['cashBox']->id,
            'cash_box_session_id' => $data['session']->id,
            'sales_invoice_id' => $data['invoice']->id,
            'allocation_amount' => 570,
        ]);

        $payment = CustomerPayment::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('customer-payments.show', $payment));
        $this->assertSame($data['cashBox']->id, $payment->cash_box_id);
        $this->assertSame($data['session']->id, $payment->cash_box_session_id);
        $this->assertSame($data['invoice']->id, $payment->intended_sales_invoice_id);
        $this->assertSame('570.0000', $payment->intended_allocation_amount);

        $this->actingAs($data['user'])->get(route('customer-payments.show', $payment))
            ->assertOk()
            ->assertSee($data['cashBox']->code)
            ->assertSee($data['cashBox']->name)
            ->assertSee($data['session']->session_number)
            ->assertSee($data['invoice']->invoice_number)
            ->assertSee('مسجلة')
            ->assertSee('اعتماد الدفعة');

        $otherBranchPayment = CustomerPayment::factory()->create([
            'company_id' => $data['company']->id,
            'branch_id' => $data['secondBranch']->id,
            'customer_id' => $data['customer']->id,
            'currency_id' => $data['currency']->id,
            'payment_method_id' => $data['method']->id,
            'received_by' => $data['user']->id,
            'status' => 'recorded',
        ]);
        $this->post(route('customer-payments.approve', $otherBranchPayment))->assertForbidden();

        $this->actingAs($data['user'])->post(route('customer-payments.approve', $payment))
            ->assertRedirect();

        $this->assertDatabaseHas('cash_receipts', [
            'customer_payment_id' => $payment->id,
            'cash_box_session_id' => $data['session']->id,
            'status' => 'posted',
        ]);
        $this->assertSame(1, $payment->fresh()->allocations()->count());
        $this->assertSame('paid', $data['invoice']->fresh()->status);
        $this->assertSame('0.0000', $data['invoice']->fresh()->balance_due);

        $this->post(route('customer-payments.approve', $payment))->assertForbidden();
        $this->assertSame(1, CashReceipt::query()->where('customer_payment_id', $payment->id)->count());
        $this->assertSame(1, $payment->fresh()->allocations()->count());
    }

    public function test_form_lists_only_current_branch_cash_context_and_uses_arabic_invoice_field(): void
    {
        $data = $this->context();
        $otherBox = CashBox::query()
            ->where('company_id', $data['company']->id)
            ->where('branch_id', $data['secondBranch']->id)
            ->firstOrFail();

        $this->actingAs($data['user'])->get(route('customer-payments.create'))
            ->assertOk()
            ->assertSee($data['cashBox']->name)
            ->assertDontSee($otherBox->name)
            ->assertSee($data['session']->session_number)
            ->assertSee($data['invoice']->invoice_number)
            ->assertSee('الفاتورة')
            ->assertDontSee('Invoice ID');
    }

    private function context(): array
    {
        $data = $this->treasuryContext();
        $paymentPermissions = [
            'customer_payments.view', 'customer_payments.record', 'customer_payments.approve',
            'customer_payments.allocate', 'customer_payments.print',
        ];
        foreach ($paymentPermissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission], ['display_name' => $permission]);
        }
        $permissions = Permission::query()->whereIn('name', $paymentPermissions)->pluck('id');
        $data['user']->roles->first()->permissions()->syncWithoutDetaching($permissions);
        $this->switchTreasuryActor($data['user']->fresh());

        $cashMethod = PaymentMethod::query()->forceCreate([
            'company_id' => $data['company']->id,
            'code' => 'CASH-UAT-'.uniqid(),
            'name' => 'نقدي',
            'type' => 'cash',
            'requires_reference' => false,
            'is_cash' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $customer = Customer::factory()->create([
            'company_id' => $data['company']->id,
            'created_branch_id' => $data['branch']->id,
            'assigned_branch_id' => $data['branch']->id,
            'name' => 'عميل فاتورة الإسكندرية UAT',
        ]);
        $invoice = SalesInvoice::factory()->create([
            'company_id' => $data['company']->id,
            'branch_id' => $data['branch']->id,
            'customer_id' => $customer->id,
            'currency_id' => $data['currency']->id,
            'invoice_number' => 'ALEX-INV-2040-000003',
            'status' => 'issued',
            'invoice_date' => '2040-01-10',
            'total' => 570,
            'paid_amount' => 0,
            'balance_due' => 570,
            'created_by' => $data['user']->id,
        ]);
        DocumentSequence::query()->forceCreate([
            'company_id' => $data['company']->id,
            'branch_id' => $data['branch']->id,
            'document_type' => 'customer_payment',
            'prefix' => 'ALEX-CP-{YYYY}-',
            'current_number' => 0,
            'padding' => 6,
            'reset_period' => 'yearly',
            'period_key' => null,
            'scope_key' => DocumentNumberService::scopeKey(
                $data['company']->id,
                $data['branch']->id,
                'customer_payment',
                null
            ),
            'is_active' => true,
        ]);
        $cashBox = CashBox::query()
            ->where('company_id', $data['company']->id)
            ->where('branch_id', $data['branch']->id)
            ->firstOrFail();
        $cashBox->forceFill(['requires_shift_opening' => true])->save();
        $session = CashBoxSession::query()->forceCreate([
            'company_id' => $data['company']->id,
            'branch_id' => $data['branch']->id,
            'cash_box_id' => $cashBox->id,
            'custodian_user_id' => $data['user']->id,
            'session_number' => 'ALEX-CS-2040-000001',
            'business_date' => '2040-01-10',
            'status' => 'counting',
            'active_guard' => 'active',
            'opening_book_balance' => 0,
            'opening_counted_balance' => 0,
            'opening_difference' => 0,
            'opened_by' => $data['user']->id,
            'opened_at' => now(),
            'counting_started_by' => $data['user']->id,
            'counting_started_at' => now(),
        ]);
        CashBoxCount::factory()->create([
            'company_id' => $data['company']->id,
            'cash_box_session_id' => $session->id,
            'count_type' => 'opening',
            'status' => 'approved',
            'counted_by' => $data['user']->id,
            'reviewed_by' => $data['approver']->id,
            'approved_by' => $data['approver']->id,
            'reviewed_at' => now(),
            'approved_at' => now(),
        ]);

        return $data + compact('cashMethod', 'customer', 'invoice', 'cashBox', 'session');
    }

    private function cashPayload(array $data): array
    {
        return [
            'customer_id' => $data['customer']->id,
            'payment_method_id' => $data['cashMethod']->id,
            'payment_date' => '2040-01-10',
            'amount' => 570,
            'cash_box_id' => $data['cashBox']->id,
            'cash_box_session_id' => $data['session']->id,
            'sales_invoice_id' => $data['invoice']->id,
            'allocation_amount' => 570,
        ];
    }
}
