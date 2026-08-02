<?php

namespace Tests\Feature;

use App\Services\ProductionBootstrap\SevenWaysProductionBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionBootstrapReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_contains_real_emails_and_never_contains_password_material(): void
    {
        $bootstrap = app(SevenWaysProductionBootstrap::class)->configure();
        $result = [
            'status' => 'READY', 'company_id' => 1, 'company_name' => 'Seven Ways',
            'changes' => ['users' => [[
                'email' => 'manager@example.com', 'password_source' => 'Environment Variable', 'result' => 'Created',
            ]]],
            'warnings' => [], 'errors' => [], 'document_types' => ['sales_invoice'],
        ];

        $path = $bootstrap->saveReport($result, 'DRY RUN');
        $report = (string) file_get_contents($path);

        $this->assertStringContainsString('manager@example.com', $report);
        $this->assertStringContainsString('Environment Variable', $report);
        $this->assertStringNotContainsString('password_hash', $report);
        $this->assertStringNotContainsString('$2y$', $report);
    }
}
