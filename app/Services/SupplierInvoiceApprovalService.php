<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\SupplierInvoiceApproved;
use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;

class SupplierInvoiceApprovalService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function submit(SupplierInvoice $invoice): SupplierInvoice
    {
        return $this->transition($invoice, 'draft', 'pending_approval', 'submitted');
    }

    public function approve(SupplierInvoice $invoice): SupplierInvoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = $this->lockScoped($invoice);
            if ($invoice->status !== 'pending_approval') {
                throw new BusinessRuleException('Only pending supplier invoices can be approved.');
            }
            if (config('purchasing.separation_of_duties', true) && $invoice->created_by === $this->tenant->user()->id) {
                throw new BusinessRuleException('The invoice creator cannot approve it.');
            }
            $invoice->forceFill([
                'status' => 'approved', 'approved_by' => $this->tenant->user()->id, 'approved_at' => now(),
            ])->save();
            $this->audit->record('supplier_invoice.approved', $invoice);
            DB::afterCommit(fn () => event(new SupplierInvoiceApproved($invoice->id)));

            return $invoice;
        });
    }

    private function transition(SupplierInvoice $invoice, string $from, string $to, string $action): SupplierInvoice
    {
        return DB::transaction(function () use ($invoice, $from, $to, $action) {
            $invoice = $this->lockScoped($invoice);
            if ($invoice->status !== $from || ! $invoice->items()->exists()) {
                throw new BusinessRuleException("Supplier invoice must be {$from} and contain items.");
            }
            $invoice->forceFill([
                'status' => $to, "{$action}_by" => $this->tenant->user()->id, "{$action}_at" => now(),
            ])->save();

            return $invoice;
        });
    }

    private function lockScoped(SupplierInvoice $invoice): SupplierInvoice
    {
        $invoice = SupplierInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
        abort_unless($invoice->company_id === $this->tenant->companyId()
            && $this->tenant->user()->canAccessBranch($invoice->branch), 403);

        return $invoice;
    }
}
