<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\SalesInvoiceIssued;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;

class SalesInvoiceIssuanceService
{
    public function __construct(
        private TenantContext $tenant,
        private DirectSaleInventoryService $inventory,
        private AuditService $audit
    ) {
    }

    public function issue(SalesInvoice $invoice): SalesInvoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = SalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->with('items')->firstOrFail();
            abort_unless($invoice->company_id === $this->tenant->companyId() && $this->tenant->user()->canAccessBranch($invoice->branch), 403);
            if ($invoice->status !== 'approved') {
                throw new BusinessRuleException('Only approved invoices can be issued.');
            }
            if ($invoice->work_order_id && SalesInvoice::where('work_order_id', $invoice->work_order_id)
                ->where('id', '!=', $invoice->id)->whereIn('status', ['issued', 'partially_paid', 'paid', 'overdue', 'credited'])->exists()) {
                throw new BusinessRuleException('A final invoice already exists for this work order.');
            }
            $this->inventory->issue($invoice);
            $invoice->forceFill(['status' => 'issued', 'issued_by' => $this->tenant->user()->id, 'issued_at' => now()])->save();
            $this->audit->record('sales_invoice.issued', $invoice);
            DB::afterCommit(fn () => event(new SalesInvoiceIssued($invoice->id)));

            return $invoice;
        });
    }
}
