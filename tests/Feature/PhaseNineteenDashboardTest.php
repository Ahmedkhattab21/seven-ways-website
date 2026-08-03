<?php

namespace Tests\Feature;

use App\Analytics\ReportFilterData;
use App\Core\Tenancy\TenantContext;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ExecutiveDashboardService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsAnalyticsContext;
use Tests\TestCase;

class PhaseNineteenDashboardTest extends TestCase
{
    use BuildsAnalyticsContext;
    use DatabaseTransactions;

    public function test_executive_sales_kpi_uses_posted_snapshots_and_previous_comparison(): void
    {
        $context = $this->analyticsContext();
        $this->analyticsInvoice($context, '2040-01-10', '100', '10', '12.6', '102.6');
        $this->analyticsInvoice($context, '2039-12-10', '50', '0', '7', '57');
        $filters = ReportFilterData::from([
            'branch_id' => $context['branch']->id,
            'currency_id' => $context['currency']->id,
            'date_from' => '2040-01-01',
            'date_to' => '2040-01-31',
        ], app(TenantContext::class));
        $dashboard = app(ExecutiveDashboardService::class)->build($filters);

        $this->assertSame('90.0000', $dashboard['current']['sales']['net_sales_before_tax']);
        $this->assertSame('50.0000', $dashboard['previous']['sales']['net_sales_before_tax']);
        $this->assertSame(80.0, $dashboard['comparisons']['net_sales']['percentage']);
    }

    public function test_pending_and_unposted_sales_are_excluded_and_zero_comparison_is_na(): void
    {
        $context = $this->analyticsContext();
        $invoice = $this->analyticsInvoice($context, '2040-01-10', '100', '0', '14', '114');
        $invoice->accountingPostingLinks()->delete();
        $filters = ReportFilterData::from([
            'branch_id' => $context['branch']->id,
            'date_from' => '2040-01-01', 'date_to' => '2040-01-31',
        ], app(TenantContext::class));
        $dashboard = app(ExecutiveDashboardService::class)->build($filters);

        $this->assertSame('0.0000', $dashboard['current']['sales']['net_sales_before_tax']);
        $this->assertNull($dashboard['comparisons']['net_sales']['percentage']);
    }

    public function test_branch_dashboard_blocks_cross_branch_and_company_wide_journals(): void
    {
        $context = $this->analyticsContext();
        $branchRole = Role::query()->create([
            'company_id' => $context['company']->id,
            'name' => 'phase19_branch_manager',
            'display_name' => 'Branch manager',
            'scope' => 'branch',
            'is_active' => true,
        ]);
        $branchRole->permissions()->sync(Permission::whereIn('name', [
            'dashboard.view', 'dashboards.branch.view', 'reports.sales.view',
        ])->pluck('id'));
        $user = $this->treasuryUser($context['company'], $context['branch'], $branchRole);
        $this->switchTreasuryActor($user);

        $this->get(route('dashboards.branches', ['branch_id' => $context['secondBranch']->id]))
            ->assertForbidden();
        $this->get(route('dashboards.branches', [
            'branch_id' => $context['branch']->id,
            'date_from' => '2040-01-01',
            'date_to' => '2040-01-31',
        ]))->assertOk();
    }

    public function test_company_owner_keeps_executive_dashboard_access_without_optional_permission(): void
    {
        $context = $this->analyticsContext();
        $role = $context['user']->roles()->first();
        $role->permissions()->detach(Permission::where('name', 'dashboards.executive.view')->value('id'));
        $this->get(route('dashboards.executive'))->assertOk();
    }
}
