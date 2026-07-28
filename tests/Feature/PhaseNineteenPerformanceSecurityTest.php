<?php

namespace Tests\Feature;

use App\Models\ApprovalTask;
use App\Models\AuditEvent;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\SystemNotification;
use Database\Seeders\AnalyticsReportingSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsAnalyticsContext;
use Tests\TestCase;

class PhaseNineteenPerformanceSecurityTest extends TestCase
{
    use BuildsAnalyticsContext;
    use DatabaseTransactions;

    public function test_reporting_seeder_is_idempotent_and_creates_no_operational_rows(): void
    {
        $context = $this->analyticsContext();
        $before = [
            JournalEntry::count(), ApprovalTask::count(), SystemNotification::count(), AuditEvent::count(),
        ];
        app(AnalyticsReportingSeeder::class)->run();
        app(AnalyticsReportingSeeder::class)->run();

        $this->assertSame(15, Permission::query()->where(function ($query) {
            $query->where('name', 'like', 'reports.%')->orWhere('name', 'like', 'dashboards.%');
        })->count());
        $this->assertSame($before, [
            JournalEntry::count(), ApprovalTask::count(), SystemNotification::count(), AuditEvent::count(),
        ]);
    }

    public function test_phase_nineteen_adds_no_stored_kpi_or_balance_tables(): void
    {
        foreach (['profits', 'analytics_balances', 'dashboard_kpis', 'report_snapshots'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertFalse(Schema::hasColumn('accounts', 'balance'));
        $this->assertFalse(Schema::hasColumn('companies', 'profit'));
    }

    public function test_all_analytics_routes_are_controller_backed_and_authenticated(): void
    {
        foreach ([
            'dashboards.executive', 'dashboards.branches',
            'analytics.reports.show', 'analytics.reports.export',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertStringNotContainsString('Closure', $route->getActionName());
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('tenant', $route->gatherMiddleware());
        }
    }

    public function test_date_range_limit_and_cross_company_filters_fail_safely(): void
    {
        $context = $this->analyticsContext();
        $this->get(route('analytics.reports.show', [
            'sales', 'date_from' => '2030-01-01', 'date_to' => '2040-01-01',
        ]))->assertSessionHasErrors('date_to');
        $this->get(route('analytics.reports.show', [
            'sales', 'company_id' => $context['company']->id,
        ]))->assertSessionHasErrors('company_id');
    }

    public function test_every_report_executes_with_company_and_branch_scope(): void
    {
        $context = $this->analyticsContext();

        foreach ([
            'financial', 'sales', 'receivables', 'purchases', 'payables',
            'inventory', 'treasury', 'employee-finance', 'approvals', 'audit',
        ] as $report) {
            $this->get(route('analytics.reports.show', [
                $report,
                'branch_id' => $context['branch']->id,
                'date_from' => '2040-01-01',
                'date_to' => '2040-01-31',
            ]))->assertOk();
        }
    }
}
