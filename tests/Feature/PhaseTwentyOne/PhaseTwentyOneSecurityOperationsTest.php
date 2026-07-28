<?php

namespace Tests\Feature\PhaseTwentyOne;

use App\Services\UatEnvironmentGuard;
use Database\Seeders\SevenWaysUatSeeder;
use RuntimeException;
use Tests\TestCase;

class PhaseTwentyOneSecurityOperationsTest extends TestCase
{
    public function test_uat_guard_rejects_port_3306_and_non_uat_database(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => 3306,
            'database.connections.mysql.database' => 'seven_ways_clean_local',
        ]);

        $this->expectException(RuntimeException::class);
        app(UatEnvironmentGuard::class)->assertSafe();
    }

    public function test_uat_seeder_has_explicit_guard_and_no_production_registration(): void
    {
        $source = file_get_contents(database_path('seeders/SevenWaysUatSeeder.php'));
        $databaseSeeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringContainsString('UatEnvironmentGuard::class', $source);
        $this->assertStringNotContainsString(SevenWaysUatSeeder::class, $databaseSeeder);
        $this->assertStringNotContainsString('StockBalance::', $source);
        $this->assertStringNotContainsString('JournalEntry::', $source);
    }

    public function test_public_health_is_minimal_and_security_headers_are_present(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok'])
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_queue_and_scheduler_operational_controls_exist(): void
    {
        $kernel = file_get_contents(app_path('Console/Kernel.php'));
        $jobsMigration = file_get_contents(database_path('migrations/2026_07_28_100000_create_jobs_table.php'));

        $this->assertSame(6, substr_count($kernel, '$schedule->command('));
        $this->assertStringContainsString("Schema::create('jobs'", $jobsMigration);
        $this->assertStringContainsString('queue:restart', file_get_contents(
            base_path('docs/production/queue-worker-runbook.md')
        ));
    }
}
