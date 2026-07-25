<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;

class SupplierStatementService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function build(Supplier $supplier, int $currencyId, ?int $branchId = null, ?string $from = null, ?string $to = null): array
    {
        abort_unless($supplier->company_id === $this->tenant->companyId(), 403);
        $branches = $this->tenant->accessibleBranches()->pluck('id');
        if ($branchId) {
            abort_unless($branches->contains($branchId), 403);
            $branches = collect([$branchId]);
        }
        $range = fn ($query, string $column) => $query
            ->when($from, fn ($q) => $q->whereDate($column, '>=', $from))
            ->when($to, fn ($q) => $q->whereDate($column, '<=', $to));
        $entries = collect();
        foreach ($range(SupplierInvoice::where('supplier_id', $supplier->id)->where('currency_id', $currencyId)
            ->whereIn('branch_id', $branches)->whereNotIn('status', ['draft', 'pending_approval', 'approved', 'cancelled', 'void']), 'invoice_date')->get() as $invoice) {
            $entries->push(['date' => $invoice->invoice_date, 'type' => 'invoice', 'reference' => $invoice->internal_invoice_number, 'debit' => $invoice->total, 'credit' => '0.0000']);
        }
        foreach ($range(SupplierCreditNote::where('supplier_id', $supplier->id)->whereIn('branch_id', $branches)->whereIn('status', ['posted', 'partially_applied', 'applied']), 'credit_date')->get() as $credit) {
            $entries->push(['date' => $credit->credit_date, 'type' => 'credit', 'reference' => $credit->credit_note_number, 'debit' => '0.0000', 'credit' => $credit->applied_amount]);
        }
        foreach ($range(SupplierPayment::where('supplier_id', $supplier->id)->where('currency_id', $currencyId)
            ->whereIn('branch_id', $branches)->whereIn('status', ['processed', 'partially_allocated', 'allocated']), 'payment_date')->get() as $payment) {
            $entries->push(['date' => $payment->payment_date, 'type' => 'payment', 'reference' => $payment->payment_number, 'debit' => '0.0000', 'credit' => $payment->amount]);
        }
        foreach (SupplierPaymentAllocation::whereIn('supplier_invoice_id', SupplierInvoice::where('supplier_id', $supplier->id)->select('id'))->get() as $allocation) {
            $entries->push(['date' => $allocation->reversed_at ?? $allocation->allocated_at, 'type' => $allocation->reversed_at ? 'allocation_reversal' : 'allocation', 'reference' => $allocation->uuid, 'debit' => '0.0000', 'credit' => '0.0000']);
        }
        foreach ($range(PurchaseReturn::where('supplier_id', $supplier->id)->whereIn('branch_id', $branches)->where('status', 'posted'), 'return_date')->get() as $return) {
            $entries->push(['date' => $return->return_date, 'type' => 'purchase_return', 'reference' => $return->purchase_return_number, 'debit' => '0.0000', 'credit' => '0.0000']);
        }
        $balance = '0.0000';
        $entries = $entries->sortBy('date')->values()->map(function ($entry) use (&$balance) {
            $balance = bcsub(bcadd($balance, $entry['debit'], 4), $entry['credit'], 4);

            return $entry + ['balance' => $balance];
        });

        return ['currency_id' => $currencyId, 'entries' => $entries, 'balance' => $balance];
    }
}
