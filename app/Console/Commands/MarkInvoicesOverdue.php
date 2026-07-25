<?php

namespace App\Console\Commands;

use App\Events\SalesInvoiceOverdue;
use App\Models\SalesInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkInvoicesOverdue extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark issued customer invoices with past due balances as overdue';

    public function handle(): int
    {
        $count = 0;
        SalesInvoice::query()->whereIn('status', ['issued', 'partially_paid'])
            ->whereDate('due_date', '<', today())->where('balance_due', '>', 0)
            ->chunkById(100, function ($invoices) use (&$count) {
                foreach ($invoices as $invoice) {
                    DB::transaction(function () use ($invoice, &$count) {
                        if (! SalesInvoice::whereKey($invoice->id)->whereIn('status', ['issued', 'partially_paid'])->update(['status' => 'overdue'])) {
                            return;
                        }
                        DB::afterCommit(fn () => event(new SalesInvoiceOverdue($invoice->id)));
                        $count++;
                    });
                }
            });
        $this->info("Marked {$count} invoices overdue.");

        return self::SUCCESS;
    }
}
