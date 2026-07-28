<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FiscalPeriodGenerationService;
use App\Services\FiscalYearService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FiscalYearLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_new_year_always_starts_draft_and_open_requires_period_coverage(): void
    {
        [$company, $user] = $this->context();
        $year = app(FiscalYearService::class)->save(new FiscalYear, $company->id, $user, [
            'code' => 'FY-LIFECYCLE', 'name' => 'Lifecycle', 'start_date' => '2038-01-01',
            'end_date' => '2038-12-31', 'status' => 'open', 'is_current' => true,
        ]);

        $this->assertSame('draft', $year->status);
        $this->assertFalse($year->is_current);
        $this->expectException(BusinessRuleException::class);
        app(FiscalYearService::class)->open($year, $user);
    }

    public function test_generation_then_open_is_the_only_valid_lifecycle(): void
    {
        [$company, $user] = $this->context();
        $year = app(FiscalYearService::class)->save(new FiscalYear, $company->id, $user, [
            'code' => 'FY-COVERAGE', 'name' => 'Coverage', 'start_date' => '2039-01-01', 'end_date' => '2039-12-31',
        ]);

        $this->assertCount(12, app(FiscalPeriodGenerationService::class)->monthly($year));
        $opened = app(FiscalYearService::class)->open($year->fresh(), $user);

        $this->assertSame('open', $opened->status);
        $this->assertTrue($opened->is_current);
        $this->assertSame(12, $opened->periods()->where('is_adjustment_period', false)->count());
        $this->assertCount(12, app(FiscalPeriodGenerationService::class)->monthly($opened));
    }

    public function test_reference_fiscal_year_routes_redirect_to_accounting_screen(): void
    {
        [$company, $user] = $this->context();
        $this->actingAs($user);
        app(TenantContext::class)->initialize($user);

        $this->get('/settings/reference/fiscal-years')->assertRedirect('/accounting/fiscal-years');
        $this->get('/settings/reference/fiscal-years/create')->assertRedirect('/accounting/fiscal-years');
    }

    private function context(): array
    {
        $company = Company::query()->create(['name' => 'Fiscal '.uniqid()]);
        $branch = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main', 'is_main' => true, 'is_active' => true,
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id, 'name' => 'company_owner', 'display_name' => 'Owner', 'scope' => 'company', 'is_active' => true,
        ]);
        $permissionNames = [
            'accounting.fiscal_years.view', 'accounting.fiscal_years.create', 'accounting.fiscal_years.open',
        ];
        $permissions = collect($permissionNames)->map(fn (string $name) => Permission::query()->firstOrCreate(
            ['name' => $name], ['display_name' => $name]
        )->id);
        $role->permissions()->syncWithoutDetaching($permissions);
        $user = User::factory()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);
        app(TenantContext::class)->initialize($user);

        return [$company, $user];
    }
}
