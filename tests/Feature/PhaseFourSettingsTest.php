<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\VehicleBrand;
use App\Services\DocumentNumberService;
use App\Services\FiscalYearService;
use App\Services\TaxService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseFourSettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reference_pages_only_show_current_company_and_system_records(): void
    {
        [$user, , $company] = $this->tenantUser(['taxes.view', 'units.view']);
        $other = Company::query()->create(['name' => 'Other '.uniqid()]);
        Tax::query()->forceCreate([
            'company_id' => $company->id, 'code' => 'OWN', 'name' => 'Own tax',
            'rate' => 15, 'tax_type' => 'sales', 'is_active' => true,
        ]);
        Tax::query()->forceCreate([
            'company_id' => $other->id, 'code' => 'OTHER', 'name' => 'Other tax',
            'rate' => 10, 'tax_type' => 'sales', 'is_active' => true,
        ]);
        Unit::query()->forceCreate([
            'company_id' => null, 'code' => 'system_'.uniqid(), 'name' => 'System unit',
            'symbol' => 'S', 'unit_type' => 'quantity', 'is_system' => true, 'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('reference.index', 'taxes'))
            ->assertOk()->assertSee('Own tax')->assertDontSee('Other tax');
        $this->actingAs($user)->get(route('reference.index', 'units'))
            ->assertOk()->assertSee('System unit');
    }

    public function test_company_user_cannot_mutate_system_reference_data(): void
    {
        [$user] = $this->tenantUser(['settings.view', 'settings.manage', 'vehicle_references.manage']);

        $this->actingAs($user)->get(route('reference.create', 'currencies'))->assertForbidden();
        $this->actingAs($user)->post(route('reference.store', 'vehicle-brands'), [
            'name_ar' => 'ماركة', 'is_active' => 1,
        ])->assertForbidden();
    }

    public function test_tax_service_keeps_one_default_per_type_and_company(): void
    {
        [, , $company] = $this->tenantUser([]);
        $service = app(TaxService::class);
        $first = $service->save(new Tax(), $company->id, $this->taxData('A', true));
        $second = $service->save(new Tax(), $company->id, $this->taxData('B', true));

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertSame(1, Tax::query()->where('company_id', $company->id)
            ->where('tax_type', 'sales')->where('is_default', true)->count());
    }

    public function test_overlapping_fiscal_year_is_rejected(): void
    {
        [$user, , $company] = $this->tenantUser([]);
        $service = app(FiscalYearService::class);
        $service->save(new FiscalYear(), $company->id, $user, [
            'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'status' => 'open', 'is_current' => true,
        ]);

        $this->expectException(ValidationException::class);
        $service->save(new FiscalYear(), $company->id, $user, [
            'name' => 'Overlap', 'start_date' => '2026-12-01', 'end_date' => '2027-11-30',
            'status' => 'open', 'is_current' => false,
        ]);
    }

    public function test_branch_settings_reject_other_company_defaults(): void
    {
        [$user] = $this->tenantUser(['branch_settings.manage']);
        $other = Company::query()->create(['name' => 'Other '.uniqid()]);
        $tax = Tax::query()->forceCreate([
            'company_id' => $other->id, 'code' => 'X', 'name' => 'Foreign',
            'rate' => 5, 'tax_type' => 'sales', 'is_active' => true,
        ]);

        $this->actingAs($user)->put(route('branch-settings.update'), [
            'default_tax_id' => $tax->id, 'maximum_discount_percentage' => 10,
        ])->assertSessionHasErrors('default_tax_id');
    }

    public function test_document_numbers_increment_and_reset_by_period(): void
    {
        [$user, $branch, $company] = $this->tenantUser([]);
        app(TenantContext::class)->initialize($user);
        DocumentSequence::query()->forceCreate([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'document_type' => 'quotation',
            'prefix' => '{BRANCH}-{YYYY}-',
            'current_number' => 0,
            'padding' => 4,
            'reset_period' => 'yearly',
            'period_key' => '2026',
            'scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, 'quotation', '2026'),
            'is_active' => true,
        ]);
        $service = app(DocumentNumberService::class);

        $this->assertSame('MAIN-2026-0001', $service->next('quotation', $company->id, $branch->id, '2026-02-01'));
        $this->assertSame('MAIN-2026-0002', $service->next('quotation', $company->id, $branch->id, '2026-03-01'));
        $this->assertSame('MAIN-2027-0001', $service->next('quotation', $company->id, $branch->id, '2027-01-01'));
    }

    public function test_document_number_service_rejects_cross_tenant_company(): void
    {
        [$user] = $this->tenantUser([]);
        $other = Company::query()->create(['name' => 'Other '.uniqid()]);
        app(TenantContext::class)->initialize($user);

        $this->expectException(ValidationException::class);
        app(DocumentNumberService::class)->next('quotation', $other->id);
    }

    public function test_monthly_company_and_branch_sequences_are_independent(): void
    {
        [$user, $branch, $company] = $this->tenantUser([]);
        app(TenantContext::class)->initialize($user);
        foreach ([null, $branch->id] as $branchId) {
            DocumentSequence::query()->forceCreate([
                'company_id' => $company->id, 'branch_id' => $branchId,
                'document_type' => 'receipt_voucher',
                'prefix' => ($branchId ? '{BRANCH}' : 'COMPANY').'-{MM}-',
                'current_number' => 0, 'padding' => 2, 'reset_period' => 'monthly',
                'period_key' => '202601',
                'scope_key' => DocumentNumberService::scopeKey(
                    $company->id,
                    $branchId,
                    'receipt_voucher',
                    '202601'
                ),
                'is_active' => true,
            ]);
        }
        $service = app(DocumentNumberService::class);

        $this->assertSame('COMPANY-01-01', $service->next('receipt_voucher', $company->id, null, '2026-01-10'));
        $this->assertSame('MAIN-01-01', $service->next('receipt_voucher', $company->id, $branch->id, '2026-01-10'));
        $this->assertSame('COMPANY-02-01', $service->next('receipt_voucher', $company->id, null, '2026-02-10'));
    }

    public function test_inactive_currency_cannot_be_selected_as_company_default(): void
    {
        [$user, , $company] = $this->tenantUser(['companies.update']);
        $currency = Currency::query()->create([
            'code' => 'ZZZ', 'name_ar' => 'غير نشطة', 'name_en' => 'Inactive',
            'symbol' => 'Z', 'decimal_places' => 2, 'is_active' => false,
        ]);

        $this->actingAs($user)->put(route('company.update', $company), [
            'name' => $company->name,
            'country_code' => 'SA',
            'currency_id' => $currency->id,
            'timezone' => 'Africa/Cairo',
            'fiscal_year_start_month' => 1,
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'money_decimal_places' => 2,
            'default_language' => 'ar',
            'ui_direction' => 'rtl',
            'is_active' => 1,
        ])->assertSessionHasErrors('currency_id');
    }

    public function test_company_can_create_its_own_unit_payment_method_and_valid_tax(): void
    {
        [$user, , $company] = $this->tenantUser([
            'units.manage', 'payment_methods.manage', 'taxes.manage',
        ]);

        $this->actingAs($user)->post(route('reference.store', 'units'), [
            'code' => 'custom', 'name' => 'Custom unit', 'symbol' => 'CU',
            'unit_type' => 'quantity', 'decimal_places' => 0, 'is_active' => 1,
        ])->assertRedirect(route('reference.index', 'units'));
        $this->actingAs($user)->post(route('reference.store', 'payment-methods'), [
            'code' => 'custom_pay', 'name' => 'Custom payment', 'type' => 'other',
            'sort_order' => 10, 'is_active' => 1,
        ])->assertRedirect(route('reference.index', 'payment-methods'));
        $this->actingAs($user)->post(route('reference.store', 'taxes'), [
            'code' => 'BAD', 'name' => 'Bad rate', 'rate' => 101,
            'tax_type' => 'sales', 'is_active' => 1,
        ])->assertSessionHasErrors('rate');

        $this->assertDatabaseHas('units', ['company_id' => $company->id, 'code' => 'custom']);
        $this->assertDatabaseHas('payment_methods', ['company_id' => $company->id, 'code' => 'custom_pay']);
    }

    public function test_vehicle_model_years_are_validated_and_linked_to_brand(): void
    {
        [$user] = $this->tenantUser([]);
        $this->makeSystemAdmin($user);
        $brand = VehicleBrand::query()->create([
            'name_ar' => 'ماركة', 'name_en' => 'Brand', 'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('reference.store', 'vehicle-models'), [
            'vehicle_brand_id' => $brand->id, 'name_ar' => 'موديل',
            'start_year' => 2027, 'end_year' => 2026, 'is_active' => 1,
        ])->assertSessionHasErrors('end_year');
        $this->actingAs($user)->post(route('reference.store', 'vehicle-models'), [
            'vehicle_brand_id' => $brand->id, 'name_ar' => 'موديل صحيح',
            'start_year' => 2026, 'end_year' => 2027, 'is_active' => 1,
        ])->assertRedirect(route('reference.index', 'vehicle-models'));

        $this->assertDatabaseHas('vehicle_models', [
            'vehicle_brand_id' => $brand->id, 'name_ar' => 'موديل صحيح',
        ]);
    }

    public function test_fiscal_year_service_creates_new_years_as_draft(): void
    {
        [$user, , $company] = $this->tenantUser([]);
        $service = app(FiscalYearService::class);
        $first = $service->save(new FiscalYear(), $company->id, $user, [
            'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'status' => 'open', 'is_current' => true,
        ]);
        $second = $service->save(new FiscalYear(), $company->id, $user, [
            'name' => '2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
            'status' => 'open', 'is_current' => true,
        ]);

        $this->assertSame('draft', $first->fresh()->status);
        $this->assertFalse((bool) $first->fresh()->is_current);
        $this->assertSame('draft', $second->fresh()->status);
        $this->assertFalse((bool) $second->fresh()->is_current);
    }

    public function test_outer_transaction_rollback_does_not_consume_document_number(): void
    {
        [$user, $branch, $company] = $this->tenantUser([]);
        app(TenantContext::class)->initialize($user);
        $sequence = DocumentSequence::query()->forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'document_type' => 'expense', 'prefix' => 'EXP-', 'current_number' => 0,
            'padding' => 3, 'reset_period' => 'never', 'period_key' => null,
            'scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, 'expense', null),
            'is_active' => true,
        ]);

        DB::statement('SAVEPOINT phase_four_number');
        app(DocumentNumberService::class)->next('expense', $company->id, $branch->id);
        DB::statement('ROLLBACK TO SAVEPOINT phase_four_number');

        $this->assertSame(0, $sequence->fresh()->current_number);
    }

    public function test_permissions_hide_settings_and_reject_direct_access(): void
    {
        [$user] = $this->tenantUser(['dashboard.view']);

        $this->actingAs($user)->get(route('reference.index', 'taxes'))
            ->assertForbidden();
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('الضرائب');
    }

    private function tenantUser(array $permissionNames): array
    {
        $company = Company::query()->create(['name' => 'Company '.uniqid()]);
        $branch = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main',
            'is_main' => true, 'is_active' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active',
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id, 'name' => 'company_owner',
            'display_name' => 'Owner', 'scope' => 'company', 'is_active' => true,
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

    private function taxData(string $code, bool $default): array
    {
        return [
            'code' => $code, 'name' => $code, 'rate' => 15, 'tax_type' => 'sales',
            'is_default' => $default, 'is_inclusive' => false, 'is_active' => true,
        ];
    }

    private function makeSystemAdmin(User $user): void
    {
        $role = Role::query()->firstOrCreate(
            ['company_id' => null, 'name' => 'system_admin'],
            ['display_name' => 'System Admin', 'scope' => 'system', 'is_active' => true]
        );
        $user->roles()->attach($role);
    }
}
