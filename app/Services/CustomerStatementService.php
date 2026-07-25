<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\PaymentAllocation;
use App\Models\SalesCreditNote;
use App\Models\SalesInvoice;
use Carbon\Carbon;

class CustomerStatementService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function build(Customer $customer, int $currencyId, ?string $from = null, ?string $to = null): array
    {
        abort_unless(
            $customer->company_id === $this->tenant->companyId()
            && Customer::forUser($this->tenant->user())->whereKey($customer->id)->exists(),
            403
        );
        $entries = collect();
        SalesInvoice::where('customer_id', $customer->id)->where('currency_id', $currencyId)
            ->whereIn('status', ['issued', 'partially_paid', 'paid', 'overdue', 'credited'])->get()
            ->each(fn ($row) => $entries->push($this->entry($row->invoice_date, 'invoice', $row->invoice_number, $row->total, 0)));
        SalesCreditNote::where('customer_id', $customer->id)->where('currency_id', $currencyId)->whereIn('status', ['issued', 'partially_applied', 'applied', 'refunded'])->get()
            ->each(fn ($row) => $entries->push($this->entry($row->credit_note_date, 'credit_note', $row->credit_note_number, 0, $row->total)));
        CustomerPayment::where('customer_id', $customer->id)->where('currency_id', $currencyId)->whereNotIn('status', ['draft', 'cancelled'])->get()
            ->each(fn ($row) => $entries->push($this->entry($row->payment_date, 'payment', $row->payment_number, 0, $row->amount)));
        PaymentAllocation::whereHas('payment', fn ($query) => $query->where('customer_id', $customer->id)->where('currency_id', $currencyId))
            ->with(['payment', 'invoice'])->get()->each(function ($row) use ($entries) {
                $entries->push($this->entry($row->allocated_at, 'allocation', $row->payment->payment_number.' → '.$row->invoice->invoice_number, 0, 0));
                if ($row->reversed_at) {
                    $entries->push($this->entry($row->reversed_at, 'allocation_reversal', $row->payment->payment_number.' → '.$row->invoice->invoice_number, 0, 0));
                }
            });
        CustomerRefund::where('customer_id', $customer->id)->where('status', 'processed')
            ->whereHas('creditNote', fn ($query) => $query->where('currency_id', $currencyId))->get()
            ->each(fn ($row) => $entries->push($this->entry($row->refund_date, 'refund', $row->refund_number, $row->amount, 0)));
        $entries = $entries->filter(fn ($row) => (! $from || $row['date'] >= $from) && (! $to || $row['date'] <= $to))
            ->sortBy(fn ($row) => $row['date'].'-'.$row['reference'])->values();
        $balance = '0.0000';
        $entries = $entries->map(function ($row) use (&$balance) {
            $balance = bcadd($balance, bcsub((string) $row['debit'], (string) $row['credit'], 4), 4);
            $row['running_balance'] = $balance;

            return $row;
        });

        return ['currency_id' => $currencyId, 'entries' => $entries, 'balance' => $balance];
    }

    private function entry($date, string $type, string $reference, $debit, $credit): array
    {
        return ['date' => Carbon::parse($date)->toDateString(), 'type' => $type, 'reference' => $reference, 'debit' => $debit, 'credit' => $credit];
    }
}
