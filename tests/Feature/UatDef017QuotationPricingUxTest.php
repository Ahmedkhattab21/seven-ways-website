<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UatDef017QuotationPricingUxTest extends TestCase
{
    use DatabaseTransactions;

    private Currency $currency;

    private Company $company;

    private Branch $branch;

    private Customer $customer;

    private Vehicle $vehicle;

    private Service $service;

    private User $pricingUser;

    private User $basicUser;

    private DocumentSequence $sequence;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::query()->firstOrCreate(['code' => 'EGP'], [
            'name_ar' => 'جنيه مصري', 'name_en' => 'Egyptian Pound', 'symbol' => 'ج.م',
            'decimal_places' => 2, 'is_active' => true,
        ]);
        $this->company = Company::query()->create([
            'name' => 'UAT DEF 017 '.uniqid(), 'currency_id' => $this->currency->id, 'is_active' => true,
        ]);
        $this->branch = Branch::query()->create([
            'company_id' => $this->company->id, 'code' => 'UAT17-'.uniqid(),
            'name' => 'الفرع الرئيسي - القاهرة', 'is_main' => true, 'is_active' => true,
        ]);
        $this->pricingUser = $this->userWithPermissions([
            'quotations.view', 'quotations.create', 'quotations.manual_price',
            'quotations.override_minimum_price',
        ], 'uat17_pricing_'.uniqid());
        $this->basicUser = $this->userWithPermissions([
            'quotations.view', 'quotations.create',
        ], 'uat17_basic_'.uniqid());
        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->id, 'created_branch_id' => $this->branch->id,
            'assigned_branch_id' => $this->branch->id, 'status' => 'active',
        ]);
        $brand = VehicleBrand::query()->create(['name_ar' => 'علامة UAT', 'is_active' => true]);
        $model = VehicleModel::query()->create([
            'vehicle_brand_id' => $brand->id, 'name_ar' => 'موديل UAT', 'is_active' => true,
        ]);
        $this->vehicle = Vehicle::query()->forceCreate([
            'company_id' => $this->company->id, 'customer_id' => $this->customer->id,
            'created_branch_id' => $this->branch->id, 'vehicle_brand_id' => $brand->id,
            'vehicle_model_id' => $model->id, 'plate_number' => 'UAT-017',
            'normalized_plate_number' => 'UAT017', 'status' => 'active',
        ]);
        $category = ServiceCategory::query()->forceCreate([
            'company_id' => $this->company->id, 'code' => 'CAT-'.uniqid(),
            'name' => 'خدمات UAT', 'is_active' => true,
        ]);
        $tax = Tax::query()->forceCreate([
            'company_id' => $this->company->id, 'code' => 'VAT14-'.uniqid(),
            'name' => 'VAT 14', 'rate' => 14, 'tax_type' => 'vat', 'is_active' => true,
        ]);
        $this->service = Service::query()->forceCreate([
            'company_id' => $this->company->id, 'service_category_id' => $category->id,
            'code' => 'SRV-'.uniqid(), 'name' => 'إزالة فيلم قديم', 'service_type' => 'ppf',
            'pricing_type' => 'fixed', 'default_duration_minutes' => 60,
            'default_tax_id' => $tax->id, 'requires_vehicle' => true, 'is_active' => true,
        ]);
        BranchService::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'service_id' => $this->service->id, 'is_available' => true, 'is_active' => true,
            'default_price' => 120, 'minimum_price' => 80, 'default_duration_minutes' => 60,
        ]);
        ServicePrice::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'service_id' => $this->service->id, 'price' => 100, 'minimum_price' => 80,
            'effective_from' => today()->subDay(), 'priority' => 0, 'is_active' => true,
        ]);
        $this->sequence = DocumentSequence::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'document_type' => 'quotation', 'prefix' => 'UAT17-', 'current_number' => 7,
            'padding' => 6, 'reset_period' => 'yearly', 'period_key' => now()->format('Y'),
            'scope_key' => DocumentNumberService::scopeKey(
                $this->company->id, $this->branch->id, 'quotation', now()->format('Y')
            ),
            'is_active' => true,
        ]);
    }

    public function test_form_has_conditional_item_fields_server_preview_and_clear_discount_sections(): void
    {
        $response = $this->actingAs($this->pricingUser)->get(route('quotations.create'))->assertOk();

        $response->assertSee('data-quotation-builder', false)
            ->assertSee('data-preview-url="'.route('quotations.preview').'"', false)
            ->assertSee('data-item-field="service"', false)
            ->assertSee('data-item-field="package"', false)
            ->assertSee('data-item-field="product"', false)
            ->assertSee('تعديل سعر الوحدة')
            ->assertSee('خصم هذه الخدمة')
            ->assertSee('خصم إضافي على إجمالي عرض السعر')
            ->assertSee('+ إضافة عنصر جديد')
            ->assertSee('حذف العنصر')
            ->assertSee('حفظ عرض السعر كمسودة');

        $javascript = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('field.disabled = !visible', $javascript);
        $this->assertStringContainsString("field.value = ''", $javascript);
        $this->assertStringContainsString('reindexItems', $javascript);
        $this->assertStringContainsString("method: 'POST'", $javascript);
        $this->assertStringContainsString('window.confirm', $javascript);
        $this->assertStringContainsString('data-summary-plain', file_get_contents(resource_path('views/quotations/form.blade.php')));
    }

    public function test_basic_user_cannot_see_or_submit_manual_price(): void
    {
        $this->actingAs($this->basicUser)->get(route('quotations.create'))
            ->assertOk()->assertDontSee('تعديل السعر يدويًا');

        $payload = $this->payload();
        $payload['items'][0]['manual_unit_price'] = 90;
        $this->actingAs($this->basicUser)->postJson(route('quotations.preview'), $payload)
            ->assertForbidden();
    }

    public function test_preview_uses_authoritative_service_price_and_writes_nothing(): void
    {
        $beforeQuotationCount = Quotation::query()->count();
        $beforeSequence = $this->sequence->current_number;

        $response = $this->actingAs($this->pricingUser)
            ->postJson(route('quotations.preview'), $this->payload())
            ->assertOk()
            ->assertJsonPath('items.0.unit_price', '100.00')
            ->assertJsonPath('items.0.base_unit_price', '100.00')
            ->assertJsonPath('items.0.price_source', 'service_price')
            ->assertJsonPath('items.0.estimated_duration_minutes', 60)
            ->assertJsonPath('summary.item_count', 1)
            ->assertJsonPath('summary.estimated_duration_minutes', 60)
            ->assertJsonPath('summary.currency_code', 'EGP');

        $response->assertJsonMissingPath('items.0.estimated_material_cost')
            ->assertJsonMissingPath('items.0.estimated_margin');
        $this->assertSame($beforeQuotationCount, Quotation::query()->count());
        $this->assertSame($beforeSequence, $this->sequence->fresh()->current_number);
    }

    public function test_item_and_header_discounts_apply_in_the_correct_order_before_tax(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['discount_type'] = 'fixed';
        $payload['items'][0]['discount_value'] = 10;
        $payload['discount_type'] = 'percentage';
        $payload['discount_value'] = 10;

        $this->actingAs($this->pricingUser)->postJson(route('quotations.preview'), $payload)
            ->assertOk()
            ->assertJsonPath('summary.subtotal_before_discounts', '200.00')
            ->assertJsonPath('summary.item_discounts_total', '10.00')
            ->assertJsonPath('summary.subtotal_after_item_discounts', '190.00')
            ->assertJsonPath('summary.header_discount_amount', '19.00')
            ->assertJsonPath('summary.tax_amount', '23.94')
            ->assertJsonPath('summary.grand_total', '194.94');
    }

    public function test_multiple_items_are_priced_independently_in_one_preview(): void
    {
        $secondService = Service::query()->forceCreate([
            'company_id' => $this->company->id,
            'service_category_id' => $this->service->service_category_id,
            'code' => 'SRV-SECOND-'.uniqid(),
            'name' => 'خدمة UAT ثانية',
            'service_type' => 'ppf',
            'pricing_type' => 'fixed',
            'default_duration_minutes' => 30,
            'default_tax_id' => $this->service->default_tax_id,
            'requires_vehicle' => true,
            'is_active' => true,
        ]);
        BranchService::query()->forceCreate([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'service_id' => $secondService->id,
            'is_available' => true,
            'is_active' => true,
            'default_price' => 50,
            'minimum_price' => 40,
            'default_duration_minutes' => 30,
        ]);
        $payload = $this->payload();
        $payload['items'][0]['quantity'] = 1;
        $payload['items'][] = [
            'item_type' => 'service',
            'service_id' => $secondService->id,
            'quantity' => 1,
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ];

        $this->actingAs($this->pricingUser)->postJson(route('quotations.preview'), $payload)
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.unit_price', '100.00')
            ->assertJsonPath('items.0.item_discount_amount', '0.00')
            ->assertJsonPath('items.1.unit_price', '50.00')
            ->assertJsonPath('items.1.item_discount_amount', '5.00')
            ->assertJsonPath('summary.item_count', 2)
            ->assertJsonPath('summary.estimated_duration_minutes', 90)
            ->assertJsonPath('summary.subtotal_after_item_discounts', '145.00');
    }

    public function test_percentage_and_excessive_discounts_are_rejected(): void
    {
        $percentage = $this->payload();
        $percentage['items'][0]['discount_type'] = 'percentage';
        $percentage['items'][0]['discount_value'] = 101;
        $this->actingAs($this->pricingUser)->postJson(route('quotations.preview'), $percentage)
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.discount_value');

        $fixed = $this->payload();
        $fixed['items'][0]['discount_type'] = 'fixed';
        $fixed['items'][0]['discount_value'] = 201;
        $this->actingAs($this->pricingUser)->postJson(route('quotations.preview'), $fixed)
            ->assertUnprocessable();

        $header = $this->payload();
        $header['discount_type'] = 'fixed';
        $header['discount_value'] = 201;
        $this->actingAs($this->pricingUser)->postJson(route('quotations.preview'), $header)
            ->assertUnprocessable();
    }

    public function test_manual_price_replaces_catalog_price_for_authorized_user(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['manual_unit_price'] = 90;

        $this->actingAs($this->pricingUser)->postJson(route('quotations.preview'), $payload)
            ->assertOk()
            ->assertJsonPath('items.0.unit_price', '90.00')
            ->assertJsonPath('items.0.base_unit_price', '100.00')
            ->assertJsonPath('items.0.price_source', 'manual');
    }

    public function test_request_prohibits_stale_references_for_selected_item_type(): void
    {
        $product = $this->payload();
        $product['items'][0]['item_type'] = 'product';
        $this->actingAs($this->pricingUser)->postJson(route('quotations.preview'), $product)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.service_id', 'items.0.product_id']);

        $service = $this->payload();
        $service['items'][0]['product_id'] = 999999;
        $this->actingAs($this->pricingUser)->postJson(route('quotations.preview'), $service)
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_browser_calculated_totals_are_rejected(): void
    {
        $payload = $this->payload();
        $payload['total'] = 1;
        $payload['items'][0]['unit_price'] = 1;

        $this->actingAs($this->pricingUser)->postJson(route('quotations.preview'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['total', 'items.0.unit_price']);
    }

    public function test_validation_error_preserves_multiple_item_input(): void
    {
        $payload = $this->payload();
        $payload['items'][] = [
            'item_type' => 'custom', 'description' => '', 'quantity' => 1, 'manual_unit_price' => '',
        ];

        $response = $this->actingAs($this->pricingUser)->from(route('quotations.create'))
            ->post(route('quotations.store'), $payload)
            ->assertRedirect(route('quotations.create'))
            ->assertSessionHasErrors(['items.1.description', 'items.1.manual_unit_price']);

        $this->assertCount(2, session()->getOldInput('items'));
    }

    public function test_store_normalizes_missing_lead_to_null_instead_of_foreign_key_zero(): void
    {
        $payload = $this->payload();
        $payload['lead_id'] = 0;

        $response = $this->actingAs($this->pricingUser)
            ->post(route('quotations.store'), $payload);

        $quotation = Quotation::query()
            ->where('company_id', $this->company->id)
            ->latest('id')
            ->firstOrFail();

        $response->assertRedirect(route('quotations.show', $quotation));
        $this->assertNull($quotation->lead_id);
    }

    private function payload(): array
    {
        return [
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'currency_id' => $this->currency->id,
            'quotation_date' => today()->toDateString(),
            'valid_until' => today()->addDays(7)->toDateString(),
            'discount_value' => 0,
            'items' => [[
                'item_type' => 'service', 'service_id' => $this->service->id,
                'quantity' => 2, 'discount_value' => 0,
            ]],
        ];
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
        $user->accessibleBranches()->attach($this->branch->id, [
            'is_default' => true, 'can_view' => true,
        ]);

        return $user;
    }
}
