<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchResponsibleUserService;
use Database\Seeders\FoundationPermissionSeeder;
use Database\Seeders\ThreeRoleOperatingModelSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ThreeRoleBranchOperatingModelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_branch_accepts_one_active_responsible_user_and_user_cannot_manage_two_branches(): void
    {
        [$owner, $company, $first, $second] = $this->context();
        $operator = $this->operator($company, 'operator@example.test');
        app(TenantContext::class)->initialize($owner);

        app(BranchResponsibleUserService::class)->assign($first, $operator);

        $this->assertSame($operator->id, $first->fresh()->responsible_user_id);
        $this->assertSame($first->id, $operator->fresh()->branch_id);
        $this->assertSame([$first->id], $operator->accessibleBranches()->pluck('branches.id')->all());

        $this->expectException(ValidationException::class);
        app(BranchResponsibleUserService::class)->assign($second, $operator);
    }

    public function test_changing_responsible_revokes_previous_operational_branch_access_without_deleting_user(): void
    {
        [$owner, $company, $branch] = $this->context();
        $first = $this->operator($company, 'first@example.test');
        $second = $this->operator($company, 'second@example.test');
        app(TenantContext::class)->initialize($owner);
        $service = app(BranchResponsibleUserService::class);

        $service->assign($branch, $first);
        $service->assign($branch, $second);

        $this->assertDatabaseHas('users', ['id' => $first->id, 'branch_id' => null]);
        $this->assertDatabaseMissing('user_branch_access', ['user_id' => $first->id, 'branch_id' => $branch->id]);
        $this->assertSame($second->id, $branch->fresh()->responsible_user_id);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Branch::class,
            'auditable_id' => $branch->id,
            'event' => 'branch.responsible_user_assigned',
        ]);
    }

    public function test_branch_responsible_has_one_branch_and_cannot_switch_to_another(): void
    {
        [$owner, $company, $branch, $other] = $this->context();
        $operator = $this->operator($company, 'scope@example.test');
        app(TenantContext::class)->initialize($owner);
        app(BranchResponsibleUserService::class)->assign($branch, $operator);
        app(TenantContext::class)->initialize($operator->fresh());

        $this->assertSame(
            [$branch->id],
            app(TenantContext::class)->accessibleBranches()->pluck('id')->all(),
        );

        $this->actingAs($operator)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('name="branch_id"', false);
        $this->actingAs($operator)->post(route('branch-context.store'), ['branch_id' => $other->id])
            ->assertForbidden();
    }

    public function test_three_role_reconciler_keeps_operational_and_accounting_duties_separated(): void
    {
        foreach ([
            'sales_invoices.direct_sale', 'treasury.cash_sessions.open', 'accounting.journals.view',
            'users.create', 'branches.assign_responsible',
        ] as $name) {
            Permission::query()->firstOrCreate(['name' => $name], ['display_name' => $name]);
        }
        app(FoundationPermissionSeeder::class)->run();
        app(ThreeRoleOperatingModelSeeder::class)->run();

        $branchManager = Role::query()->whereNull('company_id')->where('name', 'branch_manager')->firstOrFail();
        $accountant = Role::query()->whereNull('company_id')->where('name', 'accountant')->firstOrFail();

        $this->assertSame('مسؤول الفرع', $branchManager->display_name);
        $this->assertSame('company', $accountant->scope);
        $this->assertTrue($branchManager->permissions()->where('name', 'sales_invoices.direct_sale')->exists());
        $this->assertFalse($branchManager->permissions()->where('name', 'accounting.journals.view')->exists());
        $this->assertTrue($accountant->permissions()->where('name', 'accounting.journals.view')->exists());
        $this->assertFalse($accountant->permissions()->where('name', 'users.create')->exists());
    }

    private function context(): array
    {
        app(FoundationPermissionSeeder::class)->run();
        app(ThreeRoleOperatingModelSeeder::class)->run();
        $company = Company::query()->create(['name' => 'Three Roles '.uniqid(), 'is_active' => true]);
        $first = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'A'.uniqid(), 'name' => 'فرع أول', 'is_main' => true,
        ]);
        $second = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'B'.uniqid(), 'name' => 'فرع ثان',
        ]);
        $owner = User::factory()->create([
            'company_id' => $company->id, 'branch_id' => $first->id, 'status' => 'active',
        ]);
        $owner->roles()->attach(Role::query()->whereNull('company_id')->where('name', 'company_owner')->firstOrFail());
        $owner->accessibleBranches()->attach($first, ['is_default' => true, 'can_view' => true]);

        return [$owner, $company, $first, $second];
    }

    private function operator(Company $company, string $email): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id, 'branch_id' => null, 'email' => $email, 'status' => 'active',
        ]);
        $user->roles()->attach(Role::query()->whereNull('company_id')->where('name', 'branch_manager')->firstOrFail());

        return $user;
    }
}
