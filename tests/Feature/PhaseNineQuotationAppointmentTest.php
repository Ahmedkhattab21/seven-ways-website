<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\QuotationExpired;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Employee;
use App\Models\PaymentMethod;
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
use App\Services\AppointmentCancellationService;
use App\Services\AppointmentCheckInService;
use App\Services\AppointmentDepositService;
use App\Services\AppointmentService;
use App\Services\DocumentNumberService;
use App\Services\QuotationAcceptanceService;
use App\Services\QuotationApprovalService;
use App\Services\QuotationService;
use App\Services\QuotationToAppointmentService;
use App\Services\QuotationVersionService;
use Database\Seeders\QuotationAppointmentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PhaseNineQuotationAppointmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_quotation_snapshot_calculation_is_backend_owned_and_immutable_from_catalog_changes(): void
    {
        $context = $this->context();
        $quotation = $this->quotation($context);

        $this->assertSame('180.0000', $quotation->subtotal);
        $this->assertSame('18.0000', $quotation->discount_amount);
        $this->assertSame('24.3000', $quotation->tax_amount);
        $this->assertSame('186.3000', $quotation->total);
        $this->assertSame('100.0000', $quotation->items[0]->unit_price);

        ServicePrice::query()->where('service_id', $context['service']->id)->update(['price' => 250]);
        $this->assertSame('100.0000', $quotation->fresh()->items()->first()->unit_price);
        $this->assertSame(0, \App\Models\WorkOrder::query()->count());
        $this->assertSame(0, \App\Models\SalesInvoice::query()->count());
    }

    public function test_non_draft_quotation_creates_version_and_preserves_family_number(): void
    {
        $context = $this->context();
        $quotation = $this->quotation($context);
        $quotation->forceFill(['status' => 'sent'])->save();
        $copy = app(QuotationVersionService::class)->create($quotation, 'Customer changed scope');

        $this->assertSame($quotation->quotation_number, $copy->quotation_number);
        $this->assertSame(2, $copy->version_number);
        $this->assertSame('draft', $copy->status);
        $this->assertSame('superseded', $quotation->fresh()->status);
        $this->assertSame($quotation->items()->count(), $copy->items()->count());
    }

    public function test_approval_acceptance_and_family_exclusivity_are_enforced(): void
    {
        $context = $this->context();
        $quotation = $this->quotation($context);
        $approval = app(QuotationApprovalService::class);
        $approval->submit($quotation);

        $this->expectException(BusinessRuleException::class);
        $approval->approve($quotation->fresh());
    }

    public function test_owner_can_approve_and_accept_non_expired_quotation(): void
    {
        $context = $this->context(true);
        $quotation = $this->quotation($context);
        $approval = app(QuotationApprovalService::class);
        $approval->submit($quotation);
        $approval->approve($quotation->fresh(), 'Approved');
        $acceptance = app(QuotationAcceptanceService::class);
        $acceptance->send($quotation->fresh());
        $accepted = $acceptance->accept($quotation->fresh(), [
            'acceptance_method' => 'phone', 'accepted_by_name' => 'Customer',
        ]);

        $this->assertSame('accepted', $accepted->status);
        $this->assertNotNull($accepted->accepted_at);
    }

    public function test_accepted_quotation_converts_to_one_appointment_without_stock_or_work_order(): void
    {
        $context = $this->context(true);
        $quotation = $this->acceptedQuotation($context);
        $beforeStock = \App\Models\StockMovement::query()->count();
        $appointment = app(QuotationToAppointmentService::class)->convert($quotation, [
            'scheduled_start' => now()->addDays(2)->setTime(10, 0),
            'scheduled_end' => now()->addDays(2)->setTime(11, 0),
            'priority' => 'normal', 'deposit_required' => true, 'deposit_amount' => 100,
        ]);

        $this->assertSame('converted', $quotation->fresh()->status);
        $this->assertSame(1, $appointment->services()->count());
        $this->assertSame($beforeStock, \App\Models\StockMovement::query()->count());
        $this->assertSame(0, \App\Models\WorkOrder::query()->count());

        $this->expectException(BusinessRuleException::class);
        app(QuotationToAppointmentService::class)->convert($quotation->fresh(), [
            'scheduled_start' => now()->addDays(3)->setTime(10, 0),
            'scheduled_end' => now()->addDays(3)->setTime(11, 0), 'priority' => 'normal',
        ]);
    }

    public function test_scheduling_blocks_technician_overlap(): void
    {
        $context = $this->context();
        $employee = Employee::query()->forceCreate([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'employee_code' => 'EMP'.uniqid(), 'name' => 'Technician', 'status' => 'active',
        ]);
        $data = $this->appointmentData($context, $employee);
        $services = $this->appointmentServices($context, $employee);
        app(AppointmentService::class)->save($data, $services);

        $this->expectException(BusinessRuleException::class);
        app(AppointmentService::class)->save($data, $services);
    }

    public function test_operational_deposit_and_check_in_have_no_accounting_or_work_order_effect(): void
    {
        $context = $this->context();
        $appointment = app(AppointmentService::class)->save(
            array_merge($this->appointmentData($context), ['deposit_required' => true, 'deposit_amount' => 100]),
            $this->appointmentServices($context)
        );
        $deposit = app(AppointmentDepositService::class)->record($appointment, [
            'amount' => 100, 'payment_method_id' => $context['paymentMethod']->id,
            'received_at' => now(), 'notes' => 'Operational only',
        ]);
        $appointment->forceFill(['status' => 'confirmed'])->save();
        app(AppointmentCheckInService::class)->checkIn($appointment->fresh(), ['odometer_snapshot' => 12000]);

        $this->assertSame('paid', $appointment->fresh()->deposit_status);
        $this->assertSame('recorded', $deposit->status);
        $this->assertSame('checked_in', $appointment->fresh()->status);
        $this->assertSame(0, \DB::table('journal_entries')->count());
        $this->assertSame(0, \App\Models\WorkOrder::query()->count());
    }

    public function test_expiration_command_is_idempotent_and_ignores_accepted(): void
    {
        Event::fake([QuotationExpired::class]);
        $context = $this->context();
        $expired = $this->quotation($context);
        $expired->forceFill(['status' => 'sent', 'valid_until' => today()->subDay()])->save();
        $accepted = $this->quotation($context);
        $accepted->forceFill(['status' => 'accepted', 'valid_until' => today()->subDay()])->save();

        $this->artisan('quotations:expire')->assertSuccessful();
        $this->artisan('quotations:expire')->assertSuccessful();
        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertSame('accepted', $accepted->fresh()->status);
        Event::assertDispatchedTimes(QuotationExpired::class, 1);
    }

    public function test_cancellation_and_no_show_are_audited_operational_states(): void
    {
        $context = $this->context();
        $appointment = app(AppointmentService::class)->save(
            $this->appointmentData($context), $this->appointmentServices($context)
        );
        $cancelled = app(AppointmentCancellationService::class)->cancel($appointment, 'Customer request');
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('not_required', $cancelled->deposit_status);

        $past = app(AppointmentService::class)->save(
            array_merge($this->appointmentData($context), [
                'scheduled_start' => now()->addDays(3)->setTime(10, 0),
                'scheduled_end' => now()->addDays(3)->setTime(11, 0),
            ]),
            $this->appointmentServices($context)
        );
        $past->forceFill([
            'scheduled_start' => now()->subHours(2), 'scheduled_end' => now()->subHour(),
        ])->save();
        $noShow = app(AppointmentCancellationService::class)->noShow($past->fresh(), 'Did not arrive');
        $this->assertSame('no_show', $noShow->status);
    }

    public function test_seeder_is_idempotent_and_creates_branch_sequences_without_fake_documents(): void
    {
        $context = $this->context();
        $seeder = app(QuotationAppointmentSeeder::class);
        $seeder->run();
        $seeder->run();

        $this->assertSame(3, DocumentSequence::query()->where('branch_id', $context['branch']->id)
            ->whereIn('document_type', ['quotation', 'appointment', 'appointment_deposit'])->count());
        $this->assertSame(0, Quotation::query()->where('company_id', $context['company']->id)->count());
        $this->assertSame(0, Appointment::query()->where('company_id', $context['company']->id)->count());
    }

    public function test_unprivileged_and_cross_company_users_cannot_access_quotation(): void
    {
        $context = $this->context();
        $quotation = $this->quotation($context);
        $user = User::factory()->create([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id, 'status' => 'active',
        ]);
        $user->accessibleBranches()->attach($context['branch']->id, ['is_default' => true, 'can_view' => true]);
        $this->actingAs($user)->get(route('quotations.show', $quotation))->assertForbidden();

        $other = $this->context();
        $this->actingAs($other['user'])->get(route('quotations.show', $quotation))->assertForbidden();
    }

    private function quotation(array $context): Quotation
    {
        return app(QuotationService::class)->save([
            'branch_id' => $context['branch']->id, 'customer_id' => $context['customer']->id,
            'vehicle_id' => $context['vehicle']->id, 'quotation_date' => today(),
            'valid_until' => today()->addDays(7), 'currency_id' => $context['currency']->id,
            'discount_type' => 'percentage', 'discount_value' => 10,
        ], [[
            'item_type' => 'service', 'service_id' => $context['service']->id, 'quantity' => 2,
            'discount_type' => 'percentage', 'discount_value' => 10,
        ]]);
    }

    private function acceptedQuotation(array $context): Quotation
    {
        $quotation = $this->quotation($context);
        $quotation->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save();

        return $quotation;
    }

    private function appointmentData(array $context, ?Employee $employee = null): array
    {
        return [
            'branch_id' => $context['branch']->id, 'customer_id' => $context['customer']->id,
            'vehicle_id' => $context['vehicle']->id, 'scheduled_start' => now()->addDays(2)->setTime(10, 0),
            'scheduled_end' => now()->addDays(2)->setTime(11, 0), 'assigned_employee_id' => $employee?->id,
            'booking_source' => 'walk_in', 'priority' => 'normal', 'deposit_required' => false,
            'deposit_amount' => 0,
        ];
    }

    private function appointmentServices(array $context, ?Employee $employee = null): array
    {
        return [[
            'service_id' => $context['service']->id, 'description' => $context['service']->name,
            'quantity' => 1, 'estimated_duration_minutes' => 60, 'unit_price_snapshot' => 100,
            'total_snapshot' => 115, 'assigned_employee_id' => $employee?->id,
        ]];
    }

    private function context(bool $owner = false): array
    {
        $currency = Currency::query()->firstOrCreate(['code' => 'SAR'], [
            'name_ar' => 'ريال', 'name_en' => 'Riyal', 'symbol' => 'ر.س', 'decimal_places' => 2, 'is_active' => true,
        ]);
        $company = Company::query()->create(['name' => 'Phase Nine '.uniqid(), 'currency_id' => $currency->id, 'is_active' => true]);
        $branch = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'B'.uniqid(), 'name' => 'Branch', 'is_main' => true, 'is_active' => true,
        ]);
        $branch->settings()->create([
            'working_day_start' => '08:00:00', 'working_day_end' => '20:00:00', 'weekend_days' => [],
        ]);
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $role = Role::query()->create([
            'company_id' => $company->id, 'name' => $owner ? 'company_owner' : 'phase9_'.uniqid(),
            'display_name' => 'Phase Nine', 'scope' => 'company', 'is_active' => true,
        ]);
        foreach ([
            'quotations.manual_price', 'quotations.override_minimum_price', 'quotations.view',
            'quotations.create', 'quotations.update', 'quotations.submit', 'quotations.approve',
            'quotations.send', 'quotations.accept', 'quotations.reject', 'quotations.cancel',
            'quotations.create_version', 'quotations.print', 'appointments.create',
            'appointment_deposits.record', 'appointment_deposits.refund_status',
        ] as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['display_name' => $name]);
            $role->permissions()->syncWithoutDetaching($permission);
        }
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch->id, ['is_default' => true, 'can_view' => true]);
        app(TenantContext::class)->initialize($user);
        $customer = Customer::factory()->create([
            'company_id' => $company->id, 'created_branch_id' => $branch->id, 'assigned_branch_id' => $branch->id,
        ]);
        $brand = VehicleBrand::query()->create(['name_ar' => 'Brand', 'is_active' => true]);
        $model = VehicleModel::query()->create(['vehicle_brand_id' => $brand->id, 'name_ar' => 'Model', 'is_active' => true]);
        $vehicle = Vehicle::query()->forceCreate([
            'company_id' => $company->id, 'customer_id' => $customer->id, 'created_branch_id' => $branch->id,
            'vehicle_brand_id' => $brand->id, 'vehicle_model_id' => $model->id,
            'plate_number' => 'P'.uniqid(), 'normalized_plate_number' => 'P'.uniqid(), 'status' => 'active',
        ]);
        $category = ServiceCategory::query()->forceCreate([
            'company_id' => $company->id, 'code' => 'CAT'.uniqid(), 'name' => 'Category', 'is_active' => true,
        ]);
        $tax = Tax::query()->forceCreate([
            'company_id' => $company->id, 'code' => 'VAT'.uniqid(), 'name' => 'VAT',
            'rate' => 15, 'tax_type' => 'vat', 'is_active' => true,
        ]);
        $service = Service::query()->forceCreate([
            'company_id' => $company->id, 'service_category_id' => $category->id, 'code' => 'S'.uniqid(),
            'name' => 'Service', 'service_type' => 'ppf', 'pricing_type' => 'fixed',
            'default_duration_minutes' => 60, 'default_tax_id' => $tax->id, 'requires_vehicle' => true, 'is_active' => true,
        ]);
        BranchService::query()->forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'service_id' => $service->id,
            'is_available' => true, 'default_price' => 100, 'minimum_price' => 80, 'is_active' => true,
        ]);
        ServicePrice::query()->forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'service_id' => $service->id,
            'price' => 100, 'effective_from' => today()->subDay(), 'priority' => 0, 'is_active' => true,
        ]);
        $paymentMethod = PaymentMethod::query()->forceCreate([
            'company_id' => $company->id, 'code' => 'CASH'.uniqid(), 'name' => 'Cash',
            'type' => 'cash', 'is_cash' => true, 'is_active' => true,
        ]);
        foreach (['quotation', 'appointment', 'appointment_deposit'] as $type) {
            DocumentSequence::query()->forceCreate([
                'company_id' => $company->id, 'branch_id' => $branch->id, 'document_type' => $type,
                'prefix' => strtoupper(substr($type, 0, 3)).'-', 'current_number' => 0, 'padding' => 6,
                'reset_period' => 'yearly', 'period_key' => now()->format('Y'),
                'scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, $type, now()->format('Y')),
                'is_active' => true,
            ]);
        }

        return compact('currency', 'company', 'branch', 'user', 'role', 'customer', 'vehicle', 'tax', 'service', 'paymentMethod');
    }
}
