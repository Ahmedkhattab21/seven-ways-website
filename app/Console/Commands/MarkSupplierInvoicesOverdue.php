<?php

namespace App\Console\Commands;

use App\Events\SupplierInvoiceOverdue;
use App\Models\SupplierInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkSupplierInvoicesOverdue extends Command
{
    protected $signature = 'supplier-invoices:mark-overdue';

    protected $description = 'Mark operational supplier invoices with past due balances as overdue';

    public function handle(): int
    {
        $count = 0;
        SupplierInvoice::whereIn('status', ['posted', 'partially_paid'])
            ->whereDate('due_date', '<', today())->where('balance_due', '>', 0)
            ->chunkById(100, function ($invoices) use (&$count) {
                foreach ($invoices as $invoice) {
                    DB::transaction(function () use ($invoice, &$count) {
                        $invoice = SupplierInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
                        if (! in_array($invoice->status, ['posted', 'partially_paid'], true)
                            || bccomp($invoice->balance_due, '0', 4) !== 1
                            || ! $invoice->due_date?->isPast()) {
                            return;
                        }
                        $invoice->forceFill(['status' => 'overdue'])->save();
                        DB::afterCommit(fn () => event(new SupplierInvoiceOverdue($invoice->id)));
                        $count++;
                    });
                }
            });
        $this->info("Marked {$count} supplier invoices overdue.");

        return self::SUCCESS;
    }
}
