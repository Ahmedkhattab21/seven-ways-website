<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\CashPayment;
use App\Models\CashReceipt;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeAdvanceSettlement;
use App\Models\EmployeeCommissionAccrual;
use App\Models\EmployeeCommissionRule;
use App\Models\EmployeeCommissionSettlement;
use App\Models\EmployeeExpenseClaim;
use App\Models\SalesCreditNote;
use App\Models\SalesInvoice;
use App\Models\Tax;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EmployeeFinanceService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AccountingPostingService $posting,
        private TreasuryApprovalLimitService $limits,
        private AuditService $audit
    ) {
    }

    public function saveRule(EmployeeCommissionRule $rule, array $data): EmployeeCommissionRule
    {
        $this->permission('commissions.manage_rules');
        $companyId = $this->tenant->companyId();
        $this->assertDimensions($data, $companyId);
        if ($rule->exists && $rule->company_id !== $companyId) {
            throw new BusinessRuleException('Commission rule is outside the current company.', status: 403);
        }
        if (in_array($data['rule_type'], ['percentage_net_sales', 'percentage_margin'], true)
            && bccomp((string) $data['rule_value'], '100', 4) === 1) {
            throw new BusinessRuleException('Percentage commission cannot exceed 100.');
        }
        if ($data['rule_type'] === 'percentage_margin'
            && empty($data['product_id']) && empty($data['service_id'])) {
            throw new BusinessRuleException('Margin commission requires a product or service scope.');
        }
        $from = Carbon::parse($data['effective_from'])->toDateString();
        $to = ! empty($data['effective_to']) ? Carbon::parse($data['effective_to'])->toDateString() : null;
        if ($to && $to < $from) {
            throw new BusinessRuleException('Commission rule effective dates are invalid.');
        }
        $scope = ['branch_id', 'employee_id', 'role_id', 'product_id', 'service_id'];
        $overlap = EmployeeCommissionRule::query()->where('company_id', $companyId)
            ->whereKeyNot($rule->getKey())->where('is_active', true)
            ->where(function (Builder $query) use ($to, $from) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from);
                if ($to) {
                    $query->whereDate('effective_from', '<=', $to);
                }
            })
            ->where(fn (Builder $query) => collect($scope)->each(
                fn (string $field) => $query->where($field, $data[$field] ?? null)
            ))->exists();
        if ($overlap) {
            throw new BusinessRuleException('An overlapping commission rule exists for the same scope.');
        }

        $rule->forceFill($data + [
            'company_id' => $companyId,
            'currency_id' => $data['currency_id'] ?? $this->tenant->company()->currency_id,
            'created_by' => $rule->created_by ?: $this->tenant->user()->id,
            'updated_by' => $this->tenant->user()->id,
        ])->save();
        $this->audit->record('employee_finance.commission_rule.saved', $rule);

        return $rule;
    }

    public function calculateInvoice(SalesInvoice $invoice, Employee $employee): array
    {
        $this->permission('commissions.calculate');
        if ($invoice->company_id !== $this->tenant->companyId()
            || $employee->company_id !== $invoice->company_id
            || ! in_array($invoice->status, ['issued', 'partially_paid', 'paid', 'overdue', 'credited'], true)) {
            throw new BusinessRuleException('Invoice is not eligible for commission calculation.');
        }
        if ($employee->branch_id !== $invoice->branch_id) {
            throw new BusinessRuleException('Commission employee is outside the invoice branch.');
        }
        $this->assertBranchAccess($invoice->branch_id);

        return DB::transaction(function () use ($invoice, $employee) {
            $invoice = SalesInvoice::query()->with('items')->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $created = [];
            foreach ($invoice->items as $item) {
                $rule = $this->resolveRule($invoice, $item->product_id, $item->service_id, $employee);
                if (! $rule) {
                    continue;
                }
                [$basis, $amount] = $this->commissionAmount($rule, $item);
                if (bccomp($amount, '0', 4) <= 0) {
                    continue;
                }
                $key = hash('sha256', implode('|', [
                    $invoice->company_id, $invoice->id, $item->id, $employee->id, $rule->id, 'accrual',
                ]));
                $accrual = EmployeeCommissionAccrual::query()
                    ->where('company_id', $invoice->company_id)
                    ->where('source_key', $key)
                    ->first();
                if (! $accrual) {
                    $accrual = new EmployeeCommissionAccrual;
                    $accrual->forceFill([
                        'company_id' => $invoice->company_id, 'source_key' => $key,
                        'branch_id' => $invoice->branch_id, 'employee_id' => $employee->id,
                        'commission_rule_id' => $rule->id, 'sales_invoice_id' => $invoice->id,
                        'sales_invoice_item_id' => $item->id, 'work_order_id' => $invoice->work_order_id,
                        'currency_id' => $invoice->currency_id, 'accrual_date' => $invoice->invoice_date,
                        'basis_amount' => $basis, 'rule_value' => $rule->rule_value,
                        'commission_amount' => $amount, 'settled_amount' => 0, 'status' => 'calculated',
                        'calculation_snapshot' => [
                            'rule_type' => $rule->rule_type, 'invoice_number' => $invoice->invoice_number,
                            'line_net' => (string) $item->net_amount, 'line_margin' => (string) ($item->margin_snapshot ?? ''),
                            'quantity' => (string) $item->quantity,
                        ],
                        'created_by' => $this->tenant->user()->id,
                    ])->save();
                }
                $created[] = $accrual;
            }
            $this->audit->record('employee_finance.commission.calculated', $invoice, [
                'employee_id' => $employee->id, 'accrual_count' => count($created),
            ]);

            return $created;
        });
    }

    public function calculateCreditNoteAdjustment(SalesCreditNote $creditNote, Employee $employee): array
    {
        $this->permission('commissions.calculate');
        if ($creditNote->company_id !== $this->tenant->companyId()
            || $employee->company_id !== $creditNote->company_id
            || $employee->branch_id !== $creditNote->branch_id
            || ! in_array($creditNote->status, ['issued', 'applied', 'refunded'], true)) {
            throw new BusinessRuleException('Credit note is not eligible for commission adjustment.');
        }
        $this->assertBranchAccess($creditNote->branch_id);

        return DB::transaction(function () use ($creditNote, $employee) {
            $creditNote = SalesCreditNote::query()->with('items')
                ->whereKey($creditNote->id)->lockForUpdate()->firstOrFail();
            $adjustments = [];
            foreach ($creditNote->items as $item) {
                if (! $item->sales_invoice_item_id) {
                    continue;
                }
                $original = EmployeeCommissionAccrual::query()
                    ->where('company_id', $creditNote->company_id)
                    ->where('employee_id', $employee->id)
                    ->where('sales_invoice_item_id', $item->sales_invoice_item_id)
                    ->whereNull('adjusts_accrual_id')
                    ->whereNotIn('status', ['reversed'])
                    ->lockForUpdate()->first();
                if (! $original || bccomp($original->basis_amount, '0', 4) <= 0) {
                    continue;
                }
                $key = hash('sha256', implode('|', [
                    $creditNote->company_id, $creditNote->id, $item->id,
                    $employee->id, $original->id, 'credit-adjustment',
                ]));
                $adjustment = EmployeeCommissionAccrual::query()
                    ->where('company_id', $creditNote->company_id)
                    ->where('source_key', $key)->first();
                if (! $adjustment) {
                    $ratio = bcdiv((string) $item->net_amount, $original->basis_amount, 8);
                    $absolute = bcmul($original->commission_amount, $ratio, 4);
                    if (bccomp($absolute, $original->commission_amount, 4) === 1) {
                        $absolute = $original->commission_amount;
                    }
                    $adjustment = new EmployeeCommissionAccrual;
                    $adjustment->forceFill([
                        'company_id' => $creditNote->company_id,
                        'branch_id' => $creditNote->branch_id,
                        'employee_id' => $employee->id,
                        'commission_rule_id' => $original->commission_rule_id,
                        'sales_invoice_id' => $original->sales_invoice_id,
                        'sales_invoice_item_id' => $original->sales_invoice_item_id,
                        'adjusts_accrual_id' => $original->id,
                        'currency_id' => $creditNote->currency_id,
                        'source_key' => $key,
                        'accrual_date' => $creditNote->credit_note_date,
                        'basis_amount' => bcmul((string) $item->net_amount, '-1', 4),
                        'rule_value' => $original->rule_value,
                        'commission_amount' => bcmul($absolute, '-1', 4),
                        'settled_amount' => '0.0000',
                        'calculation_snapshot' => [
                            'type' => 'sales_credit_note_adjustment',
                            'credit_note_id' => $creditNote->id,
                            'credit_note_item_id' => $item->id,
                            'original_accrual_id' => $original->id,
                        ],
                        'status' => 'calculated',
                        'created_by' => $this->tenant->user()->id,
                    ])->save();
                }
                $adjustments[] = $adjustment;
            }

            return $adjustments;
        });
    }

    public function accrualAction(EmployeeCommissionAccrual $accrual, string $action): EmployeeCommissionAccrual
    {
        $this->permission('commissions.'.match ($action) {
            'submit' => 'submit', 'approve' => 'approve', 'post' => 'post',
            'reverse' => 'reverse', default => throw new BusinessRuleException('Unsupported commission action.'),
        });

        return DB::transaction(function () use ($accrual, $action) {
            $accrual = EmployeeCommissionAccrual::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($accrual->id)->lockForUpdate()->firstOrFail();
            $map = [
                'submit' => ['calculated', 'pending_approval', 'submitted_by', 'submitted_at'],
                'approve' => ['pending_approval', 'approved', 'approved_by', 'approved_at'],
            ];
            if (isset($map[$action])) {
                [$from, $to, $actor, $time] = $map[$action];
                if ($accrual->status !== $from) {
                    throw new BusinessRuleException('Invalid commission accrual transition.');
                }
                if ($action === 'approve' && $accrual->created_by === $this->tenant->user()->id) {
                    throw new BusinessRuleException('Commission creator cannot approve the accrual.');
                }
                if ($action === 'approve') {
                    $this->limits->assert(
                        $this->tenant->user(), 'employee_commission', 'approve',
                        $accrual->currency_id, ltrim((string) $accrual->commission_amount, '-'),
                        $accrual->branch_id
                    );
                }
                $accrual->forceFill([
                    'status' => $to, $actor => $this->tenant->user()->id, $time => now(),
                ])->save();
            } elseif ($action === 'post') {
                if ($accrual->status !== 'approved') {
                    throw new BusinessRuleException('Only approved commission accruals can be posted.');
                }
                $entry = $this->posting->post($accrual);
                $accrual->forceFill(['status' => 'posted', 'journal_entry_id' => $entry?->id])->save();
            } else {
                if (! in_array($accrual->status, ['approved', 'posted'], true)
                    || bccomp($accrual->settled_amount, '0', 4) === 1) {
                    throw new BusinessRuleException('Commission accrual cannot be reversed.');
                }
                $reversal = $accrual->journal_entry_id
                    ? $this->posting->reverse($accrual, 'Commission accrual reversal')
                    : null;
                $accrual->forceFill([
                    'status' => 'reversed', 'reversed_by' => $this->tenant->user()->id,
                    'reversed_at' => now(), 'reversal_journal_entry_id' => $reversal?->id,
                ])->save();
            }
            $this->audit->record('employee_finance.commission.'.$action, $accrual);

            return $accrual;
        });
    }

    public function createSettlement(Employee $employee, array $lines, array $data): EmployeeCommissionSettlement
    {
        $this->permission('commissions.settle');
        if ($employee->company_id !== $this->tenant->companyId()) {
            throw new BusinessRuleException('Employee is outside the current company.', status: 403);
        }
        $this->assertBranchAccess($employee->branch_id);
        if ($lines === [] || count($lines) !== count(array_unique(array_column($lines, 'accrual_id')))) {
            throw new BusinessRuleException('Settlement lines must be present and unique.');
        }

        return DB::transaction(function () use ($employee, $lines, $data) {
            $accruals = EmployeeCommissionAccrual::query()
                ->where('company_id', $employee->company_id)->where('employee_id', $employee->id)
                ->whereIn('status', ['approved', 'posted', 'partially_settled'])
                ->whereIn('id', array_column($lines, 'accrual_id'))->lockForUpdate()->get()->keyBy('id');
            if ($accruals->count() !== count(array_unique(array_column($lines, 'accrual_id')))) {
                throw new BusinessRuleException('Settlement contains an ineligible commission accrual.');
            }
            $currencyId = (int) $accruals->first()->currency_id;
            $payableAccountIds = $accruals->map(
                fn (EmployeeCommissionAccrual $accrual) => $accrual->rule()->value('payable_account_id')
            )->unique();
            if ($payableAccountIds->count() !== 1) {
                throw new BusinessRuleException('Commission settlement must use one payable account.');
            }
            $total = '0.0000';
            $allocations = [];
            foreach ($lines as $line) {
                $accrual = $accruals->get((int) $line['accrual_id']);
                if ($accrual->currency_id !== $currencyId) {
                    throw new BusinessRuleException('Commission settlement cannot mix currencies.');
                }
                $reserved = (string) DB::table('employee_commission_settlement_lines as l')
                    ->join('employee_commission_settlements as s', 's.id', '=', 'l.commission_settlement_id')
                    ->where('l.commission_accrual_id', $accrual->id)
                    ->whereNotIn('s.status', ['reversed', 'cancelled'])
                    ->sum('l.amount');
                $remaining = bcsub(
                    bcsub($accrual->commission_amount, $accrual->settled_amount, 4),
                    $reserved,
                    4
                );
                $amount = (string) ($line['amount'] ?? $remaining);
                if (bccomp($amount, '0', 4) <= 0 || bccomp($amount, $remaining, 4) === 1) {
                    throw new BusinessRuleException('Commission settlement exceeds the remaining accrual.');
                }
                $allocations[] = [$accrual, $amount];
                $total = bcadd($total, $amount, 4);
            }
            $settlement = new EmployeeCommissionSettlement($data);
            $settlement->forceFill([
                'company_id' => $employee->company_id, 'employee_id' => $employee->id,
                'currency_id' => $currencyId, 'total_amount' => $total, 'status' => 'draft',
                'settlement_number' => $this->numbers->next(
                    'employee_commission_settlement', $employee->company_id,
                    $employee->branch_id, $data['settlement_date']
                ),
                'branch_id' => $employee->branch_id,
                'created_by' => $this->tenant->user()->id,
            ])->save();
            foreach ($allocations as [$accrual, $amount]) {
                $settlement->lines()->create(['commission_accrual_id' => $accrual->id, 'amount' => $amount]);
            }
            $this->audit->record('employee_finance.commission_settlement.created', $settlement);

            return $settlement->load('lines');
        });
    }

    public function settlementAction(EmployeeCommissionSettlement $settlement, string $action): EmployeeCommissionSettlement
    {
        $this->permission('commissions.'.($action === 'settle' ? 'settle' : $action));

        return DB::transaction(function () use ($settlement, $action) {
            $settlement = EmployeeCommissionSettlement::query()->with('lines.accrual')
                ->where('company_id', $this->tenant->companyId())->whereKey($settlement->id)
                ->lockForUpdate()->firstOrFail();
            $map = [
                'submit' => ['draft', 'pending_approval', 'submitted_by'],
                'approve' => ['pending_approval', 'approved', 'approved_by'],
            ];
            if (isset($map[$action])) {
                [$from, $to, $actor] = $map[$action];
                if ($settlement->status !== $from) {
                    throw new BusinessRuleException('Invalid commission settlement transition.');
                }
                if ($action === 'approve' && $settlement->created_by === $this->tenant->user()->id) {
                    throw new BusinessRuleException('Settlement creator cannot approve it.');
                }
                if ($action === 'approve') {
                    $this->limits->assert(
                        $this->tenant->user(), 'employee_commission', 'approve',
                        $settlement->currency_id, $settlement->total_amount, $settlement->branch_id
                    );
                }
                $settlement->forceFill(['status' => $to, $actor => $this->tenant->user()->id])->save();
            } elseif ($action === 'settle') {
                if ($settlement->status !== 'approved' || ! $settlement->cash_payment_id) {
                    throw new BusinessRuleException('Approved settlement requires a linked cash payment.');
                }
                $payment = CashPayment::query()->where('company_id', $settlement->company_id)
                    ->where('branch_id', $settlement->branch_id)
                    ->where('currency_id', $settlement->currency_id)
                    ->where('employee_id', $settlement->employee_id)->where('status', 'posted')
                    ->findOrFail($settlement->cash_payment_id);
                $payableAccountId = $settlement->lines->map(
                    fn ($line) => $line->accrual->rule()->value('payable_account_id')
                )->unique()->sole();
                if ((int) $payment->offset_account_id !== (int) $payableAccountId) {
                    throw new BusinessRuleException('Commission payment is linked to the wrong payable account.');
                }
                if (bccomp($payment->amount, $settlement->total_amount, 4) !== 0) {
                    throw new BusinessRuleException('Commission payment must equal the settlement amount.');
                }
                foreach ($settlement->lines as $line) {
                    $accrual = EmployeeCommissionAccrual::query()->whereKey($line->commission_accrual_id)
                        ->lockForUpdate()->firstOrFail();
                    $settled = bcadd($accrual->settled_amount, $line->amount, 4);
                    if (bccomp($settled, $accrual->commission_amount, 4) === 1) {
                        throw new BusinessRuleException('Commission accrual would be over-settled.');
                    }
                    $accrual->forceFill([
                        'settled_amount' => $settled,
                        'status' => bccomp($settled, $accrual->commission_amount, 4) === 0
                            ? 'settled' : 'partially_settled',
                    ])->save();
                }
                $settlement->forceFill([
                    'status' => 'settled', 'settled_by' => $this->tenant->user()->id,
                    'journal_entry_id' => $payment->journal_entry_id,
                ])->save();
            } elseif ($action === 'reverse') {
                if ($settlement->status !== 'settled' || ! $settlement->cash_payment_id) {
                    throw new BusinessRuleException('Only a settled commission payment can be reversed.');
                }
                $payment = CashPayment::query()->where('company_id', $settlement->company_id)
                    ->where('status', 'reversed')->findOrFail($settlement->cash_payment_id);
                foreach ($settlement->lines as $line) {
                    $accrual = EmployeeCommissionAccrual::query()->whereKey($line->commission_accrual_id)
                        ->lockForUpdate()->firstOrFail();
                    $settled = bcsub($accrual->settled_amount, $line->amount, 4);
                    if (bccomp($settled, '0', 4) === -1) {
                        throw new BusinessRuleException('Commission reversal would create a negative settlement.');
                    }
                    $accrual->forceFill([
                        'settled_amount' => $settled,
                        'status' => bccomp($settled, '0', 4) === 0 ? 'posted' : 'partially_settled',
                    ])->save();
                }
                $settlement->forceFill([
                    'status' => 'reversed',
                    'reversed_by' => $this->tenant->user()->id,
                    'reversal_journal_entry_id' => $payment->reversal_journal_entry_id,
                ])->save();
            } else {
                throw new BusinessRuleException('Unsupported commission settlement action.');
            }
            $this->audit->record('employee_finance.commission_settlement.'.$action, $settlement);

            return $settlement;
        });
    }

    public function createExpenseClaim(Employee $employee, array $data): EmployeeExpenseClaim
    {
        $this->permission('employee_expenses.create');
        $companyId = $this->tenant->companyId();
        if ($employee->company_id !== $companyId
            || ($employee->user_id !== $this->tenant->user()->id
                && ! $this->tenant->user()->hasPermission('employee_expenses.create_for_others'))) {
            throw new BusinessRuleException('Expense employee is outside the allowed scope.', status: 403);
        }
        $this->assertBranchAccess((int) $data['branch_id']);
        if ($employee->branch_id !== (int) $data['branch_id']) {
            throw new BusinessRuleException('Expense employee is outside the selected branch.', status: 403);
        }
        $this->assertAccount((int) $data['payable_account_id'], $companyId, true);

        return DB::transaction(function () use ($employee, $data, $companyId) {
            $subtotal = '0.0000';
            $taxTotal = '0.0000';
            $items = [];
            foreach ($data['items'] as $index => $item) {
                $account = $this->assertAccount((int) $item['expense_account_id'], $companyId);
                if ($account->is_control_account
                    && ! $this->tenant->user()->hasPermission('employee_expenses.use_control_accounts')) {
                    throw new BusinessRuleException('Control expense account requires explicit permission.');
                }
                $net = $this->positive($item['net_amount'], 'Expense amount');
                $tax = ! empty($item['tax_id'])
                    ? Tax::query()->where('company_id', $companyId)->where('is_active', true)->findOrFail($item['tax_id'])
                    : null;
                $taxAmount = $tax ? bcdiv(bcmul($net, $tax->rate, 4), '100', 4) : '0.0000';
                $items[] = array_replace($item, [
                    'tax_rate' => $tax?->rate ?? 0, 'tax_amount' => $taxAmount,
                    'total_amount' => bcadd($net, $taxAmount, 4), 'sort_order' => $index + 1,
                ]);
                $subtotal = bcadd($subtotal, $net, 4);
                $taxTotal = bcadd($taxTotal, $taxAmount, 4);
            }
            $claim = new EmployeeExpenseClaim($data);
            $claim->forceFill([
                'company_id' => $companyId, 'employee_id' => $employee->id,
                'currency_id' => $data['currency_id'] ?? $this->tenant->company()->currency_id,
                'claim_number' => $this->numbers->next(
                    'employee_expense_claim', $companyId, $data['branch_id'], $data['claim_date']
                ),
                'subtotal' => $subtotal, 'tax_amount' => $taxTotal,
                'total_amount' => bcadd($subtotal, $taxTotal, 4),
                'status' => 'draft', 'created_by' => $this->tenant->user()->id,
            ])->save();
            foreach ($items as $item) {
                $claimItem = new \App\Models\EmployeeExpenseClaimItem;
                $claimItem->forceFill($item);
                $claim->items()->save($claimItem);
            }
            $this->audit->record('employee_finance.expense.created', $claim);

            return $claim->load('items');
        });
    }

    public function expenseAction(EmployeeExpenseClaim $claim, string $action, ?string $reason = null): EmployeeExpenseClaim
    {
        $this->permission('employee_expenses.'.$action);

        return DB::transaction(function () use ($claim, $action, $reason) {
            $claim = EmployeeExpenseClaim::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            $map = [
                'submit' => ['draft', 'pending_approval', 'submitted_by'],
                'approve' => ['pending_approval', 'approved', 'approved_by'],
                'reject' => ['pending_approval', 'rejected', 'approved_by'],
            ];
            if (isset($map[$action])) {
                [$from, $to, $actor] = $map[$action];
                if ($claim->status !== $from) {
                    throw new BusinessRuleException('Invalid expense claim transition.');
                }
                if ($action === 'approve' && $claim->created_by === $this->tenant->user()->id) {
                    throw new BusinessRuleException('Expense claimant cannot approve their own claim.');
                }
                if ($action === 'approve') {
                    $this->limits->assert(
                        $this->tenant->user(), 'employee_expense', 'approve',
                        $claim->currency_id, $claim->total_amount, $claim->branch_id
                    );
                }
                $claim->forceFill([
                    'status' => $to, $actor => $this->tenant->user()->id,
                    'rejection_reason' => $action === 'reject' ? $reason : null,
                ])->save();
            } elseif ($action === 'post') {
                if ($claim->status !== 'approved') {
                    throw new BusinessRuleException('Only approved expense claims can be posted.');
                }
                $this->limits->assert(
                    $this->tenant->user(), 'employee_expense', 'post',
                    $claim->currency_id, $claim->total_amount, $claim->branch_id
                );
                $entry = $this->posting->post($claim);
                $claim->forceFill([
                    'status' => 'posted', 'posted_by' => $this->tenant->user()->id,
                    'journal_entry_id' => $entry?->id,
                ])->save();
            } elseif ($action === 'pay') {
                if ($claim->status !== 'posted' || ! $claim->cash_payment_id) {
                    throw new BusinessRuleException('Posted expense claim requires a linked cash payment.');
                }
                $payment = CashPayment::query()->where('company_id', $claim->company_id)
                    ->where('branch_id', $claim->branch_id)
                    ->where('currency_id', $claim->currency_id)
                    ->where('employee_id', $claim->employee_id)->where('status', 'posted')
                    ->findOrFail($claim->cash_payment_id);
                if ((int) $payment->offset_account_id !== (int) $claim->payable_account_id) {
                    throw new BusinessRuleException('Expense payment is linked to the wrong payable account.');
                }
                if (bccomp($payment->amount, $claim->total_amount, 4) !== 0) {
                    throw new BusinessRuleException('Expense payment must equal the claim total.');
                }
                $claim->forceFill(['status' => 'paid', 'paid_by' => $this->tenant->user()->id])->save();
            } elseif ($action === 'reverse') {
                if ($claim->status !== 'posted' || ! $claim->journal_entry_id) {
                    throw new BusinessRuleException('Only an unpaid posted claim can be reversed.');
                }
                $entry = $this->posting->reverse($claim, (string) $reason);
                $claim->forceFill([
                    'status' => 'reversed', 'reversed_by' => $this->tenant->user()->id,
                    'reversal_journal_entry_id' => $entry->id,
                ])->save();
            } else {
                throw new BusinessRuleException('Unsupported expense claim action.');
            }
            $this->audit->record('employee_finance.expense.'.$action, $claim, ['reason' => $reason]);

            return $claim;
        });
    }

    public function createAdvance(Employee $employee, array $data): EmployeeAdvance
    {
        $this->permission('employee_advances.create');
        $companyId = $this->tenant->companyId();
        if ($employee->company_id !== $companyId) {
            throw new BusinessRuleException('Employee advance is outside the current company.', status: 403);
        }
        $this->assertBranchAccess((int) $data['branch_id']);
        if ($employee->branch_id !== (int) $data['branch_id']) {
            throw new BusinessRuleException('Advance employee is outside the selected branch.', status: 403);
        }
        $this->assertAccount((int) $data['receivable_account_id'], $companyId, true);
        $amount = $this->positive($data['amount'], 'Advance amount');

        return DB::transaction(function () use ($employee, $data, $companyId, $amount) {
            $advance = new EmployeeAdvance($data);
            $advance->forceFill([
                'company_id' => $companyId, 'employee_id' => $employee->id,
                'currency_id' => $data['currency_id'] ?? $this->tenant->company()->currency_id,
                'advance_number' => $this->numbers->next(
                    'employee_advance', $companyId, $data['branch_id'], $data['request_date']
                ),
                'amount' => $amount, 'settled_amount' => 0, 'status' => 'draft',
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('employee_finance.advance.created', $advance);

            return $advance;
        });
    }

    public function advanceAction(EmployeeAdvance $advance, string $action): EmployeeAdvance
    {
        $this->permission('employee_advances.'.$action);

        return DB::transaction(function () use ($advance, $action) {
            $advance = EmployeeAdvance::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($advance->id)->lockForUpdate()->firstOrFail();
            $map = [
                'submit' => ['draft', 'submitted', 'submitted_by'],
                'approve' => ['submitted', 'approved', 'approved_by'],
            ];
            if (isset($map[$action])) {
                [$from, $to, $actor] = $map[$action];
                if ($advance->status !== $from) {
                    throw new BusinessRuleException('Invalid employee advance transition.');
                }
                if ($action === 'approve' && $advance->created_by === $this->tenant->user()->id) {
                    throw new BusinessRuleException('Advance creator cannot approve it.');
                }
                if ($action === 'approve') {
                    $this->limits->assert(
                        $this->tenant->user(), 'employee_advance', 'approve',
                        $advance->currency_id, $advance->amount, $advance->branch_id
                    );
                }
                $advance->forceFill(['status' => $to, $actor => $this->tenant->user()->id])->save();
            } elseif ($action === 'disburse') {
                if ($advance->status !== 'approved' || ! $advance->cash_payment_id) {
                    throw new BusinessRuleException('Approved advance requires a linked cash payment.');
                }
                $payment = CashPayment::query()->where('company_id', $advance->company_id)
                    ->where('branch_id', $advance->branch_id)
                    ->where('currency_id', $advance->currency_id)
                    ->where('employee_id', $advance->employee_id)->where('status', 'posted')
                    ->findOrFail($advance->cash_payment_id);
                if ((int) $payment->offset_account_id !== (int) $advance->receivable_account_id) {
                    throw new BusinessRuleException('Advance payment is linked to the wrong receivable account.');
                }
                if (bccomp($payment->amount, $advance->amount, 4) !== 0) {
                    throw new BusinessRuleException('Advance payment must equal the approved amount.');
                }
                $advance->forceFill([
                    'status' => 'disbursed', 'disbursed_by' => $this->tenant->user()->id,
                    'journal_entry_id' => $payment->journal_entry_id,
                ])->save();
            } elseif ($action === 'close') {
                if (bccomp($advance->settled_amount, $advance->amount, 4) !== 0) {
                    throw new BusinessRuleException('Advance cannot close before full settlement.');
                }
                $advance->forceFill(['status' => 'closed', 'closed_by' => $this->tenant->user()->id])->save();
            } elseif ($action === 'reverse') {
                if ($advance->status !== 'disbursed'
                    || bccomp($advance->settled_amount, '0', 4) !== 0
                    || ! $advance->cash_payment_id) {
                    throw new BusinessRuleException('Only an unsettled disbursed advance can be reversed.');
                }
                $payment = CashPayment::query()->where('company_id', $advance->company_id)
                    ->where('status', 'reversed')->findOrFail($advance->cash_payment_id);
                $advance->forceFill([
                    'status' => 'reversed',
                    'reversed_by' => $this->tenant->user()->id,
                    'reversal_journal_entry_id' => $payment->reversal_journal_entry_id,
                ])->save();
            } else {
                throw new BusinessRuleException('Unsupported employee advance action.');
            }
            $this->audit->record('employee_finance.advance.'.$action, $advance);

            return $advance;
        });
    }

    public function settleAdvance(EmployeeAdvance $advance, array $data): EmployeeAdvanceSettlement
    {
        $this->permission('employee_advances.settle');

        return DB::transaction(function () use ($advance, $data) {
            $advance = EmployeeAdvance::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($advance->id)->lockForUpdate()->firstOrFail();
            if (! in_array($advance->status, ['disbursed', 'partially_settled'], true)) {
                throw new BusinessRuleException('Advance is not eligible for settlement.');
            }
            $amount = $this->positive($data['amount'], 'Settlement amount');
            $remaining = bcsub($advance->amount, $advance->settled_amount, 4);
            if (bccomp($amount, $remaining, 4) === 1) {
                throw new BusinessRuleException('Advance settlement exceeds the outstanding amount.');
            }
            if ($data['settlement_type'] === 'expense_claim') {
                $claim = EmployeeExpenseClaim::query()->where('company_id', $advance->company_id)
                    ->where('branch_id', $advance->branch_id)
                    ->where('currency_id', $advance->currency_id)
                    ->where('employee_id', $advance->employee_id)->where('status', 'posted')
                    ->findOrFail($data['expense_claim_id'] ?? null);
                if ((int) $claim->payable_account_id !== (int) $advance->receivable_account_id) {
                    throw new BusinessRuleException('Expense settlement must credit the advance receivable account.');
                }
                if (bccomp($amount, $claim->total_amount, 4) === 1) {
                    throw new BusinessRuleException('Settlement exceeds the posted expense claim.');
                }
            } elseif ($data['settlement_type'] === 'cash_return') {
                $receipt = CashReceipt::query()->where('company_id', $advance->company_id)
                    ->where('branch_id', $advance->branch_id)
                    ->where('currency_id', $advance->currency_id)
                    ->where('employee_id', $advance->employee_id)->where('status', 'posted')
                    ->findOrFail($data['cash_receipt_id'] ?? null);
                if ((int) $receipt->offset_account_id !== (int) $advance->receivable_account_id) {
                    throw new BusinessRuleException('Cash return must credit the advance receivable account.');
                }
                if (bccomp($amount, $receipt->amount, 4) === 1) {
                    throw new BusinessRuleException('Settlement exceeds the posted cash return.');
                }
            } else {
                throw new BusinessRuleException('Unsupported advance settlement type.');
            }
            $settlement = new EmployeeAdvanceSettlement;
            $settlement->forceFill([
                'company_id' => $advance->company_id, 'employee_advance_id' => $advance->id,
                'expense_claim_id' => $data['expense_claim_id'] ?? null,
                'cash_receipt_id' => $data['cash_receipt_id'] ?? null,
                'journal_entry_id' => $data['journal_entry_id'] ?? null,
                'settlement_type' => $data['settlement_type'],
                'settlement_date' => $data['settlement_date'], 'amount' => $amount,
                'status' => 'posted', 'created_by' => $this->tenant->user()->id,
            ])->save();
            $settled = bcadd($advance->settled_amount, $amount, 4);
            $advance->forceFill([
                'settled_amount' => $settled,
                'status' => bccomp($settled, $advance->amount, 4) === 0 ? 'settled' : 'partially_settled',
            ])->save();
            $this->audit->record('employee_finance.advance.settled', $settlement);

            return $settlement;
        });
    }

    private function resolveRule(SalesInvoice $invoice, ?int $productId, ?int $serviceId, Employee $employee): ?EmployeeCommissionRule
    {
        $roleIds = $employee->user?->roles()->pluck('roles.id') ?? collect();

        return EmployeeCommissionRule::query()->where('company_id', $invoice->company_id)
            ->where('currency_id', $invoice->currency_id)->where('is_active', true)
            ->whereDate('effective_from', '<=', $invoice->invoice_date)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $invoice->invoice_date))
            ->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhere('branch_id', $invoice->branch_id))
            ->where(fn (Builder $q) => $q->whereNull('employee_id')->orWhere('employee_id', $employee->id))
            ->where(fn (Builder $q) => $q->whereNull('product_id')->orWhere('product_id', $productId))
            ->where(fn (Builder $q) => $q->whereNull('service_id')->orWhere('service_id', $serviceId))
            ->where(fn (Builder $q) => $q->whereNull('role_id')->orWhereIn('role_id', $roleIds))
            ->orderByRaw(
                'CASE WHEN employee_id IS NULL THEN 0 ELSE 16 END + '.
                'CASE WHEN product_id IS NULL AND service_id IS NULL THEN 0 ELSE 8 END + '.
                'CASE WHEN branch_id IS NULL THEN 0 ELSE 4 END + '.
                'CASE WHEN role_id IS NULL THEN 0 ELSE 2 END DESC'
            )->orderByDesc('priority')->orderByDesc('id')->first();
    }

    private function commissionAmount(EmployeeCommissionRule $rule, object $item): array
    {
        $basis = match ($rule->rule_type) {
            'percentage_net_sales' => (string) $item->net_amount,
            'percentage_margin' => filled($item->margin_snapshot)
                ? (bccomp((string) $item->margin_snapshot, '0', 4) === 1
                    ? (string) $item->margin_snapshot : '0.0000')
                : throw new BusinessRuleException('Margin commission requires an authoritative margin snapshot.'),
            'fixed_product', 'fixed_service' => (string) $item->quantity,
            'fixed' => '1.0000',
            default => throw new BusinessRuleException('Unsupported commission rule type.'),
        };
        $amount = str_starts_with($rule->rule_type, 'percentage_')
            ? bcdiv(bcmul($basis, $rule->rule_value, 4), '100', 4)
            : bcmul($basis, $rule->rule_value, 4);
        if ($rule->minimum_amount !== null && bccomp($amount, $rule->minimum_amount, 4) === -1) {
            $amount = $rule->minimum_amount;
        }
        if ($rule->maximum_amount !== null && bccomp($amount, $rule->maximum_amount, 4) === 1) {
            $amount = $rule->maximum_amount;
        }

        return [$basis, $amount];
    }

    private function assertDimensions(array $data, int $companyId): void
    {
        foreach ([
            'branch_id' => \App\Models\Branch::class, 'employee_id' => Employee::class,
            'role_id' => \App\Models\Role::class, 'product_id' => \App\Models\Product::class,
            'service_id' => \App\Models\Service::class,
        ] as $field => $model) {
            if (! empty($data[$field])
                && ! $model::query()->where('company_id', $companyId)->whereKey($data[$field])->exists()) {
                throw new BusinessRuleException("{$field} is outside the current company.", status: 403);
            }
        }
        foreach (['expense_account_id', 'payable_account_id'] as $field) {
            $this->assertAccount((int) $data[$field], $companyId, true);
        }
    }

    private function assertAccount(int $id, int $companyId, bool $allowControl = false): Account
    {
        $account = Account::query()->where('company_id', $companyId)
            ->where('is_active', true)->where('is_posting', true)->findOrFail($id);
        if (! $allowControl && $account->is_control_account) {
            throw new BusinessRuleException('A posting account is required.');
        }

        return $account;
    }

    private function positive(mixed $amount, string $label): string
    {
        if (! is_numeric($amount)) {
            throw new BusinessRuleException("{$label} must be numeric.");
        }
        $amount = bcadd((string) $amount, '0', 4);
        if (bccomp($amount, '0', 4) <= 0) {
            throw new BusinessRuleException("{$label} must be greater than zero.");
        }

        return $amount;
    }

    private function permission(string $permission): void
    {
        if (! $this->tenant->user()->hasPermission($permission)) {
            throw new BusinessRuleException('This employee finance action is not authorized.', status: 403);
        }
    }

    private function assertBranchAccess(int $branchId): void
    {
        if (! $this->tenant->accessibleBranches()->contains('id', $branchId)) {
            throw new BusinessRuleException('This branch is outside the allowed scope.', status: 403);
        }
    }
}
