<?php

namespace App\Services;

use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPaymentAllocation;

class SupplierInvoiceBalanceService
{
    public function rebuild(SupplierInvoice $invoice): SupplierInvoice
    {
        $invoice = SupplierInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
        $paid = (string) SupplierPaymentAllocation::where('supplier_invoice_id', $invoice->id)
            ->whereNull('reversed_at')->sum('amount');
        $credited = (string) SupplierCreditNote::where('supplier_invoice_id', $invoice->id)
            ->whereIn('status', ['posted', 'partially_applied', 'applied'])->sum('applied_amount');
        $balance = bcsub(bcsub($invoice->total, $paid, 4), $credited, 4);
        if (bccomp($balance, '0', 4) < 0) {
            $balance = '0.0000';
        }
        $status = $invoice->status;
        if (! in_array($status, ['draft', 'pending_approval', 'approved', 'cancelled', 'void'], true)) {
            $status = match (true) {
                bccomp($balance, '0', 4) === 0 && bccomp($credited, $invoice->total, 4) >= 0 => 'credited',
                bccomp($balance, '0', 4) === 0 => 'paid',
                $invoice->due_date?->isPast() => 'overdue',
                bccomp($paid, '0', 4) > 0 => 'partially_paid',
                default => 'posted',
            };
        }
        $invoice->forceFill([
            'paid_amount' => $paid, 'credited_amount' => $credited,
            'balance_due' => $balance, 'status' => $status,
        ])->save();

        return $invoice;
    }
}
