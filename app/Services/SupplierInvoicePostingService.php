<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\SupplierInvoicePosted;
use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;

class SupplierInvoicePostingService
{
    public function __construct(
        private TenantContext $tenant,
        private SupplierInvoiceBalanceService $balances,
        private AuditService $audit
    ) {
    }

    public function post(SupplierInvoice $invoice): SupplierInvoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = SupplierInvoice::whereKey($invoice->id)->lockForUpdate()
                ->with(['items.matches', 'items.purchaseOrderItem'])->firstOrFail();
            abort_unless($invoice->company_id === $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($invoice->branch), 403);
            if ($invoice->posted_at || $invoice->status !== 'approved') {
                throw new BusinessRuleException('Only approved supplier invoices can be posted.');
            }
            foreach ($invoice->items()->lockForUpdate()->with('purchaseOrderItem')->get() as $item) {
                $matches = $item->matches()->lockForUpdate()->get();
                if (config('purchasing.supplier_invoice_matching_required', true) && $matches->isEmpty()) {
                    throw new BusinessRuleException('Supplier invoice matching is required before posting.');
                }
                foreach ($matches as $match) {
                    if ($match->status !== 'matched' && ! $match->approved_by) {
                        throw new BusinessRuleException('Supplier invoice variances require approval before posting.');
                    }
                }
                if ($item->purchaseOrderItem) {
                    $poItem = $item->purchaseOrderItem()->lockForUpdate()->first();
                    $available = bcsub($poItem->received_quantity, $poItem->invoiced_quantity, 6);
                    if (bccomp($item->quantity, $available, 6) === 1
                        && ! $this->tenant->user()->hasPermission('supplier_invoices.override_variance')) {
                        throw new BusinessRuleException('Supplier invoice exceeds received quantity.');
                    }
                    $poItem->forceFill([
                        'invoiced_quantity' => bcadd($poItem->invoiced_quantity, $item->quantity, 6),
                    ])->save();
                }
            }
            $invoice->forceFill([
                'status' => 'posted', 'posted_by' => $this->tenant->user()->id, 'posted_at' => now(),
            ])->save();
            if ($invoice->purchase_order_id) {
                $this->refreshOrder($invoice->purchase_order_id);
            }
            $this->balances->rebuild($invoice);
            $this->audit->record('supplier_invoice.posted', $invoice, ['operational_only' => true]);
            DB::afterCommit(fn () => event(new SupplierInvoicePosted($invoice->id)));

            return $invoice->fresh();
        });
    }

    private function refreshOrder(int $orderId): void
    {
        $order = PurchaseOrder::whereKey($orderId)->lockForUpdate()->with('items')->firstOrFail();
        $amount = '0.0000';
        $all = true;
        foreach ($order->items as $item) {
            $amount = bcadd($amount, bcmul($item->invoiced_quantity, $item->unit_price, 4), 4);
            $all = $all && bccomp($item->invoiced_quantity, $item->received_quantity, 6) >= 0;
        }
        $order->forceFill([
            'invoiced_amount' => $amount,
            'status' => $all ? 'fully_invoiced' : 'partially_invoiced',
        ])->save();
    }
}
