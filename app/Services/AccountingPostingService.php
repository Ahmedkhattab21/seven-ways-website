<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\AccountingSourcePosted;
use App\Events\AccountingSourceReversed;
use App\Events\JournalEntryCreated;
use App\Events\JournalEntryPosted;
use App\Events\OpeningBalancePosted;
use App\Events\OpeningBalanceReversed;
use App\Models\AccountingPostingLink;
use App\Models\AccountingSetting;
use App\Models\BankAdjustment;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\GoodsReceipt;
use App\Models\JournalEntry;
use App\Models\OpeningBalanceDocument;
use App\Models\PurchaseReturn;
use App\Models\SalesCreditNote;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AccountingPostingService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AccountingPeriodResolver $periods,
        private PostingProfileResolver $profiles,
        private PostingAccountResolver $accounts,
        private PostingAmountResolver $amounts,
        private JournalEntryValidationService $validator,
        private JournalEntryService $journals,
        private AuditService $audit
    ) {
    }

    public function preview(Model $source, array $options = []): array
    {
        $this->assertScopeAndEligibility($source);
        [$date, $module] = $this->dateAndModule($source);
        $period = $this->periods->resolve($source->company_id, $date, $module, $this->tenant->user(), $options['override_reason'] ?? null);
        $profile = $this->profiles->resolve($source->company_id, $this->sourceType($source), $date, $options['posting_profile_id'] ?? null);
        $lines = $this->buildLines($source);

        return [
            'source_type' => $this->sourceType($source), 'source_id' => $source->getKey(),
            'posting_date' => $date, 'accounting_period_id' => $period->id,
            'posting_profile_id' => $profile?->id, 'lines' => $lines,
            'total_debit' => $this->sum($lines, 'debit_amount'),
            'total_credit' => $this->sum($lines, 'credit_amount'),
            'not_required' => $lines === [],
        ];
    }

    public function post(Model $source, array $options = []): ?JournalEntry
    {
        return DB::transaction(function () use ($source, $options) {
            $source = $source->newQuery()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
            $sourceType = $this->sourceType($source);
            if (($source->company_id ?? null) !== $this->tenant->companyId()) {
                throw new BusinessRuleException('Accounting source is outside the current company.');
            }
            $existing = AccountingPostingLink::query()->where('company_id', $source->company_id)
                ->where('source_type', $sourceType)->where('source_id', $source->getKey())
                ->where('posting_action', 'post')->lockForUpdate()->first();
            if ($existing) {
                return $existing->journalEntry;
            }
            $this->assertScopeAndEligibility($source);
            [$date, $module] = $this->dateAndModule($source);
            $period = $this->periods->resolve($source->company_id, $date, $module, $this->tenant->user(), $options['override_reason'] ?? null);
            $profile = $this->profiles->resolve($source->company_id, $sourceType, $date, $options['posting_profile_id'] ?? null);
            $lines = $this->buildLines($source);
            if ($lines === []) {
                $this->createLink($source, null, 'not_required');

                return null;
            }
            $settings = AccountingSetting::query()->where('company_id', $source->company_id)->firstOrFail();
            $entry = new JournalEntry();
            $entry->forceFill([
                'company_id' => $source->company_id, 'branch_id' => $this->branchId($source),
                'fiscal_year_id' => $period->fiscal_year_id, 'accounting_period_id' => $period->id,
                'journal_number' => $this->numbers->next('journal_entry', $source->company_id, $this->branchId($source), $date),
                'entry_type' => 'automatic', 'source_type' => $sourceType, 'source_id' => $source->getKey(),
                'source_uuid' => $source->uuid ?? null, 'source_number' => $this->sourceNumber($source),
                'posting_profile_id' => $profile?->id, 'status' => 'posted', 'entry_date' => $date,
                'posting_date' => $date, 'currency_id' => $source->currency_id ?? $settings->base_currency_id,
                'exchange_rate' => '1.00000000', 'description' => $this->description($source),
                'is_automatic' => true, 'created_by' => $this->tenant->user()->id,
                'posted_by' => $this->tenant->user()->id, 'posted_at' => now(),
            ])->save();
            $this->storeLines($entry, $lines);
            $this->validator->assertPostable($entry, true);
            $link = $this->createLink($source, $entry, 'posted');
            $this->audit->record('accounting.source_posted', $entry, [
                'source_type' => $sourceType, 'source_id' => $source->getKey(), 'posting_link_id' => $link->id,
            ]);
            DB::afterCommit(function () use ($entry, $source) {
                event(new JournalEntryCreated($entry->id));
                event(new JournalEntryPosted($entry->id));
                event(new AccountingSourcePosted($source->getKey()));
                if ($source instanceof OpeningBalanceDocument) {
                    event(new OpeningBalancePosted($source->getKey()));
                }
            });

            return $entry->load('lines');
        });
    }

    public function reverse(Model $source, string $reason, ?string $date = null): JournalEntry
    {
        return DB::transaction(function () use ($source, $reason, $date) {
            $source = $source->newQuery()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
            $link = AccountingPostingLink::query()->where('company_id', $source->company_id)
                ->where('source_type', $this->sourceType($source))->where('source_id', $source->getKey())
                ->where('posting_action', 'post')->lockForUpdate()->firstOrFail();
            if ($link->status !== 'posted' || ! $link->journal_entry_id || $link->reversal_journal_entry_id) {
                throw new BusinessRuleException('The accounting source is not eligible for reversal.');
            }
            $reversal = $this->journals->reverse($link->journalEntry, $reason, $date);
            $link->forceFill(['status' => 'reversed', 'reversal_journal_entry_id' => $reversal->id])->save();
            if ($source instanceof OpeningBalanceDocument) {
                $source->forceFill(['status' => 'reversed', 'reversed_at' => now()])->save();
            }
            DB::afterCommit(function () use ($source) {
                event(new AccountingSourceReversed($source->getKey()));
                if ($source instanceof OpeningBalanceDocument) {
                    event(new OpeningBalanceReversed($source->getKey()));
                }
            });

            return $reversal;
        });
    }

    private function buildLines(Model $source): array
    {
        if ($source instanceof BankAdjustment) {
            $bank = $source->bankAccount()->firstOrFail();
            $increase = in_array($source->adjustment_type, ['interest_income', 'unidentified_receipt'], true);
            if (in_array($source->adjustment_type, ['rounding', 'other'], true)) {
                if (! $source->statementLine) {
                    throw new BusinessRuleException('Rounding and other bank adjustments require a statement line direction.');
                }
                $increase = $source->statementLine->direction() === 'credit';
            }

            return $increase
                ? [$this->debit($bank->gl_account_id, $source->amount), $this->credit($source->offset_account_id, $source->amount)]
                : [$this->debit($source->offset_account_id, $source->amount), $this->credit($bank->gl_account_id, $source->amount)];
        }
        if ($source instanceof OpeningBalanceDocument) {
            return $source->lines()->get()->map(fn ($line) => [
                ...$line->only(['account_id', 'branch_id', 'cost_center_id', 'currency_id', 'customer_id',
                    'supplier_id', 'employee_id', 'vehicle_id', 'exchange_rate', 'debit_amount',
                    'credit_amount', 'description']),
            ])->all();
        }
        $company = $source->company_id;
        $branch = $this->branchId($source);
        if ($source instanceof SalesInvoice) {
            $net = bcsub((string) $source->total, (string) $source->tax_amount, 4);

            return [
                $this->debit($this->accounts->branch($company, $branch, 'accounts_receivable_account_id'), $source->total, ['customer_id' => $source->customer_id]),
                $this->credit($this->accounts->branch($company, $branch, 'service_revenue_account_id'), $net),
                ...$this->optionalCredit($this->accounts->branch($company, $branch, 'vat_output_account_id'), $source->tax_amount, ['tax_component' => 'output']),
            ];
        }
        if ($source instanceof SalesCreditNote) {
            return [
                $this->debit($this->accounts->branch($company, $branch, 'sales_return_account_id'), $source->subtotal),
                ...$this->optionalDebit($this->accounts->branch($company, $branch, 'vat_output_account_id'), $source->tax_amount, ['tax_component' => 'output']),
                $this->credit($this->accounts->branch($company, $branch, 'accounts_receivable_account_id'), $source->total, ['customer_id' => $source->customer_id]),
            ];
        }
        if ($source instanceof CustomerPayment) {
            return [
                $this->debit($this->accounts->paymentMethod($company, $branch, $source->payment_method_id, 'receipt', $source->currency_id, (string) $source->amount), $source->amount),
                $this->credit($this->accounts->branch($company, $branch, 'customer_advance_account_id'), $source->amount, ['customer_id' => $source->customer_id]),
            ];
        }
        if ($source instanceof CustomerRefund) {
            return [
                $this->debit($this->accounts->branch($company, $branch, 'customer_advance_account_id'), $source->amount, ['customer_id' => $source->customer_id]),
                $this->credit($this->accounts->paymentMethod($company, $branch, $source->payment_method_id, 'refund', $source->currency_id, (string) $source->amount), $source->amount),
            ];
        }
        if ($source instanceof SupplierInvoice) {
            $net = bcsub((string) $source->total, (string) $source->tax_amount, 4);

            return [
                $this->debit($this->accounts->branch($company, $branch, 'purchase_account_id'), $net),
                ...$this->optionalDebit($this->accounts->branch($company, $branch, 'vat_input_account_id'), $source->tax_amount, ['tax_component' => 'input']),
                $this->credit($this->accounts->branch($company, $branch, 'accounts_payable_account_id'), $source->total, ['supplier_id' => $source->supplier_id]),
            ];
        }
        if ($source instanceof SupplierCreditNote) {
            return [
                $this->debit($this->accounts->branch($company, $branch, 'accounts_payable_account_id'), $source->total, ['supplier_id' => $source->supplier_id]),
                $this->credit($this->accounts->branch($company, $branch, 'purchase_return_account_id'), $source->subtotal),
                ...$this->optionalCredit($this->accounts->branch($company, $branch, 'vat_input_account_id'), $source->tax_amount, ['tax_component' => 'input']),
            ];
        }
        if ($source instanceof SupplierPayment) {
            return [
                $this->debit($this->accounts->branch($company, $branch, 'supplier_advance_account_id'), $source->amount, ['supplier_id' => $source->supplier_id]),
                $this->credit($this->accounts->paymentMethod($company, $branch, $source->payment_method_id, 'payment', $source->currency_id, (string) $source->amount), $source->amount),
            ];
        }
        if ($source instanceof GoodsReceipt || $source instanceof PurchaseReturn) {
            $ids = $source->items()->pluck('id');
            $referenceType = $source instanceof GoodsReceipt ? 'goods_receipt_item' : 'purchase_return_item';
            $cost = StockMovement::query()->where('company_id', $company)->where('reference_type', $referenceType)
                ->whereIn('reference_id', $ids)->sum('total_cost');
            if (bccomp((string) $cost, '0', 4) <= 0) {
                throw new BusinessRuleException('No authoritative stock movement cost was found.');
            }
            $inventory = $this->accounts->branch($company, $branch, 'inventory_account_id');
            $clearing = $this->accounts->branch($company, $branch, $source instanceof GoodsReceipt ? 'purchase_account_id' : 'purchase_return_account_id');

            return $source instanceof GoodsReceipt
                ? [$this->debit($inventory, $cost), $this->credit($clearing, $cost)]
                : [$this->debit($clearing, $cost), $this->credit($inventory, $cost)];
        }
        if ($source instanceof StockTransfer) {
            $from = $this->accounts->branch($company, $source->from_branch_id, 'inventory_account_id');
            $to = $this->accounts->branch($company, $source->to_branch_id, 'inventory_account_id');

            return $from === $to ? [] : throw new BusinessRuleException('Inter-account stock transfer posting is not configured.');
        }
        if ($source instanceof StockMovement) {
            $amount = ltrim((string) $source->total_cost, '-');
            $inventory = $this->accounts->product($company, $source->product_id, 'inventory_account_id', $branch, 'inventory_account_id');
            $isIn = $source->direction === 'in' || str_contains($source->movement_type, 'return') || str_contains($source->movement_type, 'gain');
            $counterpart = str_contains($source->movement_type, 'adjustment')
                ? $this->accounts->product($company, $source->product_id, 'adjustment_account_id', $branch, 'inventory_adjustment_account_id')
                : $this->accounts->product($company, $source->product_id, 'cogs_account_id', $branch, 'cost_of_goods_sold_account_id');

            return $isIn
                ? [$this->debit($inventory, $amount, ['product_id' => $source->product_id, 'warehouse_id' => $source->warehouse_id]), $this->credit($counterpart, $amount)]
                : [$this->debit($counterpart, $amount), $this->credit($inventory, $amount, ['product_id' => $source->product_id, 'warehouse_id' => $source->warehouse_id])];
        }
        throw new BusinessRuleException('No accounting posting builder exists for this source.');
    }

    private function storeLines(JournalEntry $entry, array $lines): void
    {
        $debit = '0.0000';
        $credit = '0.0000';
        foreach (array_values($lines) as $index => $line) {
            $rate = (string) ($line['exchange_rate'] ?? '1');
            $lineDebit = $this->amounts->amount($line, 'debit_amount');
            $lineCredit = $this->amounts->amount($line, 'credit_amount');
            $entry->lines()->create($line + [
                'line_number' => $index + 1, 'currency_id' => $line['currency_id'] ?? $entry->currency_id,
                'exchange_rate' => $rate, 'base_debit_amount' => $this->amounts->base($lineDebit, $rate),
                'base_credit_amount' => $this->amounts->base($lineCredit, $rate),
            ]);
            $debit = bcadd($debit, $this->amounts->base($lineDebit, $rate), 4);
            $credit = bcadd($credit, $this->amounts->base($lineCredit, $rate), 4);
        }
        $entry->forceFill([
            'total_debit' => $debit, 'total_credit' => $credit,
            'base_total_debit' => $debit, 'base_total_credit' => $credit,
        ])->save();
    }

    private function assertScopeAndEligibility(Model $source): void
    {
        if (($source->company_id ?? null) !== $this->tenant->companyId()) {
            throw new BusinessRuleException('Accounting source is outside the current company.');
        }
        $eligible = match (true) {
            $source instanceof SalesInvoice => in_array($source->status, ['issued', 'partially_paid', 'paid', 'overdue', 'credited'], true),
            $source instanceof SalesCreditNote => $source->status === 'issued',
            $source instanceof CustomerPayment => in_array($source->status, ['approved', 'partially_allocated', 'allocated'], true),
            $source instanceof CustomerRefund => $source->status === 'processed',
            $source instanceof SupplierInvoice => in_array($source->status, ['posted', 'partially_paid', 'paid', 'credited'], true),
            $source instanceof SupplierCreditNote => $source->status === 'posted',
            $source instanceof SupplierPayment => in_array($source->status, ['processed', 'partially_allocated', 'allocated'], true),
            $source instanceof GoodsReceipt, $source instanceof PurchaseReturn => $source->status === 'posted',
            $source instanceof StockTransfer => in_array($source->status, ['received', 'received_with_discrepancy'], true),
            $source instanceof OpeningBalanceDocument => $source->status === 'ready_for_posting',
            $source instanceof StockMovement => true,
            $source instanceof BankAdjustment => $source->status === 'approved',
            default => false,
        };
        if (! $eligible) {
            throw new BusinessRuleException('Accounting source status is not eligible for posting.');
        }
    }

    private function createLink(Model $source, ?JournalEntry $entry, string $status): AccountingPostingLink
    {
        $link = new AccountingPostingLink();
        $link->forceFill([
            'company_id' => $source->company_id, 'branch_id' => $this->branchId($source),
            'source_type' => $this->sourceType($source), 'source_id' => $source->getKey(),
            'source_uuid' => $source->uuid ?? null, 'posting_action' => 'post',
            'journal_entry_id' => $entry?->id,
            'idempotency_key' => hash('sha256', implode('|', [$source->company_id, $this->sourceType($source), $source->getKey(), 'post'])),
            'status' => $status, 'created_by' => $this->tenant->user()->id,
        ])->save();
        if ($source instanceof OpeningBalanceDocument && $entry) {
            $source->forceFill(['status' => 'posted', 'journal_entry_id' => $entry->id, 'posted_at' => now()])->save();
        }

        return $link;
    }

    private function debit(int $accountId, mixed $amount, array $dimensions = []): array
    {
        return $dimensions + ['account_id' => $accountId, 'debit_amount' => (string) $amount, 'credit_amount' => '0'];
    }

    private function credit(int $accountId, mixed $amount, array $dimensions = []): array
    {
        return $dimensions + ['account_id' => $accountId, 'debit_amount' => '0', 'credit_amount' => (string) $amount];
    }

    private function optionalDebit(int $accountId, mixed $amount, array $dimensions = []): array
    {
        return bccomp((string) $amount, '0', 4) > 0 ? [$this->debit($accountId, $amount, $dimensions)] : [];
    }

    private function optionalCredit(int $accountId, mixed $amount, array $dimensions = []): array
    {
        return bccomp((string) $amount, '0', 4) > 0 ? [$this->credit($accountId, $amount, $dimensions)] : [];
    }

    private function sum(array $lines, string $field): string
    {
        return array_reduce($lines, fn ($total, $line) => bcadd($total, (string) ($line[$field] ?? 0), 4), '0.0000');
    }

    private function sourceType(Model $source): string
    {
        return $source->getMorphClass();
    }

    private function branchId(Model $source): ?int
    {
        return $source->branch_id ?? $source->from_branch_id ?? null;
    }

    private function dateAndModule(Model $source): array
    {
        return match (true) {
            $source instanceof SalesInvoice => [$source->invoice_date->toDateString(), 'sales'],
            $source instanceof SalesCreditNote => [$source->credit_note_date->toDateString(), 'sales'],
            $source instanceof CustomerPayment => [$source->payment_date->toDateString(), 'payments'],
            $source instanceof CustomerRefund => [$source->refund_date->toDateString(), 'payments'],
            $source instanceof SupplierInvoice => [$source->invoice_date->toDateString(), 'purchases'],
            $source instanceof SupplierCreditNote => [$source->credit_date->toDateString(), 'purchases'],
            $source instanceof SupplierPayment => [$source->payment_date->toDateString(), 'payments'],
            $source instanceof GoodsReceipt => [$source->receipt_date->toDateString(), 'inventory'],
            $source instanceof PurchaseReturn => [$source->return_date->toDateString(), 'inventory'],
            $source instanceof OpeningBalanceDocument => [$source->balance_date->toDateString(), 'opening_balances'],
            $source instanceof StockMovement => [$source->occurred_at->toDateString(), 'inventory'],
            $source instanceof StockTransfer => [($source->received_at ?? now())->toDateString(), 'inventory'],
            $source instanceof BankAdjustment => [$source->adjustment_date->toDateString(), 'treasury'],
            default => [now()->toDateString(), 'inventory'],
        };
    }

    private function sourceNumber(Model $source): ?string
    {
        foreach (['invoice_number', 'credit_note_number', 'payment_number', 'refund_number', 'internal_invoice_number',
            'goods_receipt_number', 'purchase_return_number', 'movement_number', 'transfer_number', 'document_number'] as $field) {
            if ($source->{$field} ?? null) {
                return $source->{$field};
            }
        }

        return null;
    }

    private function description(Model $source): string
    {
        return 'Automatic posting for '.class_basename($source).' '.($this->sourceNumber($source) ?? $source->getKey());
    }
}
