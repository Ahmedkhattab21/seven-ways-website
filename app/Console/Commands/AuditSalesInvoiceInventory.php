<?php

namespace App\Console\Commands;

use App\Models\SalesInvoice;
use Illuminate\Console\Command;

class AuditSalesInvoiceInventory extends Command
{
    protected $signature = 'inventory:audit-sales-invoices {--dry-run : Report only; never change invoice or inventory data}';

    protected $description = 'Report issued sales invoice product lines that have no inventory issue movement';

    public function handle(): int
    {
        if (! $this->option('dry-run')) {
            $this->error('This audit is read-only. Run it with --dry-run.');

            return self::FAILURE;
        }

        $invoices = SalesInvoice::query()
            ->whereIn('status', ['issued', 'partially_paid', 'paid', 'overdue', 'credited'])
            ->whereHas('items', fn ($query) => $query
                ->where('item_type', 'product')
                ->whereNull('issued_movement_id'))
            ->with(['items' => fn ($query) => $query
                ->where('item_type', 'product')
                ->whereNull('issued_movement_id')
                ->with('product')])
            ->orderBy('id')
            ->get();

        $rows = $invoices->flatMap(fn (SalesInvoice $invoice) => $invoice->items->map(fn ($item) => [
            $invoice->invoice_number,
            $item->product?->sku ?? '—',
            $item->quantity,
            'Missing sales inventory movement',
        ]));

        $rows->each(function (array $row): void {
            $this->line("Invoice: {$row[0]}");
            $this->line("SKU: {$row[1]}");
            $this->line("Quantity: {$row[2]}");
            $this->line("Problem: {$row[3]}");
        });
        $this->table(['Invoice', 'SKU', 'Quantity', 'Problem'], $rows);
        $this->info("Found {$rows->count()} product line(s) missing an inventory issue movement. No data was changed.");

        return self::SUCCESS;
    }
}
