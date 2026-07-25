<?php

namespace App\Console\Commands;

use App\Events\QuotationExpired;
use App\Models\AuditLog;
use App\Models\Quotation;
use Illuminate\Console\Command;

class ExpireQuotations extends Command
{
    protected $signature = 'quotations:expire';

    protected $description = 'Expire approved or sent quotations past their validity date';

    public function handle(): int
    {
        $count = 0;
        Quotation::query()->whereIn('status', ['approved', 'sent'])->whereDate('valid_until', '<', today())
            ->orderBy('id')->chunkById(100, function ($quotations) use (&$count) {
                foreach ($quotations as $quotation) {
                    if (! Quotation::query()->whereKey($quotation->id)->whereIn('status', ['approved', 'sent'])
                        ->update(['status' => 'expired', 'updated_at' => now()])) {
                        continue;
                    }
                    $log = new AuditLog(['event' => 'quotation.expired', 'metadata' => ['source' => 'command']]);
                    $log->forceFill(['company_id' => $quotation->company_id, 'branch_id' => $quotation->branch_id]);
                    $log->auditable()->associate($quotation);
                    $log->save();
                    event(new QuotationExpired($quotation->id));
                    $count++;
                }
            });
        $this->info("Expired {$count} quotations.");

        return self::SUCCESS;
    }
}
