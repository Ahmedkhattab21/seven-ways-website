<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceMatch;
use Illuminate\Support\Facades\DB;

class SupplierInvoiceMatchingService
{
    public function match(SupplierInvoice $invoice): SupplierInvoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = SupplierInvoice::whereKey($invoice->id)->lockForUpdate()
                ->with(['items.purchaseOrderItem', 'items.receiptItem'])->firstOrFail();
            if ($invoice->status !== 'draft') {
                throw new BusinessRuleException('Only draft supplier invoices can be matched.');
            }
            foreach ($invoice->items as $item) {
                $item->matches()->delete();
                $poItem = $item->purchaseOrderItem;
                $receiptItem = $item->receiptItem;
                $matched = $receiptItem
                    ? min((float) $item->quantity, (float) $receiptItem->accepted_quantity)
                    : min((float) $item->quantity, (float) ($poItem?->received_quantity ?? 0));
                $matched = number_format($matched, 6, '.', '');
                $quantityVariance = $poItem
                    ? bcsub($item->quantity, bcsub($poItem->received_quantity, $poItem->invoiced_quantity, 6), 6)
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
}
