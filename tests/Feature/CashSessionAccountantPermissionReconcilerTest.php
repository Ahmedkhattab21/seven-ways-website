<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountantCashSessionPermissionReconciler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class CashSessionAccountantPermissionReconcilerTest extends TestCase
{
    use DatabaseTransactions;
    use BuildsTreasuryOperationsContext;

    public function test_company_accountant_role_is_reconciled_without_granting_operational_actions(): void
    {
        $company = Company::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Accountant Reconciler '.uniqid(),
            'country_code' => 'EG', 'currency_code' => 'EGP', 'timezone' => 'Africa/Cairo',
            'is_active' => true,
        ]);
        $systemRole = Role::query()->create([
            'company_id' => null, 'name' => 'accountant', 'display_name' => 'Accountant',
            'scope' => 'branch', 'is_system' => true, 'is_active' => true,
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id, 'name' => 'accountant', 'display_name' => 'Accountant',
            'scope' => 'company', 'is_system' => false, 'is_active' => true,
        ]);
        $user = User::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $user->roles()->attach($role);
        $permissions = collect([
            'treasury.cash_sessions.view', 'treasury.cash_sessions.review',
            'treasury.cash_sessions.open', 'treasury.cash_sessions.count',
            'treasury.cash_sessions.submit', 'treasury.cash_sessions.approve',
            'treasury.cash_sessions.close', 'treasury.cash_sessions.reopen',
            'treasury.cash_sessions.override_custodian',
        ])->mapWithKeys(fn (string $name) => [$name => Permission::query()->firstOrCreate(
            ['name' => $name], ['display_name' => $name]
        )]);
        $role->permissions()->sync($permissions->pluck('id'));

        app(AccountantCashSessionPermissionReconciler::class)->reconcile();

        $this->assertTrue($user->fresh()->hasPermission('treasury.cash_sessions.view'));
        $this->assertTrue($user->fresh()->hasPermission('treasury.cash_sessions.review'));
        foreach (AccountantCashSessionPermissionReconciler::FORBIDDEN as $permission) {
            $this->assertFalse($user->fresh()->hasPermission($permission));
        }
        $this->assertTrue($systemRole->fresh()->permissions()->where('name', 'treasury.cash_sessions.view')->exists());
        $this->assertTrue($systemRole->fresh()->permissions()->where('name', 'treasury.cash_sessions.review')->exists());
        app(AccountantCashSessionPermissionReconciler::class)->reconcile();
        $this->assertSame(2, $role->fresh()->permissions()->whereIn('name', AccountantCashSessionPermissionReconciler::REQUIRED)->count());
    }

    public function test_company_accountant_can_open_cash_sessions_page_after_reconciliation(): void
    {
        $context = $this->treasuryContext();
        $role = Role::query()->create([
            'company_id' => $context['company']->id, 'name' => 'accountant', 'display_name' => 'Accountant',
            'scope' => 'company', 'is_system' => false, 'is_active' => true,
        ]);
        $accountant = User::factory()->create([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id, 'status' => 'active',
        ]);
        $accountant->roles()->attach($role);
        $accountant->accessibleBranches()->attach($context['branch'], [
            'is_default' => true, 'can_view' => true, 'can_create' => true,
        ]);
        app(AccountantCashSessionPermissionReconciler::class)->reconcile();
        $this->actingAs($accountant);
        app(TenantContext::class)->initialize($accountant);

        $this->get('/treasury/cash-sessions')->assertOk();
    }
}
