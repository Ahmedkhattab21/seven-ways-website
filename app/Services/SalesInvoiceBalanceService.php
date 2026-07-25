<?php

namespace App\Services;

use App\Models\CustomerRefund;
use App\Models\PaymentAllocation;
use App\Models\SalesCreditNote;
use App\Models\SalesInvoice;

class SalesInvoiceBalanceService
{
    public function rebuild(SalesInvoice $invoice): SalesInvoice
    {
        $invoice = SalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
        $paid = (string) PaymentAllocation::query()
            ->where('sales_invoice_id', $invoice->id)
            ->whereNull('reversed_at')
            ->sum('amount');
        $credited = (string) SalesCreditNote::query()
            ->where('sales_invoice_id', $invoice->id)
            ->whereIn('status', ['issued', 'partially_applied', 'applied', 'refunded'])
            ->sum('total');
        $refunded = (string) CustomerRefund::query()
            ->where('status', 'processed')
            ->whereHas('creditNote', fn ($query) => $query->where('sales_invoice_id', $invoice->id))
            ->sum('amount');
        $balance = bcsub(bcsub((string) $invoice->total, $paid, 4), $credited, 4);
        if (bccomp($balance, '0', 4) < 0) {
            $balance = '0.0000';
        }

        $status = $invoice->status;
        if (! in_array($status, ['draft', 'pending_approval', 'approved', 'cancelled', 'void'], true)) {
            $status = match (true) {
                bccomp($balance, '0', 4) === 0 && bccomp($credited, (string) $invoice->total, 4) >= 0 => 'credited',
                bccomp($balance, '0', 4) === 0 => 'paid',
                $invoice->due_date?->isPast() => 'overdue',
                bccomp($paid, '0', 4) > 0 => 'partially_paid',
                default => 'issued',
            };
        }

        $invoice->forceFill([
            'paid_amount' => $paid,
            'credited_amount' => $credited,
            'refunded_amount' => $refunded,
            'balance_due' => $balance,
            'status' => $status,
        ])->save();

        return $invoice;
    }
}
