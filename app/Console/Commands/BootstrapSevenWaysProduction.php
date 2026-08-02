<?php

namespace App\Console\Commands;

use App\Services\ProductionBootstrap\SevenWaysProductionBootstrap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BootstrapSevenWaysProduction extends Command
{
    protected $signature = 'sevenways:bootstrap-production
        {--dry-run : Preview all changes without committing database rows}
        {--apply : Apply the bootstrap changes}
        {--force : Confirm production data changes}
        {--rotate-passwords : Rotate existing bootstrap user passwords from environment values}
        {--replace-accounting-mappings : Replace existing eligible branch mappings}
        {--json : Output JSON instead of the console summary}';

    protected $description = 'Safely bootstrap Seven Ways Egypt production reference and operating data';

    public function handle(SevenWaysProductionBootstrap $bootstrap): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Use either --dry-run or --apply, not both.');

            return self::INVALID;
        }
        $apply = (bool) $this->option('apply');
        if ($apply && (! app()->environment('production')
            || ! config('sevenways_production.enabled')
            || ! $this->option('force'))) {
            $this->error('Apply requires APP_ENV=production, SEVENWAYS_PRODUCTION_BOOTSTRAP=true and --force.');

            return self::FAILURE;
        }

        $bootstrap->configure([
            'rotate_passwords' => (bool) $this->option('rotate-passwords'),
            'replace_accounting_mappings' => (bool) $this->option('replace-accounting-mappings'),
            'authorized_execution' => true,
        ]);
        DB::beginTransaction();
        try {
            $result = $bootstrap->runAll();
            $apply ? DB::commit() : DB::rollBack();
        } catch (Throwable $exception) {
            DB::rollBack();
            $bootstrap->addError($exception->getMessage());
            $result = $bootstrap->snapshot();
        }
        $report = $bootstrap->saveReport($result, $apply ? 'APPLY' : 'DRY RUN');
        $result['report'] = $report;

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info($result['status'].' — Seven Ways Egypt production bootstrap');
            $this->line('Company: '.($result['company_name'] ?? '—').' #'.($result['company_id'] ?? '—'));
            $this->line('Mode: '.($apply ? 'APPLY' : 'DRY RUN'));
            $this->line('Document types: '.count($result['document_types'] ?? []));
            $this->line('Sequences per branch: '.($result['sequence_count_per_branch'] ?? 0));
            foreach ($result['warnings'] ?? [] as $warning) {
                $this->warn($warning);
            }
            foreach ($result['errors'] ?? [] as $error) {
                $this->error($error);
            }
            $this->line('Report: '.$report);
        }

        return $result['status'] === 'FAILED' ? self::FAILURE : self::SUCCESS;
    }
}
