<?php

namespace App\Console\Commands;

use App\Services\UatEnvironmentGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ValidateUatEnvironment extends Command
{
    protected $signature = 'uat:validate-target';

    protected $description = 'Validate and display the isolated UAT database target without secrets';

    public function handle(UatEnvironmentGuard $guard): int
    {
        try {
            $summary = $guard->safeSummary();
            $summary['MariaDB version'] = (string) DB::scalar('SELECT VERSION()');
        } catch (Throwable $exception) {
            $this->error(str_starts_with($exception->getMessage(), 'STOP')
                ? $exception->getMessage()
                : 'STOP — Unsafe database target or database unavailable.');

            return self::FAILURE;
        }

        $this->table(['Setting', 'Value'], collect($summary)->map(
            fn (string $value, string $name) => [$name, $value]
        )->values()->all());
        $this->info('UAT database target is safe.');

        return self::SUCCESS;
    }
}
