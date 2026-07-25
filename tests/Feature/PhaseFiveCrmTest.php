<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Services\CustomerService;
use App\Services\DocumentNumberService;
use App\Services\LeadConversionService;
use App\Services\LeadFollowUpService;
use App\Services\LeadService;
use App\Services\VehicleOwnershipService;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseFiveCrmTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_creation_is_tenant_owned_numbered_audited_and_mass_assignment_safe(): void
    {
        [$user, $branch, $company] = $this->tenantUser('company_owner');
        $this->sequence($company, $branch, 'customer', 'CUS-', 'never');
        $foreign = Company::query()->create(['name' => 'Foreign '.uniqid()]);

        $customer = app(CustomerService::class)->create([
            ...$this->customerData($branch),
            'company_id' => $foreign->id,
        ]);

        $this->assertSame($company->id, $customer->company_id);
        $this->assertSame($branch->id, $customer->created_branch_id);
        $this->assertSame('CUS-000001', $customer->customer_code);
        $this->assertSame('966501234567', $customer->normalized_phone);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id, 'event' => 'customer.created',
            'auditable_type' => Customer::class, 'auditable_id' => $customer->id,
        ]);
    }

    public function test_duplicate_phone_warns_but_can_be_confirmed_and_tax_numbers_are_company_unique(): void
    {
        [$user, $branch, $company] = $this->tenantUser('company_owner');
        $this->sequence($company, $branch, 'customer', 'CUS-', 'never');
        $service = app(CustomerService::class);
        $service->create($this->customerData($branch));

        try {
            $service->create([...$this->customerData($branch), 'name' => 'Duplicate']);
            $this->fail('Expected duplicate warning.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('phone', $exception->errors());
        }

        $confirmed = $service->create([
            ...$this->customerData($branch),
            'name' => 'Confirmed',
            'confirm_duplicate' => true,
            'tax_number' => 'TAX-UNIQUE',
        ]);
        $this->assertSame($company->id, $confirmed->company_id);
        $this->expectException(\Illuminate\Database\QueryException::class);
        Customer::query()->forceCreate([
            ...$confirmed->only([
                'company_id', 'created_branch_id', 'assigned_branch_id', 'customer_type',
                'phone', 'normalized_phone', 'preferred_language', 'credit_limit',
                'payment_term_days', 'status', 'tax_number',
            ]),
            'customer_code' => 'CUS-999999',
            'name' => 'Tax duplicate',
        ]);
    }

    public function test_branch_scope_and_company_admin_scope_prevent_cross_company_access(): void
    {
        [$branchUser, $branchA, $company] = $this->tenantUser('branch_manager', ['customers.view']);
        $branchB = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'B2', 'name' => 'Branch 2', 'is_active' => true,
        ]);
        $own = $this->rawCustomer($company, $branchA, 'CUS-A');
        $otherBranch = $this->rawCustomer($company, $branchB, 'CUS-B');
        [$admin] = $this->tenantUser('company_owner', ['customers.view'], $company, $branchA);
        [$foreignUser, $foreignBranch, $foreignCompany] = $this->tenantUser('company_owner', ['customers.view']);
        $foreignCustomer = $this->rawCustomer($foreignCompany, $foreignBranch, 'CUS-X');

        app(TenantContext::class)->initialize($branchUser);
        $this->assertEquals([$own->id], Customer::query()->forUser($branchUser)->pluck('id')->all());
        app(TenantContext::class)->initialize($admin);
        $this->assertEqualsCanonicalizing([$own->id, $otherBranch->id], Customer::query()->forUser($admin)->pluck('id')->all());
        $this->actingAs($branchUser)->get(route('customers.show', $foreignCustomer))->assertForbidden();
        $this->actingAs($foreignUser)->get(route('customers.show', $own))->assertForbidden();
    }

    public function test_customer_primary_contact_and_default_address_are_singular(): void
    {
        [$user, $branch, $company] = $this->tenantUser('company_owner');
        $customer = $this->rawCustomer($company, $branch, 'CUS-REL');
        $service = app(CustomerService::class);
        $first = $service->addContact($customer, ['name' => 'First', 'is_primary' => true, 'is_active' => true]);
        $second = $service->addContact($customer, ['name' => 'Second', 'is_primary' => true, 'is_active' => true]);
        $service->addAddress($customer, ['label' => 'A', 'address_type' => 'billing', 'country_code' => 'SA', 'is_default' => true, 'is_active' => true]);
        $service->addAddress($customer, ['label' => 'B', 'address_type' => 'billing', 'country_code' => 'SA', 'is_default' => true, 'is_active' => true]);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame(1, $customer->addresses()->where('address_type', 'billing')->where('is_default', true)->count());
        $service->deleteContact($first);
        $this->assertSoftDeleted('customer_contacts', ['id' => $first->id]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'customer.contact_deleted', 'auditable_id' => $customer->id,
        ]);
    }

    public function test_tax_and_commercial_registration_are_unique_inside_company(): void
    {
        [$user, $branch, $company] = $this->tenantUser('company_owner', ['customers.create']);
        $this->sequence($company, $branch, 'customer', 'CUS-', 'never');
        Customer::factory()->create([
            'company_id' => $company->id, 'created_branch_id' => $branch->id,
            'assigned_branch_id' => $branch->id, 'tax_number' => 'TAX-1',
            'commercial_registration' => 'CR-1',
        ]);

        $this->actingAs($user)->post(route('customers.store'), [
            ...$this->customerData($branch),
            'tax_number' => 'TAX-1',
            'commercial_registration' => 'CR-1',
        ])->assertSessionHasErrors(['tax_number', 'commercial_registration']);
    }

    public function test_vehicle_validates_owner_model_plate_vin_and_records_ownership_history(): void
    {
        [$user, $branch, $company] = $this->tenantUser('company_owner');
        $customer = $this->rawCustomer($company, $branch, 'CUS-V1');
        $nextOwner = $this->rawCustomer($company, $branch, 'CUS-V2');
        [$brand, $model] = $this->vehicleReference();
        $vehicle = app(VehicleService::class)->save(new Vehicle(), [
            'customer_id' => $customer->id, 'vehicle_brand_id' => $brand->id,
            'vehicle_model_id' => $model->id, 'plate_number' => 'abc ١٢٣',
            'vin' => 'vin-unique', 'status' => 'active',
        ]);
        app(VehicleOwnershipService::class)->transfer($vehicle, $nextOwner->id, ['reason' => 'Sale']);

        $this->assertSame('ABC123', $vehicle->fresh()->normalized_plate_number);
        $this->assertSame('VIN-UNIQUE', $vehicle->fresh()->vin);
        $this->assertDatabaseHas('vehicle_ownership_history', [
            'vehicle_id' => $vehicle->id, 'from_customer_id' => $customer->id,
            'to_customer_id' => $nextOwner->id, 'created_by' => $user->id,
        ]);

        try {
            app(VehicleOwnershipService::class)->transfer($vehicle->fresh(), $nextOwner->id);
            $this->fail('Expected duplicate ownership transfer rejection.');
        } catch (ValidationException) {
            $this->assertSame($nextOwner->id, $vehicle->fresh()->customer_id);
            $this->assertSame(1, $vehicle->ownershipHistory()->count());
        }
    }

    public function test_vehicle_rejects_cross_company_customer_and_wrong_brand_model(): void
    {
        [$user, $branch, $company] = $this->tenantUser('company_owner');
        [$brand, $model] = $this->vehicleReference();
        $otherBrand = VehicleBrand::query()->create(['name_ar' => 'Other', 'is_active' => true]);
        [, $foreignBranch, $foreignCompany] = $this->tenantUser('company_owner');
        $foreignCustomer = $this->rawCustomer($foreignCompany, $foreignBranch, 'CUS-X');
        app(TenantContext::class)->initialize($user);

        try {
            app(VehicleService::class)->save(new Vehicle(), [
                'customer_id' => $foreignCustomer->id, 'vehicle_brand_id' => $brand->id,
                'vehicle_model_id' => $model->id, 'status' => 'active',
            ]);
            $this->fail('Expected cross-company owner rejection.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $customer = $this->rawCustomer($company, $branch, 'CUS-OWN');
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(VehicleService::class)->save(new Vehicle(), [
            'customer_id' => $customer->id, 'vehicle_brand_id' => $otherBrand->id,
            'vehicle_model_id' => $model->id, 'status' => 'active',
        ]);
    }

    public function test_lead_number_follow_up_lost_rule_and_conversion_are_transactional(): void
    {
        [, $branch, $company] = $this->tenantUser('company_owner');
        $this->sequence($company, $branch, 'customer', 'CUS-', 'never');
        $this->sequence($company, $branch, 'lead', '{BRANCH}-LEAD-{YYYY}-', 'yearly');
        $lead = app(LeadService::class)->save(new Lead(), [
            'name' => 'Lead One', 'phone' => '0501234567', 'status' => 'new', 'priority' => 'high',
        ]);
        $next = now()->addDay()->setMicrosecond(0);
        app(LeadFollowUpService::class)->create($lead, [
            'follow_up_type' => 'call', 'outcome' => 'Interested', 'next_follow_up_at' => $next,
        ]);
        $customer = app(LeadConversionService::class)->convert($lead, []);

        $this->assertStringContainsString('-LEAD-', $lead->lead_number);
        $this->assertEquals($next, $lead->fresh()->next_follow_up_at);
        $this->assertSame('won', $lead->fresh()->status);
        $this->assertSame($customer->id, $lead->fresh()->converted_customer_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'lead.converted', 'auditable_id' => $lead->id]);

        $this->expectException(ValidationException::class);
        app(LeadConversionService::class)->convert($lead->fresh(), []);
    }

    public function test_lost_requires_reason_and_branch_users_cannot_see_other_branch_leads(): void
    {
        [$user, $branch, $company] = $this->tenantUser('branch_manager', ['leads.view', 'leads.update']);
        $otherBranch = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'B2', 'name' => 'Branch 2', 'is_active' => true,
        ]);
        $own = Lead::factory()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'created_by' => $user->id,
        ]);
        Lead::factory()->create([
            'company_id' => $company->id, 'branch_id' => $otherBranch->id, 'created_by' => $user->id,
        ]);

        $this->assertEquals([$own->id], Lead::query()->forUser($user)->pluck('id')->all());
        $this->expectException(ValidationException::class);
        app(LeadService::class)->save($own, [
            'name' => $own->name, 'phone' => $own->phone, 'status' => 'lost', 'priority' => 'normal',
        ]);
    }

    public function test_lead_conversion_detects_and_can_link_an_existing_customer(): void
    {
        [$user, $branch, $company] = $this->tenantUser('company_owner');
        $this->sequence($company, $branch, 'lead', '{BRANCH}-LEAD-{YYYY}-', 'yearly');
        $customer = Customer::factory()->create([
            'company_id' => $company->id, 'created_branch_id' => $branch->id,
            'assigned_branch_id' => $branch->id, 'phone' => '0501234567',
            'normalized_phone' => '966501234567',
        ]);
        $lead = app(LeadService::class)->save(new Lead(), [
            'name' => 'Existing', 'phone' => '0501234567', 'status' => 'new', 'priority' => 'normal',
        ]);

        try {
            app(LeadConversionService::class)->convert($lead, []);
            $this->fail('Expected duplicate customer warning.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('phone', $exception->errors());
        }

        $linked = app(LeadConversionService::class)->convert($lead->fresh(), ['customer_id' => $customer->id]);
        $this->assertSame($customer->id, $linked->id);
        $this->assertSame(1, Customer::query()->where('company_id', $company->id)
            ->where('normalized_phone', '966501234567')->count());
        $this->assertSame($user->id, $lead->fresh()->updated_by);
    }

    public function test_attachments_are_private_validated_and_cross_company_download_is_forbidden(): void
    {
        Storage::fake('local');
        [$user, $branch, $company] = $this->tenantUser('company_owner', [
            'customers.view', 'customers.manage_attachments',
        ]);
        $customer = $this->rawCustomer($company, $branch, 'CUS-FILE');
        $this->actingAs($user)->post(route('customers.attachments.store', $customer), [
            'file' => UploadedFile::fake()->image('car.jpg'),
            'category' => 'customer_document',
        ])->assertRedirect();
        $attachment = $customer->attachments()->firstOrFail();

        Storage::disk('local')->assertExists($attachment->path);
        $this->assertStringStartsWith('private/attachments/'.$company->id.'/', $attachment->path);
        $this->actingAs($user)->post(route('customers.attachments.store', $customer), [
            'file' => UploadedFile::fake()->create('bad.exe', 1, 'application/x-msdownload'),
        ])->assertSessionHasErrors('file');
        $this->actingAs($user)->post(route('customers.attachments.store', $customer), [
            'file' => UploadedFile::fake()->create(
                'large.pdf',
                config('attachments.max_kb') + 1,
                'application/pdf'
            ),
        ])->assertSessionHasErrors('file');

        [$unauthorized] = $this->tenantUser('branch_manager', [], $company, $branch);
        $this->actingAs($unauthorized)->get(route('attachments.download', $attachment))->assertForbidden();

        [$foreignUser] = $this->tenantUser('company_owner', ['customers.view']);
        $this->actingAs($foreignUser)->get(route('attachments.download', $attachment))->assertForbidden();
    }

    private function tenantUser(
        string $roleName,
        array $permissionNames = [],
        ?Company $company = null,
        ?Branch $branch = null
    ): array {
        $company ??= Company::query()->create(['name' => 'Company '.uniqid()]);
        $branch ??= Branch::query()->create([
            'company_id' => $company->id, 'code' => 'MAIN'.uniqid(),
            'name' => 'Main', 'is_main' => true, 'is_active' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active',
        ]);
        $role = Role::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => $roleName],
            [
                'display_name' => $roleName,
                'scope' => $roleName === 'company_owner' ? 'company' : 'branch',
                'is_active' => true,
            ]
        );
        $permissions = collect($permissionNames)->map(fn ($name) => Permission::query()->firstOrCreate(
            ['name' => $name], ['display_name' => $name]
        ));
        $role->permissions()->syncWithoutDetaching($permissions->pluck('id'));
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, ['is_default' => true, 'can_view' => true]);
        app(TenantContext::class)->initialize($user);

        return [$user, $branch, $company];
    }

    private function sequence(Company $company, Branch $branch, string $type, string $prefix, string $reset): void
    {
        $period = $reset === 'yearly' ? now()->format('Y') : null;
        DocumentSequence::query()->forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'document_type' => $type, 'prefix' => $prefix, 'current_number' => 0,
            'padding' => 6, 'reset_period' => $reset, 'period_key' => $period,
            'scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, $type, $period),
            'is_active' => true,
        ]);
    }

    private function customerData(Branch $branch): array
    {
        return [
            'customer_type' => 'individual', 'name' => 'Customer '.uniqid(),
            'phone' => '050 123 4567', 'preferred_language' => 'ar',
            'credit_limit' => 0, 'payment_term_days' => 0, 'status' => 'active',
            'assigned_branch_id' => $branch->id,
        ];
    }

    private function rawCustomer(Company $company, Branch $branch, string $code): Customer
    {
        return Customer::factory()->create([
            'company_id' => $company->id, 'created_branch_id' => $branch->id,
            'assigned_branch_id' => $branch->id, 'customer_code' => $code,
        ]);
    }

    private function vehicleReference(): array
    {
        $brand = VehicleBrand::query()->create(['name_ar' => 'Brand '.uniqid(), 'is_active' => true]);
        $model = VehicleModel::query()->create([
            'vehicle_brand_id' => $brand->id, 'name_ar' => 'Model '.uniqid(),
            'start_year' => 2000, 'end_year' => 2030, 'is_active' => true,
        ]);

        return [$brand, $model];
    }
}
