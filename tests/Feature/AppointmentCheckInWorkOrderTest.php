<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleInspection;
use App\Models\VehicleModel;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\AppointmentCheckInService;
use App\Services\AppointmentService;
use App\Services\BranchSettingsService;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentCheckInWorkOrderTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableModules('appointments', 'work_orders');
    }

    public function test_branch_default_accepts_only_an_eligible_work_order_warehouse(): void
    {
        $context = $this->context();
        $eligible = $this->warehouse($context);
        $invalid = $this->warehouse($context, ['allows_work_order_issue' => false]);

        $service = app(BranchSettingsService::class);
        $service->update($context['branch'], [
            'default_work_order_warehouse_id' => $eligible->id,
        ]);

        $this->assertSame(
            $eligible->id,
            $context['branch']->settings()->value('default_work_order_warehouse_id')
        );

        $this->expectException(ValidationException::class);
        $service->update($context['branch'], [
            'default_work_order_warehouse_id' => $invalid->id,
        ]);
    }

    public function test_check_in_requires_a_configured_default_warehouse_and_rolls_back(): void
    {
        $context = $this->context();
        $appointment = $this->appointment($context, 'confirmed');

        try {
            app(AppointmentCheckInService::class)->checkIn($appointment, []);
            $this->fail('Check-in should require a default work-order warehouse.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('مستودع افتراضي', $exception->getMessage());
        }

        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertNull($appointment->fresh()->checked_in_at);
        $this->assertSame(0, WorkOrder::query()->where('appointment_id', $appointment->id)->count());
    }

    public function test_check_in_atomically_creates_one_work_order_without_inventory_or_accounting(): void
    {
        $context = $this->context();
        $warehouse = $this->warehouse($context);
        $context['branch']->settings()->update([
            'default_work_order_warehouse_id' => $warehouse->id,
        ]);
        $this->workOrderSequence($context);
        $appointment = $this->appointment($context, 'pending');
        $stockMovementsBefore = DB::table('stock_movements')->count();
        $journalEntriesBefore = DB::table('journal_entries')->count();

        $first = app(AppointmentCheckInService::class)->checkIn($appointment, [
            'arrival_notes' => 'وصل العميل',
            'odometer_snapshot' => 1000,
        ]);
        $second = app(AppointmentCheckInService::class)->checkIn($appointment->fresh(), []);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('in_progress', $appointment->fresh()->status);
        $this->assertSame($appointment->id, $first->appointment_id);
        $this->assertSame($context['branch']->id, $first->branch_id);
        $this->assertSame($context['customer']->id, $first->customer_id);
        $this->assertSame($context['vehicle']->id, $first->vehicle_id);
        $this->assertSame($warehouse->id, $first->warehouse_id);
        $this->assertSame(1, $first->services()->count());
        $this->assertSame(1, VehicleInspection::query()->where('work_order_id', $first->id)->count());
        $this->assertSame(1, WorkOrder::query()->where('appointment_id', $appointment->id)->count());
        $this->assertSame($stockMovementsBefore, DB::table('stock_movements')->count());
        $this->assertSame($journalEntriesBefore, DB::table('journal_entries')->count());
    }

    public function test_AppointmentRecovery_preserves_arrival_data_and_is_idempotent(): void
    {
        $context = $this->context();
        $warehouse = $this->warehouse($context);
        $context['branch']->settings()->update([
            'default_work_order_warehouse_id' => $warehouse->id,
        ]);
        $this->workOrderSequence($context);
        $appointment = $this->appointment($context, 'checked_in');
        $checkedInAt = now()->subHour()->startOfSecond();
        $appointment->forceFill([
            'checked_in_at' => $checkedInAt,
            'arrival_notes' => 'بيانات وصول محفوظة',
            'odometer_snapshot' => 43210,
        ])->save();

        $first = app(AppointmentCheckInService::class)->checkIn($appointment, [
            'arrival_notes' => 'يجب تجاهلها',
            'odometer_snapshot' => 1,
        ]);
        $second = app(AppointmentCheckInService::class)->checkIn($appointment->fresh(), []);
        $recovered = $appointment->fresh();

        $this->assertSame($first->id, $second->id);
        $this->assertSame('in_progress', $recovered->status);
        $this->assertTrue($checkedInAt->equalTo($recovered->checked_in_at));
        $this->assertSame('بيانات وصول محفوظة', $recovered->arrival_notes);
        $this->assertSame(43210, $recovered->odometer_snapshot);
        $this->assertSame(1, WorkOrder::query()->where('appointment_id', $appointment->id)->count());
        $this->assertSame(1, VehicleInspection::query()->where('work_order_id', $first->id)->count());
        $this->assertTrue(AuditLog::query()
            ->where('event', 'appointment.work_order_recovered')
            ->where('auditable_type', $appointment->getMorphClass())
            ->where('auditable_id', $appointment->id)
            ->exists());
    }

    public function test_missing_work_order_sequence_rolls_back_check_in_data(): void
    {
        $context = $this->context();
        $warehouse = $this->warehouse($context);
        $context['branch']->settings()->update([
            'default_work_order_warehouse_id' => $warehouse->id,
        ]);
        $appointment = $this->appointment($context, 'confirmed');
        $inspectionsBefore = VehicleInspection::query()->count();

        try {
            app(AppointmentCheckInService::class)->checkIn($appointment, [
                'arrival_notes' => 'لا يجب حفظها',
                'odometer_snapshot' => 500,
            ]);
            $this->fail('Check-in should require a work-order sequence.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('تسلسل أرقام أوامر العمل', $exception->getMessage());
        }

        $appointment->refresh();
        $this->assertSame('confirmed', $appointment->status);
        $this->assertNull($appointment->checked_in_at);
        $this->assertNull($appointment->arrival_notes);
        $this->assertNull($appointment->odometer_snapshot);
        $this->assertSame(0, WorkOrder::query()->where('appointment_id', $appointment->id)->count());
        $this->assertSame($inspectionsBefore, VehicleInspection::query()->count());
    }

    private function context(): array
    {
        $currency = Currency::query()->firstOrCreate(['code' => 'EGP'], [
            'name_ar' => 'جنيه مصري',
            'name_en' => 'Egyptian Pound',
            'symbol' => 'ج.م',
            'decimal_places' => 2,
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'name' => 'Appointment Check-in '.uniqid(),
            'currency_id' => $currency->id,
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'ACI-'.uniqid(),
            'name' => 'فرع اختبار تسجيل الوصول',
            'is_main' => true,
            'is_active' => true,
        ]);
        $branch->settings()->create([
            'working_day_start' => '08:00:00',
            'working_day_end' => '20:00:00',
            'weekend_days' => [],
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $user->accessibleBranches()->attach($branch->id, [
            'is_default' => true,
            'can_view' => true,
        ]);
        app(TenantContext::class)->initialize($user);
        $customer = Customer::factory()->create([
            'company_id' => $company->id,
            'created_branch_id' => $branch->id,
            'assigned_branch_id' => $branch->id,
        ]);
        $brand = VehicleBrand::query()->create(['name_ar' => 'علامة', 'is_active' => true]);
        $model = VehicleModel::query()->create([
            'vehicle_brand_id' => $brand->id,
            'name_ar' => 'موديل',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::query()->forceCreate([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'created_branch_id' => $branch->id,
            'vehicle_brand_id' => $brand->id,
            'vehicle_model_id' => $model->id,
            'plate_number' => 'ACI-'.uniqid(),
            'normalized_plate_number' => 'ACI-'.uniqid(),
            'status' => 'active',
        ]);
        $category = ServiceCategory::query()->forceCreate([
            'company_id' => $company->id,
            'code' => 'ACI-'.uniqid(),
            'name' => 'خدمات الاختبار',
            'is_active' => true,
        ]);
        $tax = Tax::query()->forceCreate([
            'company_id' => $company->id,
            'code' => 'ACI-VAT-'.uniqid(),
            'name' => 'ضريبة اختبار',
            'rate' => 14,
            'tax_type' => 'vat',
            'is_active' => true,
        ]);
        $service = Service::query()->forceCreate([
            'company_id' => $company->id,
            'service_category_id' => $category->id,
            'code' => 'ACI-S-'.uniqid(),
            'name' => 'خدمة اختبار',
            'service_type' => 'ppf',
            'pricing_type' => 'fixed',
            'default_duration_minutes' => 60,
            'default_tax_id' => $tax->id,
            'requires_vehicle' => true,
            'is_active' => true,
        ]);
        BranchService::query()->forceCreate([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'is_available' => true,
            'default_price' => 100,
            'minimum_price' => 80,
            'is_active' => true,
        ]);
        DocumentSequence::query()->forceCreate([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'document_type' => 'appointment',
            'prefix' => 'ACI-APT-',
            'current_number' => 0,
            'padding' => 6,
            'reset_period' => 'yearly',
            'period_key' => now()->format('Y'),
            'scope_key' => DocumentNumberService::scopeKey(
                $company->id,
                $branch->id,
                'appointment',
                now()->format('Y')
            ),
            'is_active' => true,
        ]);

        return compact('company', 'branch', 'user', 'customer', 'vehicle', 'service');
    }

    private function appointment(array $context, string $status)
    {
        $appointment = app(AppointmentService::class)->save([
            'branch_id' => $context['branch']->id,
            'customer_id' => $context['customer']->id,
            'vehicle_id' => $context['vehicle']->id,
            'scheduled_start' => now()->addDay()->setTime(10, 0),
            'scheduled_end' => now()->addDay()->setTime(11, 0),
            'booking_source' => 'walk_in',
            'priority' => 'normal',
            'deposit_required' => false,
            'deposit_amount' => 0,
        ], [[
            'service_id' => $context['service']->id,
            'description' => 'خدمة اختبار',
            'quantity' => 1,
            'estimated_duration_minutes' => 60,
            'unit_price_snapshot' => 100,
            'total_snapshot' => 100,
        ]]);
        $appointment->forceFill(['status' => $status])->save();

        return $appointment;
    }

    private function warehouse(array $context, array $overrides = []): Warehouse
    {
        return Warehouse::query()->forceCreate(array_merge([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'code' => 'ACI-W-'.uniqid(),
            'name' => 'مستودع أوامر العمل',
            'warehouse_type' => 'normal',
            'is_active' => true,
            'is_system' => false,
            'allows_work_order_issue' => true,
        ], $overrides));
    }

    private function workOrderSequence(array $context): void
    {
        DocumentSequence::query()->forceCreate([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'document_type' => 'work_order',
            'prefix' => 'ACI-WO-',
            'current_number' => 0,
            'padding' => 6,
            'reset_period' => 'yearly',
            'period_key' => now()->format('Y'),
            'scope_key' => DocumentNumberService::scopeKey(
                $context['company']->id,
                $context['branch']->id,
                'work_order',
                now()->format('Y')
            ),
            'is_active' => true,
        ]);
    }
}
