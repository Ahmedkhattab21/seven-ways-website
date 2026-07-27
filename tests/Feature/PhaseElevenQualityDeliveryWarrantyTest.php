<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Models\Warehouse;
use App\Models\Warranty;
use App\Models\WorkOrder;
use App\Services\AttachmentService;
use App\Services\DeliveryInspectionService;
use App\Services\DocumentNumberService;
use App\Services\QualityCheckDecisionService;
use App\Services\QualityChecklistService;
use App\Services\QualityCheckService;
use App\Services\ReworkExecutionService;
use App\Services\VehicleInspectionService;
use App\Services\WarrantyClaimDecisionService;
use App\Services\WarrantyClaimInspectionService;
use App\Services\WarrantyClaimReworkService;
use App\Services\WarrantyClaimService;
use App\Services\WarrantyVerificationService;
use App\Services\WorkOrderCreationService;
use App\Services\WorkOrderDeliveryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseElevenQualityDeliveryWarrantyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_quality_starts_only_for_awaiting_order_and_snapshots_template(): void
    {
        $context = $this->context();
        $order = $this->order($context);
        $template = $this->template($context);

        try {
            app(QualityCheckService::class)->start($order, $template);
            $this->fail('Quality must not start before awaiting_quality.');
        } catch (BusinessRuleException) {
            $this->assertTrue(true);
        }

        $this->makeAwaitingQuality($order);
        $check = app(QualityCheckService::class)->start($order->fresh(), $template);
        $this->assertSame(1, $check->round_number);
        $this->assertSame($template->version, $check->template_version);
        $this->assertSame('NO_BUBBLES', $check->items->first()->code);

        $this->expectException(BusinessRuleException::class);
        $template->forceFill(['name' => 'Mutated'])->save();
    }

    public function test_critical_failure_and_required_items_block_pass_and_failure_creates_rework(): void
    {
        Storage::fake('local');
        $context = $this->context();
        $order = $this->makeAwaitingQuality($this->order($context));
        $check = app(QualityCheckService::class)->start($order, $this->template($context));

        try {
            app(QualityCheckDecisionService::class)->pass($check);
            $this->fail('Pending required items must block pass.');
        } catch (BusinessRuleException) {
            $this->assertTrue(true);
        }

        app(QualityCheckService::class)->updateItems($check, [[
            'id' => $check->items->first()->id, 'result' => 'failed', 'notes' => 'Bubble found',
        ]]);
        try {
            app(QualityCheckDecisionService::class)->fail($check->fresh(), 'Critical failure');
            $this->fail('Failure photo must be required.');
        } catch (BusinessRuleException) {
            $this->assertTrue(true);
        }
        app(AttachmentService::class)->store(
            $check,
            $this->fakeImage('failure.jpg'),
            'quality_failure'
        );
        app(QualityCheckDecisionService::class)->fail($check->fresh(), 'Critical failure');

        $this->assertSame('failed', $check->fresh()->status);
        $this->assertSame('in_progress', $order->fresh()->status);
        $this->assertSame('rework_required', $order->services()->first()->status);
        $this->assertSame(1, $order->reworkOrders()->count());
    }

    public function test_technician_cannot_approve_own_work_but_quality_pass_makes_order_ready(): void
    {
        $context = $this->context();
        $order = $this->makeAwaitingQuality($this->order($context));
        $serviceLine = $order->services()->first();
        $serviceLine->technicians()->create([
            'employee_id' => $context['employee']->id, 'role' => 'technician', 'is_primary' => true,
            'assigned_at' => now(), 'worked_minutes' => 0, 'labor_cost' => 0,
            'status' => 'completed', 'assigned_by' => $context['operator']->id,
        ]);
        $template = $this->template($context);
        $check = app(QualityCheckService::class)->start($order, $template);
        app(QualityCheckService::class)->updateItems($check, [[
            'id' => $check->items->first()->id, 'result' => 'passed',
        ]]);

        try {
            app(QualityCheckDecisionService::class)->pass($check->fresh());
            $this->fail('The assigned technician must not approve their work.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame(403, $exception->status());
        }

        $this->asUser($context['qualityUser']);
        app(QualityCheckDecisionService::class)->pass($check->fresh());
        $this->assertSame('ready_for_delivery', $order->fresh()->status);
        $this->assertSame('passed', $check->fresh()->status);
    }

    public function test_rework_completion_preserves_history_and_requires_a_new_quality_round(): void
    {
        Storage::fake('local');
        $context = $this->context();
        $order = $this->makeAwaitingQuality($this->order($context));
        $check = app(QualityCheckService::class)->start($order, $this->template($context));
        app(QualityCheckService::class)->updateItems($check, [[
            'id' => $check->items->first()->id, 'result' => 'failed',
        ]]);
        app(AttachmentService::class)->store($check, $this->fakeImage('failure.jpg'), 'quality_failure');
        app(QualityCheckDecisionService::class)->fail($check->fresh(), 'Rework needed');
        $rework = $order->reworkOrders()->first();
        $execution = app(ReworkExecutionService::class);
        $execution->approve($rework);
        $execution->start($rework->fresh());
        $execution->completeService($rework->fresh(), $rework->services()->first()->id);
        $execution->complete($rework->fresh());

        $this->assertSame('awaiting_quality', $order->fresh()->status);
        $this->assertSame('failed', $check->fresh()->status);
        $roundTwo = app(QualityCheckService::class)->start($order->fresh(), $check->template);
        $this->assertSame(2, $roundTwo->round_number);
    }

    public function test_delivery_requires_quality_private_photos_and_signature_then_issues_warranty_once(): void
    {
        Storage::fake('local');
        $context = $this->context();
        $order = $this->makeAwaitingQuality($this->order($context));
        try {
            app(DeliveryInspectionService::class)->create($order);
            $this->fail('Delivery inspection must require quality pass.');
        } catch (BusinessRuleException) {
            $this->assertTrue(true);
        }
        $check = app(QualityCheckService::class)->start($order, $this->template($context));
        app(QualityCheckService::class)->updateItems($check, [['id' => $check->items->first()->id, 'result' => 'passed']]);
        app(QualityCheckDecisionService::class)->pass($check->fresh());
        $inspection = app(DeliveryInspectionService::class)->create($order->fresh());
        app(VehicleInspectionService::class)->save($inspection, ['receiver_name' => 'Customer'], [[
            'section' => 'exterior', 'item_code' => 'final', 'item_name' => 'Final condition', 'condition' => 'good',
        ]]);
        app(AttachmentService::class)->store($inspection, $this->fakeImage('after.jpg'), 'delivery_overview');
        app(AttachmentService::class)->store($inspection, $this->fakeImage('signature.png'), 'delivery_signature');
        app(VehicleInspectionService::class)->complete($inspection->fresh(), 'Customer');
        app(WorkOrderDeliveryService::class)->deliver($order->fresh(), ['receiver_name' => 'Customer']);

        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('completed', $context['appointment']->fresh()->status);
        $this->assertSame(1, Warranty::where('work_order_id', $order->id)->count());
        app(\App\Services\WarrantyIssuanceService::class)->issueForWorkOrder($order->fresh());
        $this->assertSame(1, Warranty::where('work_order_id', $order->id)->count());
        $this->assertStringStartsWith('private/attachments/', $order->fresh()->delivery_signature_path);
    }

    public function test_public_warranty_verification_is_rate_limited_and_does_not_leak_sensitive_data(): void
    {
        Storage::fake('local');
        $context = $this->context();
        $warranty = $this->deliveredWarranty($context);
        $verification = app(WarrantyVerificationService::class)->verify($warranty->qr_token);

        $this->assertArrayNotHasKey('customer', $verification);
        $this->assertArrayNotHasKey('cost', $verification);
        $this->assertArrayNotHasKey('vehicle_id', $verification);
        $response = $this->get(route('warranties.verify', $warranty->qr_token));
        $response->assertOk()->assertDontSee($context['customer']->phone ?? '');
        $route = app('router')->getRoutes()->getByName('warranties.verify');
        $this->assertContains('throttle:30,1', $route->gatherMiddleware());
    }

    public function test_warranty_claim_requires_own_items_and_inspection_photo_then_creates_resolvable_rework(): void
    {
        Storage::fake('local');
        $context = $this->context();
        $warranty = $this->deliveredWarranty($context);
        $item = $warranty->items()->first();
        $claim = app(WarrantyClaimService::class)->create($warranty, ['complaint' => 'Peeling'], [[
            'warranty_item_id' => $item->id, 'issue_type' => 'peeling', 'description' => 'Edge peeling',
        ]]);
        app(AttachmentService::class)->store($claim, $this->fakeImage('claim.jpg'), 'warranty_claim_photo');
        app(WarrantyClaimInspectionService::class)->inspect($claim, [[
            'id' => $claim->items()->first()->id, 'inspection_result' => 'installation_defect',
        ]]);
        app(WarrantyClaimDecisionService::class)->decide($claim->fresh(), 'covered', [[
            'id' => $claim->items()->first()->id, 'coverage_percentage' => 100,
        ]]);
        $rework = app(WarrantyClaimReworkService::class)->create($claim->fresh());
        $execution = app(ReworkExecutionService::class);
        $execution->start($rework);
        $execution->completeService($rework->fresh(), $rework->services()->first()->id);
        $execution->complete($rework->fresh());

        $this->assertSame('under_review', $claim->fresh()->status);
        app(AttachmentService::class)->store($rework->fresh(), $this->fakeImage('final.jpg'), 'rework_after');
        app(WarrantyClaimDecisionService::class)->resolve($claim->fresh());
        $this->assertSame('resolved', $claim->fresh()->status);
        $this->assertSame('delivered', $warranty->workOrder->fresh()->status);
        $this->assertSame(1, $claim->reworkOrders()->count());
    }

    public function test_cross_company_quality_warranty_and_claim_access_is_forbidden(): void
    {
        Storage::fake('local');
        $qualityContext = $this->context();
        $order = $this->makeAwaitingQuality($this->order($qualityContext));
        $warrantyContext = $this->context();
        $warranty = $this->deliveredWarranty($warrantyContext);
        $second = $this->context();

        $this->actingAs($second['qualityUser'])->get(route('warranties.show', $warranty))->assertForbidden();
        $this->actingAs($second['qualityUser'])->post(route('quality-checks.start', $order))->assertForbidden();
    }

    private function deliveredWarranty(array $context): Warranty
    {
        $order = $this->makeAwaitingQuality($this->order($context));
        $check = app(QualityCheckService::class)->start($order, $this->template($context));
        app(QualityCheckService::class)->updateItems($check, [['id' => $check->items->first()->id, 'result' => 'passed']]);
        $this->asUser($context['qualityUser']);
        app(QualityCheckDecisionService::class)->pass($check->fresh());
        $inspection = app(DeliveryInspectionService::class)->create($order->fresh());
        app(VehicleInspectionService::class)->save($inspection, [], [[
            'section' => 'exterior', 'item_code' => 'final', 'item_name' => 'Final', 'condition' => 'good',
        ]]);
        app(AttachmentService::class)->store($inspection, $this->fakeImage('after.jpg'), 'delivery_overview');
        app(AttachmentService::class)->store($inspection, $this->fakeImage('signature.png'), 'delivery_signature');
        app(VehicleInspectionService::class)->complete($inspection->fresh(), 'Customer');
        app(WorkOrderDeliveryService::class)->deliver($order->fresh(), ['receiver_name' => 'Customer']);

        return Warranty::where('work_order_id', $order->id)->firstOrFail();
    }

    private function template(array $context)
    {
        $existing = \App\Models\QualityChecklistTemplate::where('company_id', $context['company']->id)->first();
        if ($existing) {
            return $existing;
        }

        return app(QualityChecklistService::class)->createVersion([
            'code' => 'QC', 'name' => 'Quality', 'is_default' => true, 'is_active' => true,
        ], [[
            'code' => 'NO_BUBBLES', 'name' => 'No bubbles', 'category' => 'finish',
            'check_type' => 'pass_fail', 'is_required' => true, 'is_critical' => true,
            'requires_photo_on_failure' => true,
        ]]);
    }

    private function makeAwaitingQuality(WorkOrder $order): WorkOrder
    {
        $order->services()->update(['status' => 'completed', 'completed_at' => now()]);
        $order->forceFill(['status' => 'awaiting_quality', 'ready_for_quality_at' => now()])->save();

        return $order->fresh();
    }

    private function order(array $context): WorkOrder
    {
        return app(WorkOrderCreationService::class)->fromAppointment($context['appointment'], $context['warehouse']->id);
    }

    private function asUser(User $user): void
    {
        $this->actingAs($user);
        app(TenantContext::class)->initialize($user);
    }

    private function context(): array
    {
        $currency = Currency::firstOrCreate(['code' => 'EGP'], ['name_ar' => 'جنيه مصري', 'name_en' => 'Egyptian Pound', 'symbol' => 'ج.م', 'decimal_places' => 2, 'is_active' => true]);
        $company = Company::create(['name' => 'Phase Eleven '.uniqid(), 'currency_id' => $currency->id, 'is_active' => true]);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'B'.uniqid(), 'name' => 'Branch', 'is_main' => true, 'is_active' => true]);
        $branch->settings()->create(['working_day_start' => '08:00:00', 'working_day_end' => '20:00:00', 'weekend_days' => []]);
        $operator = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $qualityUser = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $permissions = [
            'work_orders.view', 'work_orders.create', 'work_orders.deliver',
            'vehicle_inspections.view', 'vehicle_inspections.update', 'vehicle_inspections.complete',
            'vehicle_inspections.manage_photos', 'vehicle_inspections.delivery', 'vehicle_inspections.delivery_photos',
            'quality_checks.view', 'quality_checks.create', 'quality_checks.perform', 'quality_checks.pass',
            'quality_checks.fail', 'quality_checks.override', 'quality_checks.manage_templates',
            'rework_orders.view', 'rework_orders.create', 'rework_orders.approve', 'rework_orders.start',
            'rework_orders.complete', 'rework_orders.view_cost', 'warranties.view', 'warranties.issue',
            'warranties.print', 'warranties.void', 'warranty_claims.view', 'warranty_claims.create',
            'warranty_claims.inspect', 'warranty_claims.decide', 'warranty_claims.approve',
            'warranty_claims.resolve', 'warranty_claims.view_cost',
        ];
        $role = Role::create(['company_id' => $company->id, 'name' => 'phase11_'.uniqid(), 'display_name' => 'Phase 11', 'scope' => 'company', 'is_active' => true]);
        foreach ($permissions as $name) {
            $role->permissions()->syncWithoutDetaching(Permission::firstOrCreate(['name' => $name], ['display_name' => $name]));
        }
        $operator->roles()->attach($role);
        $qualityUser->roles()->attach($role);
        $operator->accessibleBranches()->attach($branch->id, ['is_default' => true, 'can_view' => true]);
        $qualityUser->accessibleBranches()->attach($branch->id, ['is_default' => true, 'can_view' => true]);
        $this->asUser($operator);
        $customer = Customer::factory()->create(['company_id' => $company->id, 'created_branch_id' => $branch->id, 'assigned_branch_id' => $branch->id]);
        $brand = VehicleBrand::create(['name_ar' => 'Brand', 'is_active' => true]);
        $model = VehicleModel::create(['vehicle_brand_id' => $brand->id, 'name_ar' => 'Model', 'is_active' => true]);
        $vehicle = Vehicle::query()->forceCreate(['company_id' => $company->id, 'customer_id' => $customer->id, 'created_branch_id' => $branch->id, 'vehicle_brand_id' => $brand->id, 'vehicle_model_id' => $model->id, 'plate_number' => 'PLT'.uniqid(), 'normalized_plate_number' => 'PLT'.uniqid(), 'vin' => 'VIN12345678901234', 'status' => 'active']);
        $warehouse = Warehouse::query()->forceCreate(['company_id' => $company->id, 'branch_id' => $branch->id, 'code' => 'W'.uniqid(), 'name' => 'Main', 'warehouse_type' => 'main', 'is_active' => true, 'is_system' => false, 'allows_work_order_issue' => true]);
        $category = ServiceCategory::query()->forceCreate(['company_id' => $company->id, 'code' => 'C'.uniqid(), 'name' => 'Category', 'is_active' => true]);
        $service = Service::query()->forceCreate(['company_id' => $company->id, 'service_category_id' => $category->id, 'code' => 'S'.uniqid(), 'name' => 'PPF', 'service_type' => 'ppf', 'pricing_type' => 'fixed', 'default_duration_minutes' => 60, 'default_warranty_months' => 12, 'requires_vehicle' => true, 'requires_quality_check' => true, 'is_active' => true]);
        $employee = Employee::query()->forceCreate(['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $operator->id, 'employee_code' => 'E'.uniqid(), 'name' => 'Technician', 'status' => 'active']);
        $appointment = Appointment::query()->forceCreate(['company_id' => $company->id, 'branch_id' => $branch->id, 'appointment_number' => 'APT'.uniqid(), 'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'status' => 'checked_in', 'scheduled_start' => now(), 'scheduled_end' => now()->addHour(), 'estimated_duration_minutes' => 60, 'booking_source' => 'walk_in', 'priority' => 'normal', 'deposit_required' => false, 'deposit_amount' => 0, 'deposit_status' => 'not_required', 'checked_in_at' => now(), 'created_by' => $operator->id]);
        $appointment->services()->create(['service_id' => $service->id, 'description' => 'PPF service', 'quantity' => 1, 'estimated_duration_minutes' => 60, 'unit_price_snapshot' => 100, 'total_snapshot' => 100]);
        foreach ([
            'work_order' => '{BRANCH}-WO-{YYYY}-', 'quality_check' => '{BRANCH}-QC-{YYYY}-',
            'rework_order' => '{BRANCH}-RW-{YYYY}-', 'warranty' => '{BRANCH}-WAR-{YYYY}-',
            'warranty_claim' => '{BRANCH}-WCL-{YYYY}-',
        ] as $type => $prefix) {
            DocumentSequence::query()->forceCreate(['company_id' => $company->id, 'branch_id' => $branch->id, 'document_type' => $type, 'prefix' => $prefix, 'current_number' => 0, 'padding' => 6, 'reset_period' => 'yearly', 'period_key' => now()->format('Y'), 'scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, $type, now()->format('Y')), 'is_active' => true]);
        }

        return compact('company', 'branch', 'operator', 'qualityUser', 'customer', 'vehicle', 'warehouse', 'service', 'employee', 'appointment');
    }
}
