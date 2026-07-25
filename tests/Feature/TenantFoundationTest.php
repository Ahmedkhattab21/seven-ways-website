<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_cannot_access_tenant_management_pages(): void
    {
        $this->get('/branches')->assertRedirect('/login');
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_tenant_context_only_exposes_authorized_company_branches(): void
    {
        [$user, $branch] = $this->tenantUser(['branches.view']);
        $otherCompany = Company::query()->create(['name' => 'Other '.uniqid()]);
        $otherBranch = Branch::query()->create([
            'company_id' => $otherCompany->id, 'code' => 'OTHER', 'name' => 'Other', 'is_main' => true,
        ]);

        $context = app(TenantContext::class)->initialize($user);

        $this->assertTrue($context->accessibleBranches()->contains($branch));
        $this->assertFalse($context->accessibleBranches()->contains($otherBranch));
    }

    public function test_user_cannot_switch_to_another_company_branch(): void
    {
        [$user] = $this->tenantUser(['dashboard.view']);
        $otherCompany = Company::query()->create(['name' => 'Other '.uniqid()]);
        $otherBranch = Branch::query()->create([
            'company_id' => $otherCompany->id, 'code' => 'OTHER', 'name' => 'Other', 'is_main' => true,
        ]);

        $this->actingAs($user)->post(route('branch-context.store'), ['branch_id' => $otherBranch->id])->assertForbidden();
    }

    public function test_authorized_user_can_open_company_branches_and_users_pages(): void
    {
        [$user, , $company] = $this->tenantUser(['dashboard.view', 'companies.view', 'branches.view', 'users.view']);
        $ownerRole = Role::query()->create([
            'company_id' => $company->id, 'name' => 'company_owner', 'display_name' => 'Owner',
            'scope' => 'company', 'is_active' => true,
        ]);
        $user->roles()->attach($ownerRole);

        $this->actingAs($user)->get(route('company.edit'))->assertOk();
        $this->actingAs($user)->get(route('branches.index'))->assertOk();
        $this->actingAs($user)->get(route('users.index'))->assertOk();
    }

    public function test_main_branch_cannot_be_disabled(): void
    {
        [$user, $branch] = $this->tenantUser(['branches.disable']);

        $this->actingAs($user)->patch(route('branches.disable', $branch))
            ->assertSessionHasErrors('branch');
        $this->assertTrue($branch->fresh()->is_active);
    }

    public function test_branch_manager_only_sees_authorized_branch(): void
    {
        [$user, $branch, $company] = $this->tenantUser(['branches.view']);
        $otherBranch = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'HIDDEN', 'name' => 'Hidden Branch',
        ]);

        $this->actingAs($user)->get(route('branches.index'))
            ->assertOk()->assertSee($branch->name)->assertDontSee($otherBranch->name);
    }

    public function test_authorized_user_can_switch_branch_and_session_is_verified(): void
    {
        [$user, , $company] = $this->tenantUser(['dashboard.view']);
        $branch = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'SECOND', 'name' => 'Second',
        ]);
        $user->accessibleBranches()->attach($branch, ['can_view' => true]);

        $this->actingAs($user)->post(route('branch-context.store'), ['branch_id' => $branch->id])
            ->assertRedirect()->assertSessionHas('tenant.branch_id', $branch->id);
    }

    public function test_duplicate_branch_code_is_rejected_within_company(): void
    {
        [$user, $branch] = $this->tenantUser(['branches.create']);

        $this->actingAs($user)->post(route('branches.store'), [
            'code' => $branch->code, 'name' => 'Duplicate', 'is_active' => 1,
        ])->assertSessionHasErrors('code');
    }

    public function test_user_without_permission_receives_forbidden(): void
    {
        [$user] = $this->tenantUser([]);

        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive+'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'status' => 'inactive',
        ]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_user_cannot_edit_own_management_roles(): void
    {
        [$user] = $this->tenantUser(['users.update']);

        $this->actingAs($user)->get(route('users.edit', $user))->assertForbidden();
    }

    private function tenantUser(array $permissionNames): array
    {
        $company = Company::query()->create(['name' => 'Company '.uniqid()]);
        $branch = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main', 'is_main' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active',
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id, 'name' => 'test_'.uniqid(), 'display_name' => 'Test',
            'scope' => 'company', 'is_active' => true,
        ]);
        $permissions = collect($permissionNames)->map(fn ($name) => Permission::query()->firstOrCreate(
            ['name' => $name],
            ['display_name' => $name]
        ));
        $role->permissions()->sync($permissions->pluck('id'));
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);

        return [$user, $branch, $company];
    }
}
