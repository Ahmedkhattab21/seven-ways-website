<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\SupplierCreditNotePosted;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;

class SupplierCreditNoteService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private SupplierInvoiceBalanceService $balances,
        private AuditService $audit
    ) {
    }

    public function create(array $data, array $items): SupplierCreditNote
    {
        return DB::transaction(function () use ($data, $items) {
            $invoice = null;
            if (! empty($data['supplier_invoice_id'])) {
                $invoice = SupplierInvoice::whereKey($data['supplier_invoice_id'])
                    ->where('company_id', $this->tenant->companyId())
                    ->where('branch_id', $this->tenant->branchId())
                    ->whereIn('status', ['posted', 'partially_paid', 'paid', 'overdue'])->firstOrFail();
            }
            $supplierId = $invoice?->supplier_id ?? $data['supplier_id'];
            $supplier = Supplier::whereKey($supplierId)
                ->where('company_id', $this->tenant->companyId())
                ->firstOrFail();
            if (! empty($data['purchase_return_id'])) {
                PurchaseReturn::whereKey($data['purchase_return_id'])
                    ->where('company_id', $this->tenant->companyId())
                    ->where('branch_id', $this->tenant->branchId())
                    ->where('supplier_id', $supplierId)
                    ->where('status', 'posted')
                    ->firstOrFail();
            }
            $note = new SupplierCreditNote($data);
            $note->forceFill([
                'company_id' => $this->tenant->companyId(), 'branch_id' => $this->tenant->branchId(),
                'supplier_id' => $supplierId,
                'currency_id' => $invoice?->currency_id
                    ?? $supplier->currency_id
                    ?? $this->tenant->company()->currency_id,
                'credit_note_number' => $this->numbers->next(
                    'supplier_credit_note',
                    $this->tenant->companyId(),
                    $this->tenant->branchId(),
                    $data['credit_date']
                ),
                'status' => 'draft', 'created_by' => $this->tenant->user()->id,
            ])->save();
            $subtotal = $tax = $total = '0.0000';
            foreach ($items as $item) {
                $quantity = (string) $item['quantity'];
                $net = bcmul($quantity, (string) $item['unit_price'], 4);
                $itemTax = bcdiv(bcmul($net, (string) ($item['tax_rate'] ?? 0), 8), '100', 4);
                $lineTotal = bcadd($net, $itemTax, 4);
                $noteItem = $note->items()->make();
                $noteItem->forceFill($item + [
                    'net_amount' => $net,
                    'tax_amount' => $itemTax,
                    'total' => $lineTotal,
                ])->save();
                $subtotal = bcadd($subtotal, $net, 4);
                $tax = bcadd($tax, $itemTax, 4);
                $total = bcadd($total, $lineTotal, 4);
            }
            if ($invoice && bccomp($total, $invoice->balance_due, 4) === 1) {
                throw new BusinessRuleException('Supplier credit note exceeds invoice balance.');
            }
            $note->forceFill([
                'subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => $total,
                'applied_amount' => 0, 'remaining_amount' => $total,
            ])->save();

            return $note->load('items');
        });
    }

    public function approve(SupplierCreditNote $note): SupplierCreditNote
    {
        return $this->transition($note, 'draft', 'approved', 'approved_by');
    }

    public function post(SupplierCreditNote $note): SupplierCreditNote
    {
        return DB::transaction(function () use ($note) {
            $note = SupplierCreditNote::whereKey($note->id)->lockForUpdate()->firstOrFail();
            abort_unless($note->company_id === $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($note->branch), 403);
            if ($note->status !== 'approved') {
                throw new BusinessRuleException('Only approved supplier credits can be posted.');
            }
            $invoice = $note->invoice()->lockForUpdate()->first();
            if ($invoice) {
                $invoice = $this->balances->rebuild($invoice);
                if (bccomp($note->total, $invoice->balance_due, 4) === 1) {
                    throw new BusinessRuleException('Supplier credit exceeds current invoice balance.');
                }
                $note->forceFill(['applied_amount' => $note->total, 'remaining_amount' => 0])->save();
            }
            $note->forceFill([
                'status' => 'posted', 'posted_by' => $this->tenant->user()->id,
            ])->save();
            if ($invoice) {
                $this->balances->rebuild($invoice);
            }
            $this->audit->record('supplier_credit_note.posted', $note, ['operational_only' => true]);
            DB::afterCommit(fn () => event(new SupplierCreditNotePosted($note->id)));

            return $note;
        });
    }

    private function transition(SupplierCreditNote $note, string $from, string $to, string $actorField): SupplierCreditNote
    {
        return DB::transaction(function () use ($note, $from, $to, $actorField) {
            $note = SupplierCreditNote::whereKey($note->id)->lockForUpdate()->firstOrFail();
            abort_unless($note->company_id === $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($note->branch), 403);
            if ($note->status !== $from) {
                throw new BusinessRuleException("Supplier credit must be {$from}.");
            }
            $note->forceFill(['status' => $to, $actorField => $this->tenant->user()->id])->save();

            return $note;
        });
    }
}
