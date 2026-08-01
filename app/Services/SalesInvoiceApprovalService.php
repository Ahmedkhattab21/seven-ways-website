<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\SalesInvoiceApproved;
use App\Events\SalesInvoiceCancelled;
use App\Events\SalesInvoiceSubmitted;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;

class SalesInvoiceApprovalService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function submit(SalesInvoice $invoice): SalesInvoice
    {
        return $this->transition($invoice, 'draft', 'pending_approval', 'submitted');
    }

    public function approve(SalesInvoice $invoice): SalesInvoice
    {
        return $this->transition($invoice, 'pending_approval', 'approved', 'approved');
    }

    public function cancel(SalesInvoice $invoice): SalesInvoice
    {
        if (! in_array($invoice->status, ['draft', 'pending_approval', 'approved'], true)) {
            throw new BusinessRuleException('Only an unissued invoice can be cancelled.');
        }

        return $this->transition($invoice, $invoice->status, 'cancelled', 'cancelled');
    }

    public function voidInvoice(SalesInvoice $invoice, string $reason): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $reason) {
            $invoice = SalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->with('items')->firstOrFail();
            abort_unless($invoice->company_id === $this->tenant->companyId() && $this->tenant->user()->canAccessBranch($invoice->branch), 403);
            if (! in_array($invoice->status, ['issued', 'overdue'], true)
                || bccomp($invoice->paid_amount, '0', 4) !== 0
                || bccomp($invoice->credited_amount, '0', 4) !== 0
                || $invoice->allocations()->whereNull('reversed_at')->exists()
                || $invoice->creditNotes()->whereNotIn('status', ['draft', 'cancelled'])->exists()) {
                throw new BusinessRuleException('Only an unpaid invoice without final allocations or credits can be voided.');
            }
            if ($invoice->invoice_type === 'direct_sale' && $invoice->items->where('item_type', 'product')
                ->contains(fn ($item) => bccomp($item->returned_quantity, $item->quantity, 6) === -1)) {
                throw new BusinessRuleException('Direct-sale stock must be returned before voiding the invoice.');
            }
            $invoice->forceFill([
                'status' => 'void', 'balance_due' => 0,
                'voided_by' => $this->tenant->user()->id, 'voided_at' => now(),
            ])->save();
            $this->audit->record('sales_invoice.voided', $invoice, ['reason' => $reason]);

            return $invoice;
        });
    }

    private function transition(SalesInvoice $invoice, string $from, string $to, string $action): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $from, $to, $action) {
            $invoice = SalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_unless($invoice->company_id === $this->tenant->companyId() && $this->tenant->user()->canAccessBranch($invoice->branch), 403);
            if ($invoice->status !== $from) {
                throw new BusinessRuleException("Invoice must be {$from}.");
            }
            $invoice->forceFill([
                'status' => $to, "{$action}_by" => $this->tenant->user()->id, "{$action}_at" => now(),
            ])->save();
            $metadata = [];
            if ($action === 'approved') {
                $metadata = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'company_id' => $invoice->company_id,
                    'branch_id' => $invoice->branch_id,
                    'approved_by' => $invoice->approved_by,
                    'approved_at' => $invoice->approved_at?->toIso8601String(),
                    'previous_status' => $from,
                    'new_status' => $to,
                ];
            }
            $this->audit->record("sales_invoice.{$action}", $invoice, $metadata);
            DB::afterCommit(fn () => event(match ($action) {
                'submitted' => new SalesInvoiceSubmitted($invoice->id),
                'approved' => new SalesInvoiceApproved($invoice->id),
                default => new SalesInvoiceCancelled($invoice->id),
            }));

            return $invoice;
        });
    }
}
