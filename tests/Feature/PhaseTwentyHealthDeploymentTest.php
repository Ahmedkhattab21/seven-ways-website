<?php

namespace Tests\Feature;

use App\Services\MigrationSafetyScanner;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PhaseTwentyHealthDeploymentTest extends TestCase
{
    public function test_liveness_and_readiness_are_safe(): void
    {
        $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok']);

        $response = $this->getJson('/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.cache', 'ok')
            ->assertJsonPath('checks.storage', 'ok')
            ->assertJsonPath('checks.queue', 'ok');

        foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', base_path(), 'stack trace'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
    }

    public function test_failed_readiness_dependency_returns_safe_503(): void
    {
        config(['queue.default' => 'sync']);
        DB::shouldReceive('select')->once()->andThrow(new RuntimeException('database secret detail'));

        $response = $this->getJson('/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'unavailable')
            ->assertJsonPath('checks.database', 'unavailable');

        $this->assertStringNotContainsString('database secret detail', $response->getContent());
    }

    public function test_health_endpoint_is_rate_limited(): void
    {
        foreach (range(1, 30) as $attempt) {
            $this->getJson('/health')->assertOk();
        }
        $this->getJson('/health')->assertTooManyRequests();
    }

    public function test_migration_scanner_checks_up_only_and_flags_destructive_changes(): void
    {
        $scanner = app(MigrationSafetyScanner::class);
        $safe = '<?php function up(): void { Schema::create("safe", fn ($t) => null); } function down(): void { Schema::dropIfExists("safe"); }';
        $unsafe = '<?php function up(): void { Schema::dropIfExists("users"); } function down(): void {}';

        $this->assertSame([], $scanner->scan($safe));
        $this->assertContains('DROP operation', $scanner->scan($unsafe));
    }

    public function test_deployment_workflow_is_manual_dry_run_first_and_contains_no_key(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/deploy.yml'));

        $this->assertStringContainsString('workflow_dispatch:', $workflow);
        $this->assertStringContainsString('default: true', $workflow);
        $this->assertStringContainsString('environment: production', $workflow);
        $this->assertStringNotContainsString("branches:\n      - main", $workflow);
        $this->assertStringNotContainsString('BEGIN OPENSSH PRIVATE KEY', $workflow);
        $this->assertStringNotContainsString('key:generate --force', $workflow);
    }
}
