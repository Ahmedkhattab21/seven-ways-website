<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Models\SupplierInvoiceMatch;
use Illuminate\Support\Facades\DB;

class SupplierInvoiceMatchingService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function match(SupplierInvoice $invoice): SupplierInvoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = SupplierInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_unless($invoice->company_id === $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($invoice->branch), 403);
            if ($invoice->status !== 'draft') {
                throw new BusinessRuleException('Only draft supplier invoices can be matched.');
            }
            foreach ($invoice->items()->lockForUpdate()->with(['purchaseOrderItem', 'receiptItem'])->get() as $item) {
                $item->matches()->delete();
                $poItem = $item->purchaseOrderItem;
                $receiptItem = $item->receiptItem;
                $available = $this->availableQuantity($invoice, $item, $poItem, $receiptItem);
                $matched = bccomp($item->quantity, $available, 6) <= 0 ? $item->quantity : $available;
                $quantityVariance = ($poItem || $receiptItem)
                    ? bcsub($item->quantity, $available, 6)
                    : '0.000000';
                $priceVariance = $poItem ? bcsub($item->unit_price, $poItem->unit_price, 4) : '0.0000';
                $taxVariance = $poItem ? bccomp($item->tax_rate, $poItem->tax_rate, 4) !== 0 : false;
                $status = match (true) {
                    ! $poItem && ! $receiptItem => 'unmatched',
                    bccomp($quantityVariance, '0', 6) === 1 => 'over_invoiced',
                    bccomp($quantityVariance, '0', 6) !== 0 => 'quantity_variance',
                    bccomp($priceVariance, '0', 4) !== 0 => 'price_variance',
                    $taxVariance => 'tax_variance',
                    default => 'matched',
                };
                $match = new SupplierInvoiceMatch;
                $match->forceFill([
                    'supplier_invoice_item_id' => $item->id,
                    'purchase_order_item_id' => $poItem?->id,
                    'goods_receipt_item_id' => $receiptItem?->id,
                    'matched_quantity' => $matched,
                    'po_unit_price' => $poItem?->unit_price,
                    'invoice_unit_price' => $item->unit_price,
                    'price_variance' => $priceVariance,
                    'quantity_variance' => $quantityVariance,
                    'status' => $status,
                ])->save();
                $item->forceFill(['matched_quantity' => $matched])->save();
            }

            return $invoice->load('items.matches');
        });
    }

    public function approveVariance(SupplierInvoiceMatch $match, string $reason, int $userId): SupplierInvoiceMatch
    {
        if ($match->status === 'matched' || blank($reason)) {
            throw new BusinessRuleException('A variance and approval reason are required.');
        }
        $match->forceFill(['approved_by' => $userId, 'approval_reason' => $reason])->save();

        return $match;
    }

    private function availableQuantity(
        SupplierInvoice $invoice,
        SupplierInvoiceItem $item,
        $poItem,
        $receiptItem
    ): string {
        if ($receiptItem) {
            $prior = (string) SupplierInvoiceItem::where('goods_receipt_item_id', $receiptItem->id)
                ->where('supplier_invoice_id', '!=', $invoice->id)
                ->whereHas('invoice', fn ($query) => $query
                    ->whereIn('status', ['posted', 'partially_paid', 'paid', 'credited', 'overdue']))
                ->sum('quantity');

            $available = bcsub($receiptItem->accepted_quantity, $prior, 6);

            return bccomp($available, '0', 6) < 0 ? '0.000000' : $available;
        }
        if ($poItem) {
            $available = bcsub($poItem->received_quantity, $poItem->invoiced_quantity, 6);

            return bccomp($available, '0', 6) < 0 ? '0.000000' : $available;
        }

        return '0.000000';
    }
}
