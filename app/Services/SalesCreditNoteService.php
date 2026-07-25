<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\CreditNoteIssued;
use App\Models\SalesCreditNote;
use App\Models\SalesCreditNoteItem;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;

class SalesCreditNoteService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private MoneyRoundingService $rounding,
        private AuditService $audit,
        private SalesInvoiceBalanceService $balances
    ) {
    }

    public function create(SalesInvoice $invoice, array $data, array $items): SalesCreditNote
    {
        return DB::transaction(function () use ($invoice, $data, $items) {
            $invoice = SalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_unless($invoice->company_id === $this->tenant->companyId() && $this->tenant->user()->canAccessBranch($invoice->branch), 403);
            if (! in_array($invoice->status, ['issued', 'partially_paid', 'paid', 'overdue'], true)) {
                throw new BusinessRuleException('Only a final invoice can be credited.');
            }
            $note = new SalesCreditNote($data);
            $note->forceFill([
                'company_id' => $invoice->company_id, 'branch_id' => $invoice->branch_id,
                'credit_note_number' => $this->numbers->next('sales_credit_note', $invoice->company_id, $invoice->branch_id, $data['credit_note_date']),
                'sales_invoice_id' => $invoice->id, 'customer_id' => $invoice->customer_id,
                'currency_id' => $invoice->currency_id, 'status' => 'draft', 'created_by' => $this->tenant->user()->id,
            ])->save();
            $subtotal = $tax = $total = '0.0000';
            foreach ($items as $input) {
                $source = $invoice->items()->whereKey($input['sales_invoice_item_id'])->firstOrFail();
                $already = $source->invoice->creditNotes()->whereIn('status', ['issued', 'partially_applied', 'applied', 'refunded'])
                    ->whereHas('items', fn ($query) => $query->where('sales_invoice_item_id', $source->id))->with('items')->get()
                    ->flatMap->items->where('sales_invoice_item_id', $source->id)->sum('quantity');
                $quantity = (string) $input['quantity'];
                if (bccomp($quantity, '0', 6) !== 1 || bccomp(bcadd((string) $already, $quantity, 6), $source->quantity, 6) === 1) {
                    throw new BusinessRuleException('Credit quantity exceeds the remaining invoice item.');
                }
                $net = $this->rounding->round(bcmul($quantity, $source->unit_price, 8), 2);
                $itemTax = $this->rounding->round(bcdiv(bcmul($net, $source->tax_rate, 8), '100', 8), 2);
                $itemTotal = $this->rounding->round(bcadd($net, $itemTax, 8), 2);
                $creditItem = $note->items()->make();
                $creditItem->forceFill([
                    'sales_invoice_item_id' => $source->id, 'description' => $source->description,
                    'quantity' => $quantity, 'unit_price' => $source->unit_price, 'net_amount' => $net,
                    'tax_rate' => $source->tax_rate, 'tax_amount' => $itemTax, 'total' => $itemTotal,
                ])->save();
                $subtotal = bcadd($subtotal, $net, 4);
                $tax = bcadd($tax, $itemTax, 4);
                $total = bcadd($total, $itemTotal, 4);
            }
            if (bccomp($total, bcsub($invoice->total, $invoice->credited_amount, 4), 4) === 1) {
                throw new BusinessRuleException('Credit note exceeds the remaining invoice value.');
            }
            $note->forceFill(['subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => $total, 'remaining_amount' => $total])->save();

            return $note->load('items');
        });
    }

    public function approve(SalesCreditNote $note): SalesCreditNote
    {
        return $this->transition($note, 'draft', 'approved', 'approved');
    }

    public function issue(SalesCreditNote $note): SalesCreditNote
    {
        return DB::transaction(function () use ($note) {
            $note = SalesCreditNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            if ($note->status !== 'approved') {
                throw new BusinessRuleException('Only approved credit notes can be issued.');
            }
            $invoice = SalesInvoice::query()->whereKey($note->sales_invoice_id)->lockForUpdate()->firstOrFail();
            abort_unless($invoice->company_id === $this->tenant->companyId() && $this->tenant->user()->canAccessBranch($invoice->branch), 403);
            $issuedTotal = (string) SalesCreditNote::query()
                ->where('sales_invoice_id', $invoice->id)
                ->where('id', '!=', $note->id)
                ->whereIn('status', ['issued', 'partially_applied', 'applied', 'refunded'])
                ->sum('total');
            if (bccomp(bcadd($issuedTotal, $note->total, 4), $invoice->total, 4) === 1) {
                throw new BusinessRuleException('Credit note exceeds the remaining invoice value.');
            }
            foreach ($note->items as $creditItem) {
                $alreadyIssued = (string) SalesCreditNoteItem::query()
                    ->where('sales_invoice_item_id', $creditItem->sales_invoice_item_id)
                    ->whereHas('creditNote', fn ($query) => $query
                        ->where('id', '!=', $note->id)
                        ->whereIn('status', ['issued', 'partially_applied', 'applied', 'refunded']))
                    ->sum('quantity');
                if (bccomp(
                    bcadd($alreadyIssued, $creditItem->quantity, 6),
                    $creditItem->invoiceItem->quantity,
                    6
                ) === 1) {
                    throw new BusinessRuleException('Credit quantity exceeds the remaining invoice item.');
                }
            }
            $note->forceFill(['status' => 'issued', 'issued_by' => $this->tenant->user()->id, 'issued_at' => now()])->save();
            $this->balances->rebuild($invoice);
            $this->audit->record('credit_note.issued', $note);
            DB::afterCommit(fn () => event(new CreditNoteIssued($note->id)));

            return $note;
        });
    }

    private function transition(SalesCreditNote $note, string $from, string $to, string $action): SalesCreditNote
    {
        return DB::transaction(function () use ($note, $from, $to, $action) {
            $note = SalesCreditNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            abort_unless($note->company_id === $this->tenant->companyId(), 403);
            if ($note->status !== $from) {
                throw new BusinessRuleException("Credit note must be {$from}.");
            }
            $note->forceFill(['status' => $to, "{$action}_by" => $this->tenant->user()->id, "{$action}_at" => now()])->save();

            return $note;
        });
    }
}
