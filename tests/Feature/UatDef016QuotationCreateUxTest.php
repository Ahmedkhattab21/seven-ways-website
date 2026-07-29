<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UatDef016QuotationCreateUxTest extends TestCase
{
    use DatabaseTransactions;

    private Currency $egp;

    private Currency $aed;

    private Company $company;

    private Branch $branch;

    private User $owner;

    private User $viewer;

    private Customer $customer;

    private Customer $otherCustomer;

    private Vehicle $vehicle;

    private Vehicle $otherVehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->egp = Currency::query()->firstOrCreate(['code' => 'EGP'], [
            'name_ar' => 'جنيه مصري', 'name_en' => 'Egyptian Pound', 'symbol' => 'ج.م',
            'decimal_places' => 2, 'is_active' => true,
        ]);
        $this->aed = Currency::query()->firstOrCreate(['code' => 'AED'], [
            'name_ar' => 'درهم إماراتي', 'name_en' => 'UAE Dirham', 'symbol' => 'AED',
            'decimal_places' => 2, 'is_active' => true,
        ]);
        $this->company = Company::query()->create([
            'name' => 'UAT DEF 016 '.uniqid(), 'currency_id' => $this->egp->id, 'is_active' => true,
        ]);
        $this->branch = Branch::query()->create([
            'company_id' => $this->company->id, 'code' => 'UAT16-'.uniqid(),
            'name' => 'الفرع الرئيسي - القاهرة', 'is_main' => true, 'is_active' => true,
        ]);
        $this->owner = $this->userWithPermissions([
            'quotations.view', 'quotations.create', 'quotations.update', 'quotations.manual_price',
        ], 'company_owner');
        $this->viewer = $this->userWithPermissions(['quotations.view'], 'quotation_viewer_'.uniqid());

        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->id, 'created_branch_id' => $this->branch->id,
            'assigned_branch_id' => $this->branch->id, 'customer_code' => 'CAI-MAIN-CUS-2026-000001',
            'name' => 'أحمد محمد - عميل اختبار',
        ]);
        $this->otherCustomer = Customer::factory()->create([
            'company_id' => $this->company->id, 'created_branch_id' => $this->branch->id,
            'assigned_branch_id' => $this->branch->id, 'customer_code' => 'CAI-MAIN-CUS-2026-000002',
            'name' => 'عميل آخر',
        ]);
        $brand = VehicleBrand::query()->create(['name_ar' => 'علامة سيارات تجريبية UAT', 'is_active' => true]);
        $model = VehicleModel::query()->create([
            'vehicle_brand_id' => $brand->id, 'name_ar' => 'موديل تجريبي UAT', 'is_active' => true,
        ]);
        $this->vehicle = $this->vehicle($this->company, $this->customer, $brand, $model, 'UAT-006');
        $this->otherVehicle = $this->vehicle($this->company, $this->otherCustomer, $brand, $model, 'UAT-007');
    }

    public function test_create_button_follows_custom_permission_system(): void
    {
        $this->actingAs($this->owner)->get(route('quotations.index'))
            ->assertOk()->assertSee('إضافة عرض سعر')->assertSee(route('quotations.create'));

        $this->actingAs($this->viewer)->get(route('quotations.index'))
            ->assertOk()->assertDontSee('إضافة عرض سعر');

        $this->actingAs($this->viewer)->get(route('quotations.create'))->assertForbidden();
    }

    public function test_new_form_uses_company_currency_then_old_input(): void
    {
        $this->actingAs($this->owner)->get(route('quotations.create'))
            ->assertOk()->assertSee('value="'.$this->egp->id.'"', false);

        $this->actingAs($this->owner)
            ->withSession(['_old_input' => ['currency_id' => $this->aed->id]])
            ->get(route('quotations.create'))
            ->assertOk()->assertSee('value="'.$this->aed->id.'"', false);
    }

    public function test_edit_form_preserves_stored_currency(): void
    {
        $quotation = $this->quotation($this->aed);

        $this->actingAs($this->owner)->get(route('quotations.edit', $quotation))
            ->assertOk()->assertSee('value="'.$this->aed->id.'"', false);
    }

    public function test_customer_and_vehicle_labels_are_clear_and_relations_are_eager_loaded(): void
    {
        $response = $this->actingAs($this->owner)->get(route('quotations.create'))->assertOk();

        $response->assertSee('CAI-MAIN-CUS-2026-000001 — أحمد محمد - عميل اختبار')
            ->assertSee('UAT-006 — علامة سيارات تجريبية UAT / موديل تجريبي UAT — CAI-MAIN-CUS-2026-000001 — أحمد محمد - عميل اختبار')
            ->assertDontSee('UAT-006 — '.$this->customer->id);

        $vehicles = $response->viewData('vehicles');
        $this->assertTrue($vehicles->every(
            fn (Vehicle $vehicle) => $vehicle->relationLoaded('customer')
                && $vehicle->relationLoaded('brand') && $vehicle->relationLoaded('model')
        ));
    }

    public function test_vehicle_options_support_customer_filtering_without_losing_backend_protection(): void
    {
        $response = $this->actingAs($this->owner)->get(route('quotations.create'))->assertOk();

        $response->assertSee('data-quotation-customer', false)
            ->assertSee('data-quotation-vehicle', false)
            ->assertSee('data-customer-id="'.$this->customer->id.'"', false)
            ->assertSee('data-customer-id="'.$this->otherCustomer->id.'"', false)
            ->assertSee('لا توجد سيارة نشطة مرتبطة بالعميل المحدد.');

        $javascript = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('option.hidden = !visible', $javascript);
        $this->assertStringContainsString("customerSelect.addEventListener('change', filterVehicles)", $javascript);
    }

    public function test_backend_rejects_vehicle_owned_by_another_customer_or_company(): void
    {
        $this->actingAs($this->owner)->post(route('quotations.store'), $this->payload($this->otherVehicle))
            ->assertSessionHasErrors('vehicle_id');

        $otherCompany = Company::query()->create([
            'name' => 'Other UAT 016 '.uniqid(), 'currency_id' => $this->egp->id, 'is_active' => true,
        ]);
        $otherBranch = Branch::query()->create([
            'company_id' => $otherCompany->id, 'code' => 'OTHER-'.uniqid(),
            'name' => 'Other Branch', 'is_main' => true, 'is_active' => true,
        ]);
        $brand = $this->vehicle->brand;
        $model = $this->vehicle->model;
        $crossCustomer = Customer::factory()->create([
            'company_id' => $otherCompany->id, 'created_branch_id' => $otherBranch->id,
            'assigned_branch_id' => $otherBranch->id,
        ]);
        $crossVehicle = $this->vehicle($otherCompany, $crossCustomer, $brand, $model, 'CROSS-016', $otherBranch);

        $this->actingAs($this->owner)->post(route('quotations.store'), $this->payload($crossVehicle))
            ->assertSessionHasErrors('vehicle_id');
    }

    public function test_quotation_statuses_are_translated_in_list_and_filter(): void
    {
        $this->quotation($this->egp);

        $response = $this->actingAs($this->owner)->get(route('quotations.index'))->assertOk();
        foreach ([
            'مسودة', 'في انتظار الاعتماد', 'معتمد', 'مُرسل للعميل', 'مقبول',
            'مرفوض', 'منتهي', 'تم تحويله', 'ملغي', 'مستبدل بإصدار أحدث',
        ] as $label) {
            $response->assertSee($label);
        }
    }

    private function userWithPermissions(array $permissions, string $roleName): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'status' => 'active',
        ]);
        $role = Role::query()->create([
            'company_id' => $this->company->id, 'name' => $roleName,
            'display_name' => $roleName, 'scope' => 'company', 'is_active' => true,
        ]);
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['display_name' => $name]);
            $role->permissions()->syncWithoutDetaching($permission);
        }
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($this->branch->id, ['is_default' => true, 'can_view' => true]);

        return $user;
    }

    private function vehicle(
        Company $company,
        Customer $customer,
        VehicleBrand $brand,
        VehicleModel $model,
        string $plate,
        ?Branch $branch = null
    ): Vehicle {
        return Vehicle::query()->forceCreate([
            'company_id' => $company->id, 'customer_id' => $customer->id,
            'created_branch_id' => ($branch ?? $this->branch)->id,
            'vehicle_brand_id' => $brand->id, 'vehicle_model_id' => $model->id,
            'plate_number' => $plate, 'normalized_plate_number' => str_replace('-', '', $plate),
            'status' => 'active',
        ]);
    }

    private function quotation(Currency $currency): Quotation
    {
        return Quotation::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'quotation_number' => 'UAT16-'.uniqid(), 'version_number' => 1,
            'customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id,
            'status' => 'draft', 'quotation_date' => today(), 'valid_until' => today()->addDays(7),
            'currency_id' => $currency->id, 'created_by' => $this->owner->id,
        ]);
    }

    private function payload(Vehicle $vehicle): array
    {
        return [
            'branch_id' => $this->branch->id, 'customer_id' => $this->customer->id,
            'vehicle_id' => $vehicle->id, 'quotation_date' => today()->toDateString(),
            'valid_until' => today()->addDays(7)->toDateString(), 'currency_id' => $this->egp->id,
            'discount_value' => 0,
            'items' => [[
                'item_type' => 'custom', 'description' => 'UAT validation only',
                'quantity' => 1, 'manual_unit_price' => 1, 'discount_value' => 0,
            ]],
        ];
    }
}
