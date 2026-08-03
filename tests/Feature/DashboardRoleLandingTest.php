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
        $context['user']->roles()->firstOrFail()->permissions()->syncWithoutDetaching(
            $this->permissionIds(['dashboard.view'])
        );
        $context['user']->forceFill(['password' => Hash::make('Dashboard-Test-123!')])->save();
        Auth::logout();
        $this->post(route('login'), [
            'email' => $context['user']->email,
            'password' => 'Dashboard-Test-123!',
        ])->assertRedirect(route('dashboards.executive'));
        $this->get(route('dashboard'))->assertOk();

        $accountant = $this->userForRole($context, 'accountant', ['accounting.accounts.view', 'dashboard.view']);
        $accountant->forceFill(['password' => Hash::make('Dashboard-Test-123!')])->save();
        $this->switchTreasuryActor($accountant);
        Auth::logout();
        $this->post(route('login'), [
            'email' => $accountant->email,
            'password' => 'Dashboard-Test-123!',
        ])->assertRedirect(route('accounting.dashboard'));
        $this->get(route('dashboard'))->assertOk();

        $manager = $this->userForRole($context, 'branch_manager', ['dashboard.view']);
        $manager->forceFill(['password' => Hash::make('Dashboard-Test-123!')])->save();
        $this->switchTreasuryActor($manager);
        Auth::logout();
        $this->post(route('login'), [
            'email' => $manager->email,
            'password' => 'Dashboard-Test-123!',
        ])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertViewIs('dashboard.index');
    }

    public function test_executive_dashboard_uses_the_central_role_authorization(): void
    {
        $context = $this->analyticsContext();

        $this->get(route('dashboards.executive'))->assertOk();

        $manager = $this->userForRole($context, 'branch_manager', ['dashboard.view']);
        $this->switchTreasuryActor($manager);
        $this->get(route('dashboards.executive'))->assertForbidden();

        $accountant = $this->userForRole($context, 'accountant', ['accounting.accounts.view']);
        $this->switchTreasuryActor($accountant);
        $this->get(route('dashboards.executive'))->assertForbidden();

        $accountant->roles()->firstOrFail()->permissions()->syncWithoutDetaching(
            Permission::query()->where('name', 'dashboards.executive.view')->pluck('id')
        );
        $this->get(route('dashboards.executive'))->assertOk();

        $systemAdmin = $this->userForRole($context, 'system_admin', []);
        $this->switchTreasuryActor($systemAdmin);
        $this->get(route('dashboards.executive'))->assertOk();
    }

    public function test_login_preserves_only_an_authorized_internal_intended_dashboard(): void
    {
        $context = $this->analyticsContext();
        $context['user']->roles()->firstOrFail()->permissions()->syncWithoutDetaching(
            $this->permissionIds(['dashboard.view'])
        );
        $context['user']->forceFill(['password' => Hash::make('Dashboard-Test-123!')])->save();
        Auth::logout();

        $this->withSession(['url.intended' => route('dashboard')])->post(route('login'), [
            'email' => $context['user']->email,
            'password' => 'Dashboard-Test-123!',
        ])->assertRedirect(route('dashboard'));

        $this->post(route('logout'));
        $this->withSession(['url.intended' => 'https://example.com/login'])->post(route('login'), [
            'email' => $context['user']->email,
            'password' => 'Dashboard-Test-123!',
        ])->assertRedirect(route('dashboards.executive'));
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
        $role->permissions()->sync($this->permissionIds($permissions));

        return $this->treasuryUser($context['company'], $context['branch'], $role);
    }

    private function permissionIds(array $permissions)
    {
        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission],
                ['display_name' => $permission]
            );
        }

        return Permission::query()->whereIn('name', $permissions)->pluck('id');
    }
}
