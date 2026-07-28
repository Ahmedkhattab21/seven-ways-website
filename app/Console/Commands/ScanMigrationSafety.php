<?php

namespace App\Console\Commands;

use App\Services\MigrationSafetyScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScanMigrationSafety extends Command
{
    protected $signature = 'production:scan-migrations {--all : Scan historical and pending migrations}';

    protected $description = 'Read-only scan of migration up() methods for destructive patterns';

    public function handle(MigrationSafetyScanner $scanner): int
    {
        $ran = Schema::hasTable('migrations')
            ? DB::table('migrations')->pluck('migration')->all()
            : [];
        $findings = [];
        $reviewed = [];
        $allowlist = (array) config('production.reviewed_migration_operations', []);

        foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (! $this->option('all') && in_array($name, $ran, true)) {
                continue;
            }
            foreach ($scanner->scan((string) file_get_contents($file)) as $finding) {
                if (array_key_exists($name, $allowlist)) {
                    $reviewed[] = [$name, $finding, $allowlist[$name]];
                } else {
                    $findings[] = [$name, $finding];
                }
            }
        }

        if ($reviewed !== []) {
            $this->table(['Reviewed migration', 'Operation', 'Review basis'], $reviewed);
        }
        if ($findings !== []) {
            $this->table(['Migration', 'Finding'], $findings);
            $this->error('Migration review required; no database change was made.');

            return self::FAILURE;
        }

        $this->info('Pending migration safety scan passed.');

        return self::SUCCESS;
    }
}
