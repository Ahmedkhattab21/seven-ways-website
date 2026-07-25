<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Appointment;
use App\Models\Attachment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\RollMovement;
use App\Models\RollScrap;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\AttachmentService;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\RollScrapService;
use App\Services\RollService;
use App\Services\VehicleInspectionService;
use App\Services\WorkOrderCancellationService;
use App\Services\WorkOrderCreationService;
use App\Services\WorkOrderMaterialIssueService;
use App\Services\WorkOrderMaterialReservationService;
use App\Services\WorkOrderRollConsumptionService;
use App\Services\WorkOrderScrapConsumptionService;
use App\Services\WorkOrderServiceActionService;
use App\Services\WorkOrderTechnicianService;
use App\Services\WorkOrderTimeTrackingService;
use Database\Seeders\WorkOrderSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseTenWorkOrderExecutionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_checked_in_appointment_creates_one_active_order_with_snapshots_and_inspection(): void
    {
        $context = $this->context();
        $order = app(WorkOrderCreationService::class)->fromAppointment($context['appointment'], $context['warehouse']->id);

        $this->assertSame('awaiting_inspection', $order->status);
        $this->assertSame('in_progress', $context['appointment']->fresh()->status);
        $this->assertSame('draft', $order->inspection->status);
        $this->assertSame('Service snapshot', $order->services->first()->description);

        $context['appointment']->forceFill(['status' => 'checked_in'])->save();
        $this->expectException(BusinessRuleException::class);
        app(WorkOrderCreationService::class)->fromAppointment($context['appointment']->fresh(), $context['warehouse']->id);
    }

    public function test_inspection_is_required_for_start_and_completed_inspection_is_immutable(): void
    {
        $context = $this->context();
        $order = $this->order($context);
        $line = $order->services()->first();
        app(WorkOrderTechnicianService::class)->assign($line, $context['employee']);

        try {
            app(WorkOrderServiceActionService::class)->start($line, $context['employee']);
            $this->fail('Start must require inspection.');
        } catch (BusinessRuleException) {
            $this->assertTrue(true);
        }

        $inspection = $order->inspection;
        app(VehicleInspectionService::class)->save($inspection, ['odometer' => 1200], [[
            'section' => 'exterior', 'item_code' => 'body', 'item_name' => 'Body',
            'condition' => 'scratched', 'is_existing_damage' => true,
        ]]);
        app(VehicleInspectionService::class)->complete($inspection->fresh(), 'Customer');
        $this->assertSame('customer_acknowledged', $inspection->fresh()->status);

        $this->expectException(BusinessRuleException::class);
        app(VehicleInspectionService::class)->save($inspection->fresh(), [], []);
    }

    public function test_time_logs_prevent_duplicate_open_sessions_and_pause_resume_close_them(): void
    {
        $context = $this->context();
        $line = $this->order($context)->services()->first();
        app(WorkOrderTechnicianService::class)->assign($line, $context['employee']);
        $tracking = app(WorkOrderTimeTrackingService::class);
        $tracking->open($line, $context['employee']);

        try {
            $tracking->open($line, $context['employee']);
            $this->fail('Duplicate open log must be blocked.');
        } catch (BusinessRuleException) {
            $this->assertTrue(true);
        }
        $closed = $tracking->close($line, $context['employee']);
        $this->assertNotNull($closed->ended_at);
        $this->assertSame(0, $line->timeLogs()->whereNull('ended_at')->count());
    }

    public function test_reservation_does_not_deduct_stock_and_issue_deducts_once_with_cost_snapshot(): void
    {
        $context = $this->context();
        $order = $this->completedInspectionOrder($context);
        $product = $this->product($context);
        app(InventoryService::class)->receive($context['warehouse'], $product, '10', '4.5000', 'stock_opening', ['type' => 'stock_opening', 'id' => 1]);
        $line = $order->materials()->create([
            'work_order_service_id' => $order->services()->first()->id, 'product_id' => $product->id,
            'warehouse_id' => $context['warehouse']->id, 'material_type' => 'quantity',
            'expected_quantity' => 5, 'unit_id' => $product->stock_unit_id,
        ]);

        $this->assertTrue(app(WorkOrderMaterialReservationService::class)->reserve($order));
        $balance = StockBalance::where('warehouse_id', $context['warehouse']->id)->where('product_id', $product->id)->first();
        $this->assertSame('10.000000', $balance->quantity);
        $this->assertSame('5.000000', $balance->reserved_quantity);

        $issues = app(WorkOrderMaterialIssueService::class);
        $issues->issue($line->fresh(), '5');
        $balance->refresh();
        $this->assertSame('5.000000', $balance->quantity);
        $this->assertSame('0.000000', $balance->reserved_quantity);
        $this->assertSame(1, StockMovement::where('movement_type', 'work_order_issue')->where('reference_id', $order->id)->count());
        $this->assertSame('22.5000', $line->fresh()->issued_cost);
        try {
            $issues->issue($line->fresh(), '5');
            $this->fail('A material issue must not be posted twice.');
        } catch (BusinessRuleException) {
            $this->assertSame('5.000000', $balance->fresh()->quantity);
            $this->assertSame(1, StockMovement::where('movement_type', 'work_order_issue')->where('reference_id', $order->id)->count());
        }
        $issues->consume($line->fresh(), '4', '1');
        $this->assertSame('consumed', $line->fresh()->status);
        $this->assertSame('18.0000', $line->fresh()->used_cost);
        $this->assertSame('4.5000', $line->fresh()->waste_cost);
        $this->assertSame('4.5000', $order->fresh()->actual_waste_cost);
    }

    public function test_roll_consumption_and_waste_cost_are_recorded_once(): void
    {
        $context = $this->context();
        $order = $this->completedInspectionOrder($context);
        $product = $this->product($context, 'roll');
        $roll = app(RollService::class)->receive($context['warehouse'], $product, [
            'width' => '1.5', 'original_length' => '20', 'total_cost' => '300',
        ]);
        $line = $order->materials()->create([
            'work_order_service_id' => $order->services()->first()->id,
            'product_id' => $product->id, 'warehouse_id' => $context['warehouse']->id,
            'roll_id' => $roll->id, 'material_type' => 'roll', 'expected_quantity' => 1,
            'unit_id' => $product->stock_unit_id,
        ]);
        app(WorkOrderMaterialReservationService::class)->reserve($order);
        $service = app(WorkOrderRollConsumptionService::class);
        $service->consume($line->fresh(), '5', '7', '0.5');

        $this->assertSame(1, RollMovement::where('reference_type', 'work_order')->where('reference_id', $order->id)->count());
        $this->assertSame(1, StockMovement::where('movement_type', 'roll_consumption')->where('reference_id', $roll->id)->count());
        $this->assertSame('5.0000', $line->fresh()->waste_cost);
        $this->assertSame('5.0000', $order->fresh()->actual_waste_cost);
        $this->assertSame('75.0000', $order->fresh()->actual_total_cost);

        try {
            $service->consume($line->fresh(), '1', '1.5');
            $this->fail('A work-order roll line must not be consumed twice.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, StockMovement::where('movement_type', 'roll_consumption')->where('reference_id', $roll->id)->count());
            $this->assertSame('5.0000', $order->fresh()->actual_waste_cost);
        }
    }

    public function test_scrap_is_consumed_once_without_duplicate_cost(): void
    {
        $context = $this->context();
        $order = $this->completedInspectionOrder($context);
        $product = $this->product($context, 'roll');
        $roll = app(RollService::class)->receive($context['warehouse'], $product, [
            'width' => '1.5', 'original_length' => '20', 'total_cost' => '300',
        ]);
        $scrap = app(RollScrapService::class)->create($roll, '0.5', '2');
        $line = $order->materials()->create([
            'work_order_service_id' => $order->services()->first()->id,
            'product_id' => $product->id, 'warehouse_id' => $context['warehouse']->id,
            'scrap_id' => $scrap->id, 'material_type' => 'scrap', 'expected_quantity' => 1,
            'unit_id' => $product->stock_unit_id,
        ]);
        app(WorkOrderMaterialReservationService::class)->reserve($order);
        $service = app(WorkOrderScrapConsumptionService::class);
        $service->consume($line->fresh());

        $this->assertSame('consumed', RollScrap::find($scrap->id)->status);
        $this->assertSame(1, StockMovement::where('movement_type', 'roll_scrap_consumed')->where('reference_id', $scrap->id)->count());
        $this->assertSame($scrap->total_cost, $order->fresh()->actual_material_cost);

        try {
            $service->consume($line->fresh());
            $this->fail('A scrap line must not be consumed twice.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, StockMovement::where('movement_type', 'roll_scrap_consumed')->where('reference_id', $scrap->id)->count());
            $this->assertSame($scrap->total_cost, $order->fresh()->actual_material_cost);
        }
    }

    public function test_service_completion_closes_logs_calculates_labor_and_stops_at_awaiting_quality(): void
    {
        $context = $this->context();
        $order = $this->completedInspectionOrder($context);
        $line = $order->services()->first();
        app(WorkOrderTechnicianService::class)->assign($line, $context['employee'], ['hourly_cost_snapshot' => 60]);
        $actions = app(WorkOrderServiceActionService::class);
        $actions->start($line, $context['employee']);
        $line->timeLogs()->whereNull('ended_at')->update(['started_at' => now()->subHour()]);
        $actions->complete($line->fresh());

        $this->assertSame('completed', $line->fresh()->status);
        $this->assertSame('awaiting_quality', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->ready_for_quality_at);
        $this->assertSame(0, $line->timeLogs()->whereNull('ended_at')->count());
        $this->assertGreaterThanOrEqual(59, $line->technicians()->first()->worked_minutes);
        $this->assertFalse(\Schema::hasTable('quality_approvals'));
        $this->assertSame(0, \App\Models\SalesInvoice::query()->count());
    }

    public function test_awaiting_quality_requires_all_non_cancelled_services_and_rework_blocks_it(): void
    {
        $context = $this->context();
        $order = $this->completedInspectionOrder($context);
        $first = $order->services()->first();
        $cancelled = $order->services()->create([
            'service_id' => $context['service']->id, 'description' => 'Cancelled line',
            'quantity' => 1, 'status' => 'cancelled', 'planned_duration_minutes' => 10,
            'unit_price_snapshot' => 0, 'total_snapshot' => 0,
        ]);
        app(WorkOrderTechnicianService::class)->assign($first, $context['employee']);
        $actions = app(WorkOrderServiceActionService::class);
        $actions->start($first, $context['employee']);
        $actions->complete($first->fresh());
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame('awaiting_quality', $order->fresh()->status);

        $other = $this->context();
        $blockedOrder = $this->completedInspectionOrder($other);
        $active = $blockedOrder->services()->first();
        $blockedOrder->services()->create([
            'service_id' => $other['service']->id, 'description' => 'Rework line',
            'quantity' => 1, 'status' => 'rework_required', 'planned_duration_minutes' => 10,
            'unit_price_snapshot' => 0, 'total_snapshot' => 0,
        ]);
        app(WorkOrderTechnicianService::class)->assign($active, $other['employee']);
        $actions->start($active, $other['employee']);
        $actions->complete($active->fresh());
        $this->assertSame('in_progress', $blockedOrder->fresh()->status);
        $this->assertNull($blockedOrder->fresh()->ready_for_quality_at);
    }

    public function test_quality_handoff_locks_new_lines_and_reopen_returns_to_execution(): void
    {
        $context = $this->context();
        $order = $this->completedInspectionOrder($context);
        $line = $order->services()->first();
        app(WorkOrderTechnicianService::class)->assign($line, $context['employee']);
        $actions = app(WorkOrderServiceActionService::class);
        $actions->start($line, $context['employee']);
        $actions->complete($line->fresh());

        try {
            $order->services()->create([
                'service_id' => $context['service']->id, 'description' => 'Late service',
                'quantity' => 1, 'planned_duration_minutes' => 10,
                'unit_price_snapshot' => 0, 'total_snapshot' => 0,
            ]);
            $this->fail('Services must be locked after quality handoff.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, $order->services()->count());
        }
        $product = $this->product($context);
        try {
            $order->materials()->create([
                'work_order_service_id' => $line->id, 'product_id' => $product->id,
                'warehouse_id' => $context['warehouse']->id, 'material_type' => 'quantity',
                'expected_quantity' => 1, 'unit_id' => $product->stock_unit_id,
            ]);
            $this->fail('Materials must be locked after quality handoff.');
        } catch (BusinessRuleException) {
            $this->assertSame(0, $order->materials()->count());
        }

        $actions->reopen($line->fresh(), 'Rework requested');
        $this->assertSame('rework_required', $line->fresh()->status);
        $this->assertSame('in_progress', $order->fresh()->status);
        $this->assertNull($order->fresh()->ready_for_quality_at);
        $this->assertNull($order->fresh()->finished_at);
        $actions->start($line->fresh(), $context['employee']);
        $this->assertSame('in_progress', $line->fresh()->status);
    }

    public function test_cancellation_releases_reservations_and_is_blocked_after_awaiting_quality(): void
    {
        $context = $this->context();
        $order = $this->completedInspectionOrder($context);
        $product = $this->product($context);
        app(InventoryService::class)->receive($context['warehouse'], $product, '3', '2', 'stock_opening');
        $order->materials()->create([
            'product_id' => $product->id, 'warehouse_id' => $context['warehouse']->id,
            'material_type' => 'quantity', 'expected_quantity' => 2, 'unit_id' => $product->stock_unit_id,
        ]);
        app(WorkOrderMaterialReservationService::class)->reserve($order);
        app(WorkOrderCancellationService::class)->cancel($order->fresh(), 'Customer request');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('0.000000', StockBalance::where('warehouse_id', $context['warehouse']->id)->where('product_id', $product->id)->value('reserved_quantity'));

        $order->forceFill(['status' => 'awaiting_quality'])->save();
        $this->expectException(BusinessRuleException::class);
        app(WorkOrderCancellationService::class)->cancel($order->fresh(), 'Too late');
    }

    public function test_cancelling_an_order_with_settled_issued_material_preserves_history(): void
    {
        $context = $this->context();
        $order = $this->completedInspectionOrder($context);
        $product = $this->product($context);
        app(InventoryService::class)->receive($context['warehouse'], $product, '2', '3', 'stock_opening');
        $line = $order->materials()->create([
            'work_order_service_id' => $order->services()->first()->id,
            'product_id' => $product->id, 'warehouse_id' => $context['warehouse']->id,
            'material_type' => 'quantity', 'expected_quantity' => 2, 'unit_id' => $product->stock_unit_id,
        ]);
        app(WorkOrderMaterialReservationService::class)->reserve($order);
        $issues = app(WorkOrderMaterialIssueService::class);
        $issues->issue($line->fresh(), '2');
        $issues->consume($line->fresh(), '2');
        $movementIds = StockMovement::where('reference_type', 'work_order')->where('reference_id', $order->id)->pluck('id');

        app(WorkOrderCancellationService::class)->cancel($order->fresh(), 'Customer stopped work');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('consumed', $line->fresh()->status);
        $this->assertEqualsCanonicalizing(
            $movementIds->all(),
            StockMovement::where('reference_type', 'work_order')->where('reference_id', $order->id)->pluck('id')->all()
        );
    }

    public function test_inspection_photos_are_private_and_cross_branch_download_is_forbidden(): void
    {
        Storage::fake('local');
        $context = $this->context();
        $order = $this->order($context);
        $attachment = app(AttachmentService::class)->store(
            $order->inspection,
            $this->fakeImage('customer-signature.png'),
            'inspection_signature'
        );
        $this->assertSame('local', $attachment->disk);
        $this->assertStringStartsWith('private/attachments/', $attachment->path);
        $this->assertNotSame('customer-signature.png', $attachment->stored_name);
        $this->assertStringNotContainsString('customer-signature.png', $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);

        $otherBranch = Branch::create([
            'company_id' => $context['company']->id, 'code' => 'OTHER'.uniqid(),
            'name' => 'Other branch', 'is_main' => false, 'is_active' => true,
        ]);
        $otherBranch->settings()->create(['working_day_start' => '08:00:00', 'working_day_end' => '20:00:00', 'weekend_days' => []]);
        $otherUser = User::factory()->create([
            'company_id' => $context['company']->id, 'branch_id' => $otherBranch->id, 'status' => 'active',
        ]);
        $role = Role::create([
            'company_id' => $context['company']->id, 'name' => 'inspection_viewer_'.uniqid(),
            'display_name' => 'Inspection viewer', 'scope' => 'branch', 'is_active' => true,
        ]);
        $role->permissions()->attach(Permission::firstOrCreate(
            ['name' => 'vehicle_inspections.view'],
            ['display_name' => 'vehicle_inspections.view']
        ));
        $otherUser->roles()->attach($role);
        $otherUser->accessibleBranches()->attach($otherBranch->id, ['is_default' => true, 'can_view' => true]);
        $this->actingAs($otherUser)->get(route('attachments.download', $attachment))->assertForbidden();

        app(TenantContext::class)->initialize($context['user']);
        $this->actingAs($context['user'])->put(route('vehicle-inspections.update', $order->inspection), [
            'odometer' => 500, 'customer_signature_path' => 'data:image/png;base64,unsafe',
            'items' => [[
                'section' => 'exterior', 'item_code' => 'body', 'item_name' => 'Body', 'condition' => 'good',
            ]],
        ])->assertRedirect();
        $this->assertNull($order->inspection->fresh()->customer_signature_path);
        $this->assertSame(1, Attachment::whereKey($attachment->id)->count());
    }

    public function test_permissions_are_seeded_idempotently_without_fake_orders(): void
    {
        $context = $this->context();
        app(WorkOrderSeeder::class)->run();
        app(WorkOrderSeeder::class)->run();
        $this->assertSame(3, DocumentSequence::where('branch_id', $context['branch']->id)->whereIn('document_type', ['work_order', 'vehicle_inspection', 'work_order_waste'])->count());
        $this->assertSame(0, WorkOrder::where('company_id', $context['company']->id)->count());
    }

    public function test_unprivileged_and_cross_company_users_cannot_view_work_order_or_cost(): void
    {
        $context = $this->context();
        $order = $this->order($context);
        $outsider = User::factory()->create(['company_id' => $context['company']->id, 'branch_id' => $context['branch']->id, 'status' => 'active']);
        $this->actingAs($outsider)->get(route('work-orders.show', $order))->assertForbidden();
        $other = $this->context();
        $this->actingAs($other['user'])->get(route('work-orders.show', $order))->assertForbidden();
    }

    private function order(array $context): WorkOrder
    {
        return app(WorkOrderCreationService::class)->fromAppointment($context['appointment'], $context['warehouse']->id);
    }

    private function completedInspectionOrder(array $context): WorkOrder
    {
        $order = $this->order($context);
        app(VehicleInspectionService::class)->save($order->inspection, [], [[
            'section' => 'exterior', 'item_code' => 'body', 'item_name' => 'Body', 'condition' => 'good',
        ]]);
        app(VehicleInspectionService::class)->complete($order->inspection->fresh());

        return $order->fresh();
    }

    private function product(array $context, string $tracking = 'quantity'): Product
    {
        $unit = Unit::query()->forceCreate(['company_id' => $context['company']->id, 'code' => 'U'.uniqid(), 'name' => 'Piece', 'symbol' => 'pc', 'unit_type' => 'quantity', 'decimal_places' => 6, 'is_active' => true]);
        $category = ProductCategory::query()->forceCreate(['company_id' => $context['company']->id, 'code' => 'P'.uniqid(), 'name' => 'Materials', 'is_active' => true]);

        return Product::query()->forceCreate([
            'company_id' => $context['company']->id, 'category_id' => $category->id, 'sku' => 'SKU'.uniqid(),
            'name' => 'Material', 'product_type' => $tracking === 'roll' ? 'ppf' : 'consumable', 'tracking_type' => $tracking,
            'purchase_unit_id' => $unit->id, 'stock_unit_id' => $unit->id, 'sale_unit_id' => $unit->id,
            'costing_method' => $tracking === 'roll' ? 'specific' : 'weighted_average', 'is_consumable' => true, 'is_active' => true,
        ]);
    }

    private function context(): array
    {
        $currency = Currency::firstOrCreate(['code' => 'SAR'], ['name_ar' => 'Riyal', 'name_en' => 'Riyal', 'symbol' => 'SAR', 'decimal_places' => 2, 'is_active' => true]);
        $company = Company::create(['name' => 'Phase Ten '.uniqid(), 'currency_id' => $currency->id, 'is_active' => true]);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'B'.uniqid(), 'name' => 'Branch', 'is_main' => true, 'is_active' => true]);
        $branch->settings()->create(['working_day_start' => '08:00:00', 'working_day_end' => '20:00:00', 'weekend_days' => []]);
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $role = Role::create(['company_id' => $company->id, 'name' => 'company_owner', 'display_name' => 'Owner', 'scope' => 'company', 'is_active' => true]);
        foreach (['work_orders.view', 'work_orders.create', 'work_orders.update', 'work_orders.cancel', 'work_orders.assign_technicians', 'work_orders.start', 'work_orders.pause', 'work_orders.complete', 'work_orders.reopen', 'work_orders.view_cost', 'vehicle_inspections.view', 'vehicle_inspections.update', 'vehicle_inspections.complete', 'vehicle_inspections.manage_photos', 'work_order_materials.view', 'work_order_materials.reserve', 'work_order_materials.issue', 'work_order_materials.return', 'work_order_materials.record_waste'] as $name) {
            $role->permissions()->syncWithoutDetaching(Permission::firstOrCreate(['name' => $name], ['display_name' => $name]));
        }
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch->id, ['is_default' => true, 'can_view' => true]);
        app(TenantContext::class)->initialize($user);
        $customer = Customer::factory()->create(['company_id' => $company->id, 'created_branch_id' => $branch->id, 'assigned_branch_id' => $branch->id]);
        $brand = VehicleBrand::create(['name_ar' => 'Brand', 'is_active' => true]);
        $model = VehicleModel::create(['vehicle_brand_id' => $brand->id, 'name_ar' => 'Model', 'is_active' => true]);
        $vehicle = Vehicle::query()->forceCreate(['company_id' => $company->id, 'customer_id' => $customer->id, 'created_branch_id' => $branch->id, 'vehicle_brand_id' => $brand->id, 'vehicle_model_id' => $model->id, 'plate_number' => 'P'.uniqid(), 'normalized_plate_number' => 'P'.uniqid(), 'status' => 'active']);
        $warehouse = Warehouse::query()->forceCreate(['company_id' => $company->id, 'branch_id' => $branch->id, 'code' => 'W'.uniqid(), 'name' => 'Main', 'warehouse_type' => 'main', 'is_active' => true, 'is_system' => false, 'allows_work_order_issue' => true]);
        $category = ServiceCategory::query()->forceCreate(['company_id' => $company->id, 'code' => 'C'.uniqid(), 'name' => 'Category', 'is_active' => true]);
        $service = Service::query()->forceCreate(['company_id' => $company->id, 'service_category_id' => $category->id, 'code' => 'S'.uniqid(), 'name' => 'Service', 'service_type' => 'ppf', 'pricing_type' => 'fixed', 'default_duration_minutes' => 60, 'requires_vehicle' => true, 'is_active' => true]);
        $employee = Employee::query()->forceCreate(['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'employee_code' => 'E'.uniqid(), 'name' => 'Technician', 'status' => 'active']);
        $appointment = Appointment::query()->forceCreate(['company_id' => $company->id, 'branch_id' => $branch->id, 'appointment_number' => 'APT'.uniqid(), 'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'status' => 'checked_in', 'scheduled_start' => now(), 'scheduled_end' => now()->addHour(), 'estimated_duration_minutes' => 60, 'booking_source' => 'walk_in', 'priority' => 'normal', 'deposit_required' => false, 'deposit_amount' => 0, 'deposit_status' => 'not_required', 'checked_in_at' => now(), 'created_by' => $user->id]);
        $appointment->services()->create(['service_id' => $service->id, 'description' => 'Service snapshot', 'quantity' => 1, 'estimated_duration_minutes' => 60, 'unit_price_snapshot' => 100, 'total_snapshot' => 100]);
        DocumentSequence::query()->forceCreate(['company_id' => $company->id, 'branch_id' => $branch->id, 'document_type' => 'work_order', 'prefix' => '{BRANCH}-WO-{YYYY}-', 'current_number' => 0, 'padding' => 6, 'reset_period' => 'yearly', 'period_key' => now()->format('Y'), 'scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, 'work_order', now()->format('Y')), 'is_active' => true]);
        DocumentSequence::query()->forceCreate(['company_id' => $company->id, 'branch_id' => $branch->id, 'document_type' => 'stock_movement', 'prefix' => '{BRANCH}-MOV-{YYYY}-', 'current_number' => 0, 'padding' => 6, 'reset_period' => 'yearly', 'period_key' => now()->format('Y'), 'scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, 'stock_movement', now()->format('Y')), 'is_active' => true]);
        DocumentSequence::query()->forceCreate(['company_id' => $company->id, 'branch_id' => $branch->id, 'document_type' => 'roll', 'prefix' => '{BRANCH}-ROLL-{YYYY}-', 'current_number' => 0, 'padding' => 6, 'reset_period' => 'yearly', 'period_key' => now()->format('Y'), 'scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, 'roll', now()->format('Y')), 'is_active' => true]);
        DocumentSequence::query()->forceCreate(['company_id' => $company->id, 'branch_id' => $branch->id, 'document_type' => 'roll_scrap', 'prefix' => '{BRANCH}-SCR-{YYYY}-', 'current_number' => 0, 'padding' => 6, 'reset_period' => 'yearly', 'period_key' => now()->format('Y'), 'scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, 'roll_scrap', now()->format('Y')), 'is_active' => true]);

        return compact('company', 'branch', 'user', 'customer', 'vehicle', 'warehouse', 'service', 'employee', 'appointment');
    }
}
