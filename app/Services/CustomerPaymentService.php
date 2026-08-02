<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\CustomerPaymentApproved;
use App\Events\CustomerPaymentRecorded;
use App\Models\AccountingPostingLink;
use App\Models\CashBox;
use App\Models\CashBoxSession;
use App\Models\CashReceipt;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentMethod;
use App\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerPaymentService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit,
        private CashBoxCustodianService $custodians,
        private CashSessionOperationalGuard $sessionGuard,
        private PostingAccountResolver $accounts,
        private CashOperationPostingService $cashPosting,
        private PaymentAllocationService $allocations
    ) {
    }

    public function availableCashBoxes(): Collection
    {
        $user = $this->tenant->user();

        return CashBox::query()
            ->where('company_id', $this->tenant->companyId())
            ->where('branch_id', $this->tenant->branchId())
            ->where('currency_id', $this->tenant->company()->currency_id)
            ->where('status', 'active')
            ->where('allows_receipts', true)
            ->when(! $user->hasPermission('treasury.cash_boxes.manage_custodians'), function ($query) use ($user) {
                $query->whereHas('custodians', fn ($custodians) => $custodians
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->where('can_receive', true)
                    ->whereDate('valid_from', '<=', today())
                    ->where(fn ($dates) => $dates->whereNull('valid_to')->orWhereDate('valid_to', '>=', today()))
                );
            })
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function availableCashSessions(Collection $cashBoxes): Collection
    {
        return CashBoxSession::query()
            ->where('company_id', $this->tenant->companyId())
            ->where('branch_id', $this->tenant->branchId())
            ->whereIn('cash_box_id', $cashBoxes->pluck('id'))
            ->where('active_guard', 'active')
            ->where('status', 'counting')
            ->whereNull('closed_at')
            ->whereHas('counts', fn ($query) => $query
                ->where('count_type', 'opening')
                ->where('status', 'approved'))
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get();
    }

    public function record(array $data): CustomerPayment
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::forUser($this->tenant->user())->whereKey($data['customer_id'])->firstOrFail();
            $method = PaymentMethod::query()
                ->whereKey($data['payment_method_id'])
                ->where('company_id', $customer->company_id)
                ->where('is_active', true)
                ->firstOrFail();
            $currencyId = (int) ($data['currency_id'] ?? $this->tenant->company()->currency_id);
            $isCash = $method->isCash();
            $allocationAmount = $data['allocation_amount'] ?? $data['allocated_amount'] ?? null;
            $data['allocation_amount'] = $allocationAmount;

            if (bccomp((string) $data['amount'], '0', 4) !== 1
                || ($method->requires_reference && empty($data['reference_number']))) {
                throw new BusinessRuleException('يجب إدخال مبلغ موجب والرقم المرجعي المطلوب لطريقة الدفع.');
            }

            if ($isCash) {
                $this->cashContext($data, $currencyId);
                $this->invoiceForPayment($data, $customer, $currencyId);
            } elseif (! empty($data['cash_box_id']) || ! empty($data['cash_box_session_id'])) {
                throw new BusinessRuleException('لا تُستخدم الخزينة أو جلستها مع طرق الدفع غير النقدية.');
            }

            $payment = new CustomerPayment;
            $payment->forceFill([
                'company_id' => $customer->company_id,
                'branch_id' => $this->tenant->branchId(),
                'payment_number' => $this->numbers->next(
                    'customer_payment',
                    $customer->company_id,
                    $this->tenant->branchId(),
                    $data['payment_date']
                ),
                'customer_id' => $customer->id,
                'currency_id' => $currencyId,
                'payment_method_id' => $method->id,
                'cash_box_id' => $isCash ? $data['cash_box_id'] : null,
                'cash_box_session_id' => $isCash ? $data['cash_box_session_id'] : null,
                'intended_sales_invoice_id' => $data['sales_invoice_id'] ?? null,
                'intended_allocation_amount' => $allocationAmount,
                'status' => 'recorded',
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'allocated_amount' => 0,
                'unallocated_amount' => $data['amount'],
                'reference_number' => $data['reference_number'] ?? null,
                'source_type' => $isCash ? 'cash' : ($data['source_type'] ?? 'manual'),
                'appointment_deposit_id' => $data['appointment_deposit_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('customer_payment.recorded', $payment);
            DB::afterCommit(fn () => event(new CustomerPaymentRecorded($payment->id)));

            return $payment;
        });
    }

    public function approve(CustomerPayment $payment): CustomerPayment
    {
        return DB::transaction(function () use ($payment) {
            $payment = CustomerPayment::query()
                ->with('paymentMethod')
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(
                $payment->company_id === $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($payment->branch),
                403
            );
            if ($payment->status !== 'recorded') {
                throw new BusinessRuleException('لا يمكن اعتماد إلا الدفعات المسجلة.');
            }

            $cashReceipt = null;
            if ($payment->paymentMethod->isCash()) {
                $context = $this->cashContext([
                    'cash_box_id' => $payment->cash_box_id,
                    'cash_box_session_id' => $payment->cash_box_session_id,
                    'payment_date' => $payment->payment_date->toDateString(),
                    'amount' => $payment->amount,
                ], (int) $payment->currency_id, $payment->branch_id, false);
                if (CashReceipt::query()->where('customer_payment_id', $payment->id)->exists()) {
                    throw new BusinessRuleException('تم إنشاء حركة خزينة لهذه الدفعة من قبل.');
                }
                $cashReceipt = $this->createCashReceipt($payment, $context['box'], $context['session']);
            }

            $payment->forceFill([
                'status' => 'approved',
                'approved_by' => $this->tenant->user()->id,
                'approved_at' => now(),
            ])->save();

            if ($cashReceipt?->journal_entry_id) {
                $link = new AccountingPostingLink;
                $link->forceFill([
                    'company_id' => $payment->company_id,
                    'branch_id' => $payment->branch_id,
                    'source_type' => $payment->getMorphClass(),
                    'source_id' => $payment->id,
                    'source_uuid' => $payment->uuid,
                    'posting_action' => 'post',
                    'journal_entry_id' => $cashReceipt->journal_entry_id,
                    'idempotency_key' => hash('sha256', $payment->getMorphClass().':'.$payment->id.':post'),
                    'status' => 'posted',
                    'created_by' => $this->tenant->user()->id,
                ])->save();
            }

            $this->audit->record('customer_payment.approved', $payment, [
                'cash_receipt_id' => $cashReceipt?->id,
            ]);

            if ($payment->intended_sales_invoice_id && $payment->intended_allocation_amount) {
                $invoice = SalesInvoice::query()->findOrFail($payment->intended_sales_invoice_id);
                $this->allocations->allocate(
                    $payment->fresh(),
                    $invoice,
                    (string) $payment->intended_allocation_amount
                );
            }

            DB::afterCommit(fn () => event(new CustomerPaymentApproved($payment->id)));

            return $payment->fresh();
        });
    }

    private function cashContext(
        array $data,
        int $currencyId,
        ?int $branchId = null,
        bool $assertCustodian = true
    ): array {
        if (empty($data['cash_box_id']) || empty($data['cash_box_session_id'])) {
            throw new BusinessRuleException('الخزينة وجلسة الخزينة مطلوبتان للدفع النقدي.');
        }

        $box = CashBox::query()
            ->where('company_id', $this->tenant->companyId())
            ->where('branch_id', $branchId ?? $this->tenant->branchId())
            ->where('currency_id', $currencyId)
            ->where('status', 'active')
            ->where('allows_receipts', true)
            ->lockForUpdate()
            ->findOrFail($data['cash_box_id']);
        if ($assertCustodian) {
            $this->custodians->assert($box, 'can_receive', (string) $data['amount']);
        }
        $session = $this->sessionGuard->assertActiveSession($box, (int) $data['cash_box_session_id']);

        if ($session->business_date->toDateString() !== (string) $data['payment_date']) {
            throw new BusinessRuleException('تاريخ الدفع يجب أن يطابق تاريخ عمل جلسة الخزينة.');
        }

        return compact('box', 'session');
    }

    private function invoiceForPayment(array $data, Customer $customer, int $currencyId): SalesInvoice
    {
        if (empty($data['sales_invoice_id']) || empty($data['allocation_amount'])) {
            throw new BusinessRuleException('الفاتورة والمبلغ المخصص مطلوبان للدفع النقدي.');
        }

        $invoice = SalesInvoice::query()
            ->where('company_id', $this->tenant->companyId())
            ->where('branch_id', $this->tenant->branchId())
            ->where('customer_id', $customer->id)
            ->where('currency_id', $currencyId)
            ->whereIn('status', ['issued', 'partially_paid', 'overdue', 'credited'])
            ->where('balance_due', '>', 0)
            ->findOrFail($data['sales_invoice_id']);

        if (bccomp((string) $data['allocation_amount'], (string) $data['amount'], 4) === 1
            || bccomp((string) $data['allocation_amount'], (string) $invoice->balance_due, 4) === 1) {
            throw new BusinessRuleException('المبلغ المخصص يتجاوز الدفعة أو المتبقي على الفاتورة.');
        }

        return $invoice;
    }

    private function createCashReceipt(
        CustomerPayment $payment,
        CashBox $box,
        CashBoxSession $session
    ): CashReceipt {
        $receipt = new CashReceipt;
        $receipt->forceFill([
            'company_id' => $payment->company_id,
            'branch_id' => $payment->branch_id,
            'cash_box_id' => $box->id,
            'cash_box_session_id' => $session->id,
            'document_number' => $this->numbers->next(
                'cash_receipt',
                $payment->company_id,
                $payment->branch_id,
                $payment->payment_date->toDateString()
            ),
            'receipt_type' => 'customer_payment',
            'status' => 'approved',
            'document_date' => $payment->payment_date,
            'currency_id' => $payment->currency_id,
            'exchange_rate' => 1,
            'amount' => $payment->amount,
            'offset_account_id' => $this->accounts->branch(
                $payment->company_id,
                $payment->branch_id,
                'customer_advance_account_id'
            ),
            'customer_id' => $payment->customer_id,
            'customer_payment_id' => $payment->id,
            'description' => 'تحصيل دفعة العميل '.$payment->payment_number,
            'reference' => $payment->reference_number ?: $payment->payment_number,
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $this->tenant->user()->id,
            'submitted_by' => $this->tenant->user()->id,
            'approved_by' => $this->tenant->user()->id,
        ])->save();

        $entry = $this->cashPosting->post($receipt);
        $receipt->forceFill([
            'status' => 'posted',
            'posted_by' => $this->tenant->user()->id,
            'journal_entry_id' => $entry->id,
        ])->save();
        $this->audit->record('treasury.cash_receipt.customer_payment_posted', $receipt, [
            'customer_payment_id' => $payment->id,
        ]);

        return $receipt;
    }
}
