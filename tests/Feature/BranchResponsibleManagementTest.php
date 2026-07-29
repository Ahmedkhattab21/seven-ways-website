<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanySetupProgressService;
use Database\Seeders\FoundationPermissionSeeder;
use Database\Seeders\ThreeRoleOperatingModelSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BranchResponsibleManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_create_keeps_optional_account_and_edit_lists_only_eligible_managers(): void
    {
        [$owner, $company, $alexandria, $other] = $this->context();
        $alex = $this->manager($company, $alexandria, 'alex.manager@sevenways.test');
        $inactive = $this->manager($company, $alexandria, 'inactive.manager@example.test', 'inactive');
        $withoutRole = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $alexandria->id,
            'status' => 'active',
        ]);
        $withoutRole->accessibleBranches()->attach($alexandria, ['is_default' => true, 'can_view' => true]);
        $assignedElsewhere = $this->manager($company, $other, 'assigned.manager@example.test');
        $other->forceFill([
            'responsible_user_id' => $assignedElsewhere->id,
            'responsible_assigned_at' => now(),
        ])->save();

        $this->actingAs($owner)->get(route('branches.create'))
            ->assertOk()
            ->assertSee('إنشاء حساب مسؤول الفرع (اختياري)');

        $this->actingAs($owner)->get(route('branches.edit', $alexandria))
            ->assertOk()
            ->assertSee('مسؤول تشغيل الفرع')
            ->assertSee('اختيار مسؤول تشغيل الفرع')
            ->assertSee('تعيين كمسؤول الفرع')
            ->assertSee(route('branches.responsible-user.update', $alexandria), false)
            ->assertSee($alex->email)
            ->assertDontSee($inactive->email)
            ->assertDontSee($withoutRole->email)
            ->assertDontSee($assignedElsewhere->email)
            ->assertDontSee('name="responsible_password"', false)
            ->assertSee('إنشاء حساب مسؤول جديد');
    }

    public function test_owner_can_assign_change_and_remove_with_audit_and_setup_becomes_incomplete(): void
    {
        [$owner, $company, $branch] = $this->context();
        $first = $this->manager($company, $branch, 'first.manager@example.test');
        $second = $this->manager($company, $branch, 'second.manager@example.test');
        $first->forceFill(['branch_id' => null])->save();

        $this->actingAs($owner)->put(route('branches.responsible-user.update', $branch), [
            'responsible_user_id' => $first->id,
        ])->assertRedirect();
        $this->assertSame($first->id, $branch->fresh()->responsible_user_id);
        $this->assertSame($branch->id, $first->fresh()->branch_id);

        $this->actingAs($owner)->put(route('branches.responsible-user.update', $branch), [
            'responsible_user_id' => $second->id,
        ])->assertRedirect();
        $this->assertSame($second->id, $branch->fresh()->responsible_user_id);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Branch::class,
            'auditable_id' => $branch->id,
            'event' => 'branch.responsible_user_assigned',
        ]);

        $this->actingAs($owner)->delete(route('branches.responsible-user.destroy', $branch), [
            'reason' => 'تغيير الهيكل التشغيلي للفرع',
        ])->assertRedirect();

        $this->assertNull($branch->fresh()->responsible_user_id);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Branch::class,
            'auditable_id' => $branch->id,
            'event' => 'branch.responsible_user_removed',
        ]);
        $rolesStep = collect(app(CompanySetupProgressService::class)->for($company)['steps'])
            ->firstWhere('label', 'المستخدمون والأدوار');
        $this->assertFalse($rolesStep['complete']);
    }

    public function test_branch_manager_and_accountant_cannot_assign_even_if_permission_is_attached(): void
    {
        [$owner, $company, $branch] = $this->context();
        $candidate = $this->manager($company, $branch, 'candidate.manager@example.test');
        $permission = \App\Models\Permission::query()->where('name', 'branches.assign_responsible')->firstOrFail();

        foreach (['branch_manager', 'accountant'] as $roleName) {
            $actor = User::factory()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'status' => 'active',
            ]);
            $role = Role::query()->whereNull('company_id')->where('name', $roleName)->firstOrFail();
            $role->permissions()->syncWithoutDetaching($permission);
            $actor->roles()->attach($role);
            $actor->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);

            $this->actingAs($actor)->put(route('branches.responsible-user.update', $branch), [
                'responsible_user_id' => $candidate->id,
            ])->assertForbidden();
        }

        $this->assertNull($branch->fresh()->responsible_user_id);
        $this->assertNotNull($owner);
    }

    public function test_prefilled_user_creation_assigns_the_new_manager_atomically(): void
    {
        [$owner, $company, $branch] = $this->context();
        $role = Role::query()->whereNull('company_id')->where('name', 'branch_manager')->firstOrFail();

        $this->actingAs($owner)->get(route('users.create', [
            'branch_id' => $branch->id,
            'role' => 'branch_manager',
            'return_url' => route('branches.edit', $branch),
            'assign_as_responsible' => 1,
        ]))->assertOk()
            ->assertSee('assign_as_responsible', false)
            ->assertSee('responsible_branch_id', false);

        $this->actingAs($owner)->post(route('users.store'), [
            'name' => 'مسؤول الإسكندرية الجديد',
            'email' => 'new.alex.manager@example.test',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'status' => 'active',
            'branch_id' => $branch->id,
            'branch_ids' => [$branch->id],
            'role_ids' => [$role->id],
            'assign_as_responsible' => 1,
            'responsible_branch_id' => $branch->id,
            'return_url' => route('branches.edit', $branch),
        ])->assertRedirect(route('branches.edit', $branch));

        $created = User::query()->where('email', 'new.alex.manager@example.test')->firstOrFail();
        $this->assertSame($created->id, $branch->fresh()->responsible_user_id);
    }

    private function context(): array
    {
        app(FoundationPermissionSeeder::class)->run();
        app(ThreeRoleOperatingModelSeeder::class)->run();
        $company = Company::query()->create(['name' => 'Responsible '.uniqid(), 'is_active' => true]);
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'ALX'.uniqid(),
            'name' => 'فرع الإسكندرية',
            'is_main' => true,
            'is_active' => true,
        ]);
        $other = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'CAI'.uniqid(),
            'name' => 'فرع القاهرة',
            'is_active' => true,
        ]);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $owner->roles()->attach(Role::query()->whereNull('company_id')->where('name', 'company_owner')->firstOrFail());
        $owner->roles()->firstOrFail()->permissions()->detach(
            \App\Models\Permission::query()->where('name', 'branches.assign_responsible')->value('id')
        );
        $owner->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);
        $owner->accessibleBranches()->attach($other, ['is_default' => false, 'can_view' => true]);

        return [$owner, $company, $branch, $other];
    }

    private function manager(Company $company, Branch $branch, string $email, string $status = 'active'): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'email' => $email,
            'status' => $status,
        ]);
        $user->roles()->attach(Role::query()->whereNull('company_id')->where('name', 'branch_manager')->firstOrFail());
        $user->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);

        return $user;
    }
}
