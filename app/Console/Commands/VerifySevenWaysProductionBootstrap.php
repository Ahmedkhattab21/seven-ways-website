<?php

namespace App\Console\Commands;

use App\Services\ProductionBootstrap\SevenWaysProductionBootstrap;
use Illuminate\Console\Command;
use Throwable;

class VerifySevenWaysProductionBootstrap extends Command
{
    protected $signature = 'sevenways:verify-production-bootstrap {--json : Output JSON}';

    protected $description = 'Read-only verification of the Seven Ways Egypt production bootstrap';

    public function handle(SevenWaysProductionBootstrap $bootstrap): int
    {
        try {
            $result = $bootstrap->configure()->verify();
        } catch (Throwable $exception) {
            $result = ['status' => 'FAILED', 'issues' => [$exception->getMessage()]];
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $result['status'] === 'READY' ? $this->info('READY') : $this->error('FAILED');
            foreach ($result['issues'] as $issue) {
                $this->line('- '.$issue);
            }
        }

        return $result['status'] === 'READY' ? self::SUCCESS : self::FAILURE;
    }
}
