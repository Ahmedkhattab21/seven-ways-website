<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\BootstrapAccessSeeder;
use Database\Seeders\ProductionReferenceSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapAccessSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProductionReferenceSeeder::class)->run();
    }

    public function test_bootstrap_seeder_creates_minimal_access_and_is_idempotent(): void
    {
        app(BootstrapAccessSeeder::class)->run();
        app(BootstrapAccessSeeder::class)->run();

        $this->prepareNoBranchOwner();

        $company = Company::query()->where('name', 'Seven Ways')->firstOrFail();
        $admin = User::query()->where('email', 'system.admin@sevenways.test')->firstOrFail();
        $owner = User::query()->where('email', 'owner@sevenways.test')->firstOrFail();

        $this->assertSame('EG', $company->country_code);
        $this->assertSame('EGP', $company->currency_code);
        $this->assertSame('Africa/Cairo', $company->timezone);
        $this->assertSame('ar', $company->default_language);
        $this->assertSame('rtl', $company->ui_direction);
        $this->assertSame(0, Branch::query()->where('company_id', $company->id)->where('is_active', true)->count());
        $this->assertSame(1, Company::query()->where('name', 'Seven Ways')->count());
        $this->assertSame(1, User::query()->where('email', 'system.admin@sevenways.test')->count());
        $this->assertSame(1, User::query()->where('email', 'owner@sevenways.test')->count());
        $this->assertNull($admin->company_id);
        $this->assertNull($admin->branch_id);
        $this->assertSame($company->id, $owner->company_id);
        $this->assertNull($owner->branch_id);
        $this->assertTrue($admin->hasRole('system_admin'));
        $this->assertTrue($owner->hasRole('company_owner'));
        $this->assertTrue(Hash::check('Test@123456', $admin->password));
        $this->assertTrue(Hash::check('Test@123456', $owner->password));
    }

    public function test_system_admin_and_owner_can_login_before_first_branch_and_owner_is_redirected_to_setup(): void
    {
        app(BootstrapAccessSeeder::class)->run();
        $this->prepareNoBranchOwner();
        $owner = User::query()->where('email', 'owner@sevenways.test')->firstOrFail();

        $this->post('/login', [
            'email' => 'system.admin@sevenways.test',
            'password' => 'Test@123456',
        ])->assertRedirect(route('dashboard'));
        $this->post('/logout');

        $this->post('/login', [
            'email' => 'owner@sevenways.test',
            'password' => 'Test@123456',
        ])->assertRedirect(route('branches.create'));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_first_branch_binds_owner_and_initializes_tenant_access(): void
    {
        app(BootstrapAccessSeeder::class)->run();
        $this->prepareNoBranchOwner();
        $owner = User::query()->where('email', 'owner@sevenways.test')->firstOrFail();
        $company = Company::query()->where('name', 'Seven Ways')->firstOrFail();

        $this->actingAs($owner)->post(route('branches.store'), [
            'code' => 'BOOTSTRAP-MAIN',
            'name' => 'Main Branch',
            'is_main' => true,
        ])->assertRedirect(route('branches.index'));

        $branch = Branch::query()->where('company_id', $company->id)->firstOrFail();
        $owner->refresh();
        $this->assertSame($branch->id, $owner->branch_id);
        $this->assertTrue($branch->is_main);
        $this->assertTrue($owner->accessibleBranches()->whereKey($branch->id)->wherePivot('is_default', true)->exists());
        $this->assertSame($branch->id, app(TenantContext::class)->initialize($owner)->branchId());
    }

    private function prepareNoBranchOwner(): void
    {
        $company = Company::query()->where('name', 'Seven Ways')->firstOrFail();
        Branch::query()->where('company_id', $company->id)->update(['is_active' => false]);
        User::query()->where('email', 'owner@sevenways.test')->update([
            'branch_id' => null,
            'password' => Hash::make('Test@123456'),
            'status' => 'active',
        ]);
    }
}
