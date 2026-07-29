<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\BranchServicePackage;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\ServicePrice;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Services\AppointmentSchedulingService;
use App\Services\DocumentNumberService;
use App\Services\ServicePackageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ServicePackagesCoreModuleTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private Branch $branch;

    private User $user;

    private Service $firstService;

    private Service $secondService;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::query()->firstOrCreate(['code' => 'EGP'], [
            'name_ar' => 'جنيه مصري', 'name_en' => 'Egyptian Pound', 'symbol' => 'ج.م',
            'decimal_places' => 2, 'is_active' => true,
        ]);
        $this->company = Company::query()->create([
            'name' => 'Package module '.uniqid(), 'currency_id' => $currency->id, 'is_active' => true,
        ]);
        $this->branch = Branch::query()->create([
            'company_id' => $this->company->id, 'code' => 'CAI-'.uniqid(),
            'name' => 'الفرع الرئيسي - القاهرة', 'is_main' => true, 'is_active' => true,
        ]);
        $this->user = $this->userWithPermissions([
            'service_packages.view', 'service_packages.create', 'service_packages.update',
            'service_packages.disable', 'service_packages.manage_prices', 'quotations.create',
        ]);
        app(TenantContext::class)->initialize($this->user);

        $category = ServiceCategory::query()->forceCreate([
            'company_id' => $this->company->id, 'code' => 'PKG-CAT-'.uniqid(),
            'name' => 'خدمات الباقات', 'is_active' => true,
        ]);
        $tax = Tax::query()->forceCreate([
            'company_id' => $this->company->id, 'code' => 'PKG-VAT-'.uniqid(),
            'name' => 'VAT 14', 'rate' => 14, 'tax_type' => 'vat', 'is_active' => true,
        ]);
        $this->firstService = $this->service($category, $tax, 'غسيل شامل', 100, 60);
        $this->secondService = $this->service($category, $tax, 'تلميع', 60, 30);

        DocumentSequence::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => null, 'document_type' => 'service_package',
            'prefix' => 'PKG-', 'current_number' => 0, 'padding' => 6, 'reset_period' => 'never',
            'period_key' => null,
            'scope_key' => DocumentNumberService::scopeKey($this->company->id, null, 'service_package', null),
            'is_active' => true,
        ]);
    }

    public function test_catalog_shows_package_card_tab_metrics_and_create_action(): void
    {
        $this->actingAs($this->user)->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('باقات الخدمات')
            ->assertSee('تجميع أكثر من خدمة داخل باقة واحدة بسعر ومدة وإتاحة خاصة بكل فرع.')
            ->assertSee('الباقات غير المسعّرة')
            ->assertSee(route('service-packages.create'), false);

        $this->actingAs($this->user)->get(route('service-packages.index'))
            ->assertOk()->assertSee('إضافة باقة خدمات')->assertSee('لا توجد باقات خدمات حتى الآن.');
    }

    public function test_package_form_updates_approved_price_and_marks_missing_service_prices(): void
    {
        $view = file_get_contents(resource_path('views/services/packages/form.blade.php'));
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('data-package-approved', $view);
        $this->assertStringContainsString('data-package-missing-prices', $view);
        $this->assertStringContainsString("missingPrice ? '—'", $javascript);
        $this->assertStringContainsString('approved.toFixed(2)', $javascript);
        $this->assertStringContainsString('missingPricesMessage?.toggleAttribute', $javascript);
    }

    public function test_quotation_form_explains_why_no_package_matches_the_vehicle(): void
    {
        $view = file_get_contents(resource_path('views/quotations/_item-row.blade.php'));
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('data-package-empty', $view);
        $this->assertStringContainsString('السيارة المحددة لا تحتوي على حجم', $javascript);
        $this->assertStringContainsString('availableCount > 0', $javascript);
    }

    public function test_package_service_can_be_scheduled_through_its_active_branch_package(): void
    {
        $package = $this->package();
        BranchServicePackage::query()->forceCreate([
            'branch_id' => $this->branch->id, 'service_package_id' => $package->id,
            'price' => 120, 'minimum_price' => 100, 'is_available' => true,
            'effective_from' => today()->subDay(),
        ]);
        BranchService::query()->where('branch_id', $this->branch->id)
            ->where('service_id', $this->firstService->id)->delete();

        app(AppointmentSchedulingService::class)->validate(
            $this->branch,
            now()->addDay()->setTime(10, 0),
            now()->addDay()->setTime(11, 0),
            null,
            [$this->firstService->id],
            null,
            [$this->firstService->id => [$package->id]]
        );

        $this->assertTrue(true);
    }

    public function test_create_action_is_hidden_without_create_permission(): void
    {
        $viewer = $this->userWithPermissions(['service_packages.view']);

        $this->actingAs($viewer)->get(route('catalog.index'))
            ->assertOk()->assertDontSee(route('service-packages.create'), false);
        $this->actingAs($viewer)->get(route('service-packages.create'))->assertForbidden();
    }

    public function test_package_requires_unique_service_and_saves_quantities_and_price_atomically(): void
    {
        $base = [
            'name' => 'باقة الاختبار', 'package_type' => 'fixed', 'is_active' => 1,
            'branch_id' => $this->branch->id, 'price' => 120, 'minimum_price' => 100,
            'effective_from' => today()->toDateString(), 'is_available' => 1,
        ];
        $this->actingAs($this->user)->post(route('service-packages.store'), $base + ['items' => []])
            ->assertSessionHasErrors('items');
        $this->actingAs($this->user)->post(route('service-packages.store'), $base + ['items' => [
            ['service_id' => $this->firstService->id, 'quantity' => 1],
            ['service_id' => $this->firstService->id, 'quantity' => 2],
        ]])->assertSessionHasErrors('items.1.service_id');

        $this->actingAs($this->user)->post(route('service-packages.store'), $base + ['items' => [
            ['service_id' => $this->firstService->id, 'quantity' => 1],
            ['service_id' => $this->secondService->id, 'quantity' => 2],
        ]])->assertRedirect();

        $package = ServicePackage::query()->where('name', 'باقة الاختبار')->firstOrFail();
        $this->assertSame(2, $package->items()->count());
        $this->assertSame('2.0000', $package->items()->where('service_id', $this->secondService->id)->value('quantity'));
        $this->assertDatabaseHas('branch_service_packages', [
            'service_package_id' => $package->id, 'branch_id' => $this->branch->id,
            'price' => '120.0000', 'minimum_price' => '100.0000', 'is_available' => 1,
        ]);
        $this->actingAs($this->user)->get(route('service-packages.index'))
            ->assertOk()->assertSee('باقة الاختبار')->assertSee('التوفير:');
    }

    public function test_overlapping_package_prices_are_rejected_and_failed_scope_rolls_back_package(): void
    {
        $blockedBranch = Branch::query()->create([
            'company_id' => $this->company->id, 'code' => 'BLOCKED-'.uniqid(),
            'name' => 'فرع غير متاح', 'is_main' => false, 'is_active' => true,
        ]);
        $before = ServicePackage::query()->count();
        try {
            app(ServicePackageService::class)->save(
                ['name' => 'يجب التراجع عنها', 'package_type' => 'fixed', 'is_active' => true],
                [['service_id' => $this->firstService->id, 'quantity' => 1]],
                null,
                [
                    'branch_id' => $blockedBranch->id, 'price' => 50, 'minimum_price' => 40,
                    'effective_from' => today()->toDateString(), 'is_available' => true,
                ]
            );
            $this->fail('An inaccessible branch price must fail.');
        } catch (BusinessRuleException) {
            $this->assertSame($before, ServicePackage::query()->count());
        }

        $package = $this->package();
        $service = app(ServicePackageService::class);
        $data = [
            'branch_id' => $this->branch->id, 'vehicle_size_id' => null, 'price' => 120,
            'minimum_price' => 100, 'effective_from' => '2026-01-01',
            'effective_to' => null, 'is_available' => true,
        ];
        $service->savePrice($package, $data);

        $this->expectException(BusinessRuleException::class);
        $service->savePrice($package, $data + ['price' => 110]);
    }

    public function test_quotation_uses_approved_package_price_and_returns_savings_and_services(): void
    {
        $package = $this->package();
        BranchServicePackage::query()->forceCreate([
            'branch_id' => $this->branch->id, 'service_package_id' => $package->id,
            'price' => 120, 'minimum_price' => 100, 'is_available' => true,
            'effective_from' => today()->subDay(),
        ]);
        $customer = Customer::factory()->create([
            'company_id' => $this->company->id, 'created_branch_id' => $this->branch->id,
            'assigned_branch_id' => $this->branch->id, 'status' => 'active',
        ]);
        $brand = VehicleBrand::query()->create(['name_ar' => 'علامة اختبار', 'is_active' => true]);
        $model = VehicleModel::query()->create([
            'vehicle_brand_id' => $brand->id, 'name_ar' => 'موديل اختبار', 'is_active' => true,
        ]);
        $vehicle = Vehicle::query()->forceCreate([
            'company_id' => $this->company->id, 'customer_id' => $customer->id,
            'created_branch_id' => $this->branch->id, 'vehicle_brand_id' => $brand->id,
            'vehicle_model_id' => $model->id, 'plate_number' => 'PKG-UAT',
            'normalized_plate_number' => 'PKGUAT', 'status' => 'active',
        ]);

        $this->actingAs($this->user)->postJson(route('quotations.preview'), [
            'branch_id' => $this->branch->id, 'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id,
            'currency_id' => $this->company->currency_id, 'quotation_date' => today()->toDateString(),
            'valid_until' => today()->addWeek()->toDateString(), 'discount_value' => 0,
            'items' => [[
                'item_type' => 'package', 'service_package_id' => $package->id,
                'quantity' => 1, 'discount_value' => 0,
            ]],
        ])->assertOk()
            ->assertJsonPath('items.0.unit_price', '120.00')
            ->assertJsonPath('items.0.standalone_services_total', '160.00')
            ->assertJsonPath('items.0.package_savings', '40.00')
            ->assertJsonCount(2, 'items.0.package_services')
            ->assertJsonPath('items.0.line_total', '136.80');
    }

    private function service(ServiceCategory $category, Tax $tax, string $name, int $price, int $duration): Service
    {
        $service = Service::query()->forceCreate([
            'company_id' => $this->company->id, 'service_category_id' => $category->id,
            'code' => 'SRV-'.uniqid(), 'name' => $name, 'service_type' => 'detailing',
            'pricing_type' => 'fixed', 'default_duration_minutes' => $duration,
            'default_tax_id' => $tax->id, 'requires_vehicle' => true, 'is_active' => true,
        ]);
        BranchService::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'service_id' => $service->id, 'is_available' => true, 'is_active' => true,
            'default_price' => $price, 'minimum_price' => 0, 'default_duration_minutes' => $duration,
        ]);
        ServicePrice::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'service_id' => $service->id, 'price' => $price, 'minimum_price' => 0,
            'effective_from' => today()->subDay(), 'priority' => 0, 'is_active' => true,
        ]);

        return $service;
    }

    private function package(): ServicePackage
    {
        $package = ServicePackage::query()->forceCreate([
            'company_id' => $this->company->id, 'code' => 'PKG-'.uniqid(),
            'name' => 'باقة موحدة', 'package_type' => 'fixed', 'is_active' => true,
        ]);
        $package->items()->createMany([
            ['service_id' => $this->firstService->id, 'quantity' => 1, 'is_required' => true, 'sort_order' => 0],
            ['service_id' => $this->secondService->id, 'quantity' => 1, 'is_required' => true, 'sort_order' => 1],
        ]);

        return $package;
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::query()->create([
            'company_id' => $this->company->id, 'name' => 'package_'.uniqid(),
            'display_name' => 'Package tester', 'scope' => 'branch', 'is_active' => true,
        ]);
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['display_name' => $name]);
            $role->permissions()->syncWithoutDetaching($permission);
        }
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($this->branch, ['is_default' => true, 'can_view' => true]);

        return $user;
    }
}
