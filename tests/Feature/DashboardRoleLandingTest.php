<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesInvoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsAnalyticsContext;
use Tests\TestCase;

class DashboardRoleLandingTest extends TestCase
{
    use BuildsAnalyticsContext;
    use DatabaseTransactions;

    public function test_dashboard_routes_each_role_to_its_real_landing_page(): void
    {
        $context = $this->analyticsContext();
        $context['user']->forceFill(['password' => Hash::make('Dashboard-Test-123!')])->save();
        Auth::logout();
        $this->post(route('login'), [
            'email' => $context['user']->email,
            'password' => 'Dashboard-Test-123!',
        ])->assertRedirect(route('dashboards.executive'));
        $this->get(route('dashboard'))->assertRedirect(route('dashboards.executive'));

        $accountant = $this->userForRole($context, 'accountant', ['accounting.accounts.view']);
        $this->switchTreasuryActor($accountant);
        $this->get(route('dashboard'))->assertRedirect(route('accounting.dashboard'));

        $manager = $this->userForRole($context, 'branch_manager', ['dashboard.view']);
        $this->switchTreasuryActor($manager);
        $this->get(route('dashboard'))->assertOk()->assertViewIs('dashboard.index');
    }

    public function test_branch_dashboard_metrics_are_operational_and_branch_scoped(): void
    {
        $context = $this->analyticsContext();
        $manager = $this->userForRole($context, 'branch_manager', ['dashboard.view']);
        $this->switchTreasuryActor($manager);
        SalesInvoice::factory()->create([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'customer_id' => $context['customer']->id,
            'currency_id' => $context['currency']->id,
            'status' => 'issued',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 100,
            'tax_amount' => 14,
            'total' => 114,
            'balance_due' => 114,
            'created_by' => $manager->id,
        ]);
        SalesInvoice::factory()->create([
            'company_id' => $context['company']->id,
            'branch_id' => $context['secondBranch']->id,
            'customer_id' => $context['customer']->id,
            'currency_id' => $context['currency']->id,
            'status' => 'issued',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 70,
            'total' => 570,
            'balance_due' => 570,
            'created_by' => $context['user']->id,
        ]);

        $this->get(route('dashboard'))->assertOk()->assertViewHas('dashboard', function (array $dashboard) {
            return $dashboard['metrics']['invoice_count'] === 1
                && bccomp($dashboard['metrics']['net_sales'], '114.0000', 4) === 0
                && bccomp($dashboard['metrics']['receivables'], '114.0000', 4) === 0;
        });
    }

    public function test_executive_dashboard_exposes_operational_and_branch_comparison_data(): void
    {
        $context = $this->analyticsContext();

        $this->get(route('dashboards.executive', [
            'date_from' => '2040-01-01',
            'date_to' => '2040-01-31',
        ]))->assertOk()->assertViewHas('dashboard', fn (array $dashboard) => array_key_exists('operational', $dashboard)
            && count($dashboard['branch_comparison']) === 2
        );
    }

    private function userForRole(array $context, string $name, array $permissions)
    {
        $role = Role::query()->create([
            'company_id' => $context['company']->id,
            'name' => $name,
            'display_name' => $name,
            'scope' => $name === 'branch_manager' ? 'branch' : 'company',
            'is_active' => true,
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('name', $permissions)->pluck('id'));

        return $this->treasuryUser($context['company'], $context['branch'], $role);
    }
}
