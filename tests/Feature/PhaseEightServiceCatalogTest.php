<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceCommissionRule;
use App\Models\StockBalance;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\VehicleSize;
use App\Models\Warehouse;
use App\Services\BranchServiceAvailabilityService;
use App\Services\DocumentNumberService;
use App\Services\PromotionResolver;
use App\Services\ServiceCatalogService;
use App\Services\ServiceCommissionRuleResolver;
use App\Services\ServiceMaterialEstimator;
use App\Services\ServiceMaterialRequirementService;
use App\Services\ServicePackageService;
use App\Services\ServicePricingService;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PhaseEightServiceCatalogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_catalog_is_tenant_owned_and_prevents_category_cycles(): void
    {
        $context = $this->context();
        $catalog = app(ServiceCatalogService::class);
        $root = $catalog->saveCategory(['code' => 'ROOT', 'name' => 'Root', 'sort_order' => 0, 'is_active' => true]);
        $child = $catalog->saveCategory(['parent_id' => $root->id, 'code' => 'CHILD', 'name' => 'Child', 'sort_order' => 0, 'is_active' => true]);

        $this->expectException(BusinessRuleException::class);
        $catalog->saveCategory(['parent_id' => $child->id, 'code' => 'ROOT', 'name' => 'Root', 'sort_order' => 0, 'is_active' => true], $root);
    }

    public function test_service_rejects_cross_company_category_and_tax(): void
    {
        $context = $this->context();
        $other = Company::query()->create(['name' => 'Other '.uniqid(), 'is_active' => true]);
        $category = ServiceCategory::query()->forceCreate([
            'company_id' => $other->id, 'code' => 'OTHER', 'name' => 'Other', 'sort_order' => 0, 'is_active' => true,
        ]);

        $this->expectException(ModelNotFoundException::class);
        app(ServiceCatalogService::class)->saveService($this->serviceData($category));
    }

    public function test_branch_availability_is_scoped_and_inactive_branch_cannot_enable_service(): void
    {
        $context = $this->context();
        $context['branch']->forceFill(['is_active' => false])->save();

        $this->expectException(BusinessRuleException::class);
        app(BranchServiceAvailabilityService::class)->save($context['service'], $context['branch'], [
            'is_available' => true, 'booking_enabled' => false, 'requires_approval' => false,
            'default_price' => '100', 'minimum_price' => '80', 'maximum_discount_percentage' => '20', 'is_active' => true,
        ]);
    }

    public function test_pricing_prefers_specific_scope_calculates_tax_quantity_and_blocks_overlap(): void
    {
        $context = $this->context();
        $pricing = app(ServicePricingService::class);
        BranchService::query()->forceCreate([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'service_id' => $context['service']->id, 'is_available' => true, 'booking_enabled' => false,
            'requires_approval' => false, 'default_price' => '90', 'minimum_price' => '75', 'is_active' => true,
        ]);
        $size = VehicleSize::query()->forceCreate([
            'company_id' => $context['company']->id, 'code' => 'LARGE'.uniqid(), 'name' => 'Large',
            'sort_order' => 1, 'is_system' => false, 'is_active' => true,
        ]);
        $base = [
            'price' => '100', 'minimum_price' => '80', 'effective_from' => '2026-01-01',
            'effective_to' => null, 'priority' => 99, 'is_active' => true,
        ];
        $pricing->save($context['service'], $context['branch'], $base);
        $pricing->save($context['service'], $context['branch'], array_merge($base, [
            'vehicle_size_id' => $size->id, 'price' => '150', 'priority' => 0,
        ]));

        $resolved = $pricing->resolvePrice($context['service']->fresh('defaultTax'), $context['branch'], $size, null, 2, '2026-07-25');
        $this->assertSame('150.0000', $resolved['unit_price']);
        $this->assertSame('300.0000', $resolved['subtotal']);
        $this->assertSame('45.0000', $resolved['tax_amount']);
        $this->assertSame('345.0000', $resolved['total']);

        $this->expectException(BusinessRuleException::class);
        $pricing->save($context['service'], $context['branch'], $base);
    }

    public function test_material_estimation_uses_weighted_cost_and_never_changes_stock(): void
    {
        $context = $this->context();
        $product = $this->product($context, 'quantity');
        $substitute = $this->product($context, 'quantity');
        $warehouse = Warehouse::query()->forceCreate([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'code' => 'WH'.uniqid(), 'name' => 'Warehouse', 'warehouse_type' => 'main',
            'is_main' => true, 'is_active' => true, 'is_system' => false,
        ]);
        StockBalance::query()->forceCreate([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'warehouse_id' => $warehouse->id, 'product_id' => $product->id,
            'quantity' => '20', 'reserved_quantity' => '0', 'available_quantity' => '20', 'average_cost' => '10',
        ]);
        $materials = app(ServiceMaterialRequirementService::class);
        $requirement = $materials->save($context['service'], [
            'product_id' => $product->id, 'unit_id' => $context['unit']->id, 'requirement_type' => 'consumable',
            'expected_quantity' => '2', 'expected_waste_percentage' => '10',
            'is_required' => true, 'allow_substitution' => true, 'sort_order' => 0,
        ]);
        $savedSubstitute = $materials->saveSubstitute($requirement, $substitute, '1.25', 1);
        $before = StockBalance::query()->where('product_id', $product->id)->firstOrFail()->quantity;
        $estimate = app(ServiceMaterialEstimator::class)->estimate($context['service'], null, null, 2);

        $this->assertSame('4.000000', $estimate['materials'][0]['expected_quantity']);
        $this->assertSame('0.400000', $estimate['materials'][0]['expected_waste']);
        $this->assertSame('44.0000', $estimate['estimated_material_cost']);
        $this->assertSame($substitute->id, $savedSubstitute->substitute_product_id);
        $this->assertSame('1.250000', $savedSubstitute->conversion_factor);
        $this->assertSame($before, StockBalance::query()->where('product_id', $product->id)->first()->quantity);
        $this->assertFalse($estimate['stock_effect']);
    }

    public function test_roll_profile_estimates_area_without_selecting_an_inventory_roll(): void
    {
        $context = $this->context();
        $film = $this->product($context, 'roll');
        $before = \App\Models\InventoryRoll::query()->count();
        $context['service']->rollProfiles()->create([
            'film_product_id' => $film->id, 'coverage_type' => 'hood',
            'expected_area' => '3', 'expected_waste_percentage' => '10',
        ]);
        $estimate = app(ServiceMaterialEstimator::class)->estimate($context['service'], null, null, 2);

        $this->assertSame('6.000000', $estimate['roll_profiles'][0]['expected_area']);
        $this->assertSame('0.600000', $estimate['roll_profiles'][0]['expected_waste_area']);
        $this->assertSame($before, \App\Models\InventoryRoll::query()->count());
    }

    public function test_commission_resolver_returns_most_specific_rule_without_financial_posting(): void
    {
        $context = $this->context();
        $employee = Employee::query()->forceCreate([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'employee_code' => 'EMP'.uniqid(), 'name' => 'Technician', 'status' => 'active',
        ]);
        $general = $this->commission($context, null, null, 100);
        $specific = $this->commission($context, $context['branch']->id, $employee->id, 0);

        $resolved = app(ServiceCommissionRuleResolver::class)->resolve(
            $context['service'], $context['branch'], $employee, null, '2026-07-25'
        );
        $this->assertTrue($resolved->is($specific));
        $this->assertFalse($resolved->is($general));
        $this->assertFalse(\Schema::hasTable('commission_payables'));
    }

    public function test_package_requires_unique_same_company_services(): void
    {
        $context = $this->context();
        $this->expectException(BusinessRuleException::class);
        app(ServicePackageService::class)->save(
            ['code' => 'PKG'.uniqid(), 'name' => 'Package', 'package_type' => 'fixed', 'is_active' => true],
            [
                ['service_id' => $context['service']->id, 'quantity' => 1],
                ['service_id' => $context['service']->id, 'quantity' => 1],
            ]
        );
    }

    public function test_promotion_resolver_only_returns_matching_foundation_record(): void
    {
        $context = $this->context();
        $promotion = Promotion::query()->forceCreate([
            'company_id' => $context['company']->id, 'code' => 'PRM'.uniqid(), 'name' => 'Promo',
            'promotion_type' => 'service', 'discount_type' => 'percentage', 'discount_value' => '10',
            'start_at' => '2026-01-01', 'end_at' => '2026-12-31', 'is_active' => true,
        ]);
        $promotion->services()->attach($context['service']);
        $promotion->branches()->attach($context['branch']);

        $resolved = app(PromotionResolver::class)->resolve($context['service'], null, $context['branch'], '2026-07-25');
        $this->assertTrue($resolved->is($promotion));
        $this->assertFalse(\Schema::hasTable('promotion_redemptions'));
    }

    public function test_permissions_hide_cost_and_routes_reject_unprivileged_users(): void
    {
        $context = $this->context();
        $this->actingAs($context['user']);

        $this->get(route('services.index'))->assertForbidden();
        $this->assertFalse($context['user']->can('viewCost', $context['service']));
        $permission = Permission::query()->firstOrCreate(['name' => 'services.view_cost'], ['display_name' => 'services.view_cost']);
        $context['role']->permissions()->attach($permission);
        $this->assertTrue($context['user']->can('viewCost', $context['service']));
    }

    public function test_service_catalog_seeder_is_idempotent_and_preserves_empty_commercial_pricing(): void
    {
        $context = $this->context();
        $seeder = new ServiceCatalogSeeder;
        $seeder->run();
        $seeder->run();

        $this->assertSame(6, ServiceCategory::where('company_id', $context['company']->id)
            ->whereIn('code', ['PPF', 'THERMAL', 'GLASS', 'INTERIOR', 'DETAILING', 'REMOVAL'])->count());
        $this->assertSame(10, Service::where('company_id', $context['company']->id)
            ->whereIn('code', [
                'PPF-FULL', 'PPF-FRONT', 'PPF-HOOD', 'PPF-BUMPER', 'PPF-LIGHTS',
                'PPF-HANDLES', 'TINT-FULL', 'TINT-WINDSHIELD', 'REMOVE-FILM', 'INTERIOR-SCREEN',
            ])->count());
        $this->assertSame(3, DocumentSequence::where('company_id', $context['company']->id)
            ->whereIn('document_type', ['service', 'service_package', 'promotion'])->count());
        $this->assertSame(0, \App\Models\ServicePrice::where('company_id', $context['company']->id)->count());
        $this->assertSame(0, Promotion::where('company_id', $context['company']->id)->count());
    }

    private function context(): array
    {
        $company = Company::query()->create(['name' => 'Services '.uniqid(), 'is_active' => true]);
        $branch = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'BR'.uniqid(), 'name' => 'Branch',
            'is_main' => true, 'is_active' => true,
        ]);
        $branch->settings()->create(['allow_negative_stock' => false]);
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $role = Role::query()->create([
            'company_id' => $company->id, 'name' => 'service_test_'.uniqid(),
            'display_name' => 'Service Test', 'scope' => 'company', 'is_active' => true,
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch->id, ['is_default' => true, 'can_view' => true]);
        app(TenantContext::class)->initialize($user);
        $unit = Unit::query()->forceCreate([
            'company_id' => $company->id, 'code' => 'U'.uniqid(), 'name' => 'Unit',
            'symbol' => 'u', 'unit_type' => 'quantity', 'decimal_places' => 6, 'is_system' => false, 'is_active' => true,
        ]);
        $category = ServiceCategory::query()->forceCreate([
            'company_id' => $company->id, 'code' => 'CAT'.uniqid(), 'name' => 'Category', 'sort_order' => 0, 'is_active' => true,
        ]);
        $tax = Tax::query()->forceCreate([
            'company_id' => $company->id, 'code' => 'VAT'.uniqid(), 'name' => 'VAT',
            'rate' => '15', 'tax_type' => 'vat', 'is_default' => true, 'is_inclusive' => false, 'is_active' => true,
        ]);
        $service = Service::query()->forceCreate([
            'company_id' => $company->id, 'service_category_id' => $category->id,
            'code' => 'SRV'.uniqid(), 'name' => 'Service', 'service_type' => 'ppf', 'pricing_type' => 'fixed',
            'default_duration_minutes' => 60, 'default_tax_id' => $tax->id,
            'requires_vehicle' => true, 'is_active' => true,
        ]);
        foreach (['service', 'service_package', 'promotion'] as $type) {
            DocumentSequence::query()->forceCreate([
                'company_id' => $company->id, 'branch_id' => null, 'document_type' => $type,
                'prefix' => strtoupper(substr($type, 0, 3)).'-', 'current_number' => 0,
                'padding' => 6, 'reset_period' => 'never', 'period_key' => null,
                'scope_key' => DocumentNumberService::scopeKey($company->id, null, $type, null), 'is_active' => true,
            ]);
        }

        return compact('company', 'branch', 'user', 'role', 'unit', 'category', 'tax', 'service');
    }

    private function serviceData(ServiceCategory $category): array
    {
        return [
            'service_category_id' => $category->id, 'code' => 'SRV'.uniqid(), 'name' => 'Service',
            'service_type' => 'ppf', 'pricing_type' => 'fixed', 'default_duration_minutes' => 60,
            'requires_vehicle' => true, 'requires_inspection' => false, 'requires_quality_check' => false,
            'allows_multiple_technicians' => false, 'is_package_only' => false, 'is_active' => true,
        ];
    }

    private function product(array $context, string $tracking): Product
    {
        $category = ProductCategory::query()->forceCreate([
            'company_id' => $context['company']->id, 'code' => 'PC'.uniqid(), 'name' => 'Products', 'is_active' => true,
        ]);

        return Product::query()->forceCreate([
            'company_id' => $context['company']->id, 'category_id' => $category->id,
            'sku' => 'SKU'.uniqid(), 'name' => 'Material', 'product_type' => $tracking === 'roll' ? 'ppf' : 'consumable',
            'tracking_type' => $tracking, 'purchase_unit_id' => $context['unit']->id,
            'stock_unit_id' => $context['unit']->id, 'sale_unit_id' => $context['unit']->id,
            'costing_method' => $tracking === 'roll' ? 'specific' : 'weighted_average',
            'minimum_stock' => 0, 'is_consumable' => true, 'is_active' => true,
        ]);
    }

    private function commission(array $context, ?int $branchId, ?int $employeeId, int $priority): ServiceCommissionRule
    {
        return ServiceCommissionRule::query()->forceCreate([
            'company_id' => $context['company']->id, 'branch_id' => $branchId,
            'service_id' => $context['service']->id, 'employee_id' => $employeeId,
            'commission_type' => 'fixed', 'commission_value' => '10', 'calculation_base' => 'fixed',
            'effective_from' => '2026-01-01', 'effective_to' => null, 'priority' => $priority, 'is_active' => true,
        ]);
    }
}
