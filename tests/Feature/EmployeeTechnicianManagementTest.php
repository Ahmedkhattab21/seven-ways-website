<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeServiceSkill;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\WorkOrderTechnicianService;
use Database\Seeders\EmployeeManagementSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EmployeeTechnicianManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableModules('technicians', 'work_orders');
    }

    public function test_employee_can_be_created_without_user_with_branch_skill_and_no_uat_record(): void
    {
        $context = $this->context();
        $usersBefore = User::query()->count();

        $response = $this->actingAs($context['user'])->post(route('employees.store'), [
            'branch_id' => $context['branch']->id,
            'employee_code' => 'TECH-001',
            'name' => 'فني اختبار مستقل',
            'phone' => '01000000000',
            'job_title' => 'فني حماية',
            'employment_type' => 'full_time',
            'hire_date' => today()->format('Y-m-d'),
            'status' => 'active',
            'skills' => [[
                'service_id' => $context['service']->id,
                'skill_level' => 'senior',
                'is_primary' => '1',
                'is_active' => '1',
            ]],
        ]);

        $response->assertSessionHasNoErrors();
        $employee = Employee::query()->where('employee_code', 'TECH-001')->firstOrFail();
        $response->assertRedirect(route('employees.show', $employee));
        $this->assertNull($employee->user_id);
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertDatabaseHas('employee_service_skills', [
            'employee_id' => $employee->id,
            'service_id' => $context['service']->id,
            'skill_level' => 'senior',
            'is_active' => 1,
        ]);
        $this->assertDatabaseMissing('employees', ['employee_code' => 'UAT-TECH']);
    }

    public function test_EmployeeServiceSkill_duplicate_and_service_unavailable_in_branch_are_rejected(): void
    {
        $context = $this->context();
        $payload = [
            'branch_id' => $context['branch']->id,
            'employee_code' => 'TECH-DUP',
            'name' => 'فني',
            'job_title' => 'فني',
            'employment_type' => 'full_time',
            'hire_date' => today()->format('Y-m-d'),
            'status' => 'active',
            'skills' => [
                ['service_id' => $context['service']->id, 'skill_level' => 'junior', 'is_primary' => 0, 'is_active' => 1],
                ['service_id' => $context['service']->id, 'skill_level' => 'senior', 'is_primary' => 1, 'is_active' => 1],
            ],
        ];

        $this->actingAs($context['user'])->post(route('employees.store'), $payload)
            ->assertSessionHasErrors('skills.1.service_id');

        $context['branchService']->update(['is_available' => false]);
        $payload['skills'] = [array_merge($payload['skills'][0], ['service_id' => $context['service']->id])];
        $this->actingAs($context['user'])->post(route('employees.store'), $payload)
            ->assertSessionHasErrors('skills.0.service_id');
    }

    public function test_employee_list_and_record_are_company_and_branch_scoped(): void
    {
        $context = $this->context();
        $visible = $this->employee($context, 'VISIBLE', 'فني القاهرة');
        $otherCompany = Company::query()->create(['name' => 'Other '.uniqid(), 'is_active' => true]);
        $otherBranch = Branch::query()->create([
            'company_id' => $otherCompany->id, 'code' => 'O'.uniqid(),
            'name' => 'فرع آخر', 'is_main' => true, 'is_active' => true,
        ]);
        $hidden = Employee::query()->forceCreate([
            'company_id' => $otherCompany->id, 'branch_id' => $otherBranch->id,
            'employee_code' => 'HIDDEN', 'name' => 'فني شركة أخرى',
            'job_title' => 'فني', 'employment_type' => 'full_time', 'status' => 'active',
        ]);

        $this->actingAs($context['user'])->get(route('employees.index'))
            ->assertOk()->assertSee($visible->name)->assertDontSee($hidden->name);
        $this->actingAs($context['user'])->get(route('employees.show', $hidden))->assertForbidden();
    }

    public function test_WorkOrderTechnician_assignment_requires_exact_active_unexpired_skill(): void
    {
        $context = $this->context();
        $line = $this->workOrderLine($context);
        $employee = $this->employee($context, 'TECH-A', 'فني أ');

        try {
            app(WorkOrderTechnicianService::class)->assign($line, $employee);
            $this->fail('Unqualified employee must not be assigned.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame(403, $exception->status());
        }

        $skill = $this->skill($context, $employee, ['certification_expires_at' => today()->subDay()]);
        try {
            app(WorkOrderTechnicianService::class)->assign($line, $employee);
            $this->fail('Expired skill must not qualify.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame(403, $exception->status());
        }

        $skill->update(['certification_expires_at' => today()->addDay()]);
        $assignment = app(WorkOrderTechnicianService::class)->assign($line, $employee, ['role' => 'lead']);
        $this->assertSame($employee->id, $assignment->employee_id);
        $this->assertSame('lead', $assignment->role);
    }

    public function test_work_order_page_only_lists_qualified_technicians_and_hides_cost_without_permission(): void
    {
        $context = $this->context();
        $line = $this->workOrderLine($context);
        $qualified = $this->employee($context, 'TECH-Q', 'الفني المؤهل');
        $unqualified = $this->employee($context, 'TECH-X', 'الفني غير المؤهل');
        $this->skill($context, $qualified);

        $this->actingAs($context['user'])->get(route('work-orders.show', $line->workOrder))
            ->assertOk()
            ->assertSee($qualified->name)
            ->assertDontSee($unqualified->name)
            ->assertDontSee('تكلفة الساعة')
            ->assertSee('الفني: غير مسند');
    }

    public function test_employee_management_seeder_is_idempotent_and_does_not_grant_management_to_accountant(): void
    {
        $context = $this->context();
        Role::query()->create([
            'company_id' => $context['company']->id, 'name' => 'accountant',
            'display_name' => 'محاسب', 'scope' => 'company', 'is_active' => true,
        ]);

        $this->seed(EmployeeManagementSeeder::class);
        $this->seed(EmployeeManagementSeeder::class);

        $owner = Role::query()->where('company_id', $context['company']->id)->where('name', 'company_owner')->firstOrFail();
        $accountant = Role::query()->where('company_id', $context['company']->id)->where('name', 'accountant')->firstOrFail();
        $this->assertSame(1, Permission::query()->where('name', 'employees.manage_skills')->count());
        $this->assertTrue($owner->permissions()->where('name', 'employees.manage_skills')->exists());
        $this->assertFalse($accountant->permissions()->whereIn('name', [
            'employees.create', 'employees.update', 'employees.disable', 'employees.manage_skills',
        ])->exists());
    }

    private function context(): array
    {
        $currency = Currency::query()->firstOrCreate(['code' => 'EGP'], [
            'name_ar' => 'جنيه مصري', 'name_en' => 'Egyptian Pound',
            'symbol' => 'ج.م', 'decimal_places' => 2, 'is_active' => true,
        ]);
        $company = Company::query()->create(['name' => 'Employees '.uniqid(), 'currency_id' => $currency->id, 'is_active' => true]);
        $branch = Branch::query()->create([
            'company_id' => $company->id, 'code' => 'B'.uniqid(),
            'name' => 'فرع القاهرة', 'is_main' => true, 'is_active' => true,
        ]);
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $role = Role::query()->create([
            'company_id' => $company->id, 'name' => 'company_owner',
            'display_name' => 'مالك الشركة', 'scope' => 'company', 'is_active' => true,
        ]);
        foreach ([
            'employees.view', 'employees.create', 'employees.update', 'employees.disable', 'employees.manage_skills',
            'work_orders.view', 'work_orders.assign_technicians',
        ] as $name) {
            $role->permissions()->syncWithoutDetaching(
                Permission::query()->firstOrCreate(['name' => $name], ['display_name' => $name])
            );
        }
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch->id, ['is_default' => true, 'can_view' => true]);
        app(TenantContext::class)->initialize($user);

        $category = ServiceCategory::query()->forceCreate([
            'company_id' => $company->id, 'code' => 'SC'.uniqid(),
            'name' => 'خدمات', 'is_active' => true,
        ]);
        $service = Service::query()->forceCreate([
            'company_id' => $company->id, 'service_category_id' => $category->id,
            'code' => 'S'.uniqid(), 'name' => 'خدمة حماية',
            'service_type' => 'ppf', 'pricing_type' => 'fixed',
            'default_duration_minutes' => 60, 'requires_vehicle' => true, 'is_active' => true,
        ]);
        $branchService = BranchService::query()->forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'service_id' => $service->id, 'is_available' => true, 'is_active' => true,
        ]);

        return compact('company', 'branch', 'user', 'role', 'service', 'branchService');
    }

    private function employee(array $context, string $code, string $name): Employee
    {
        return Employee::query()->forceCreate([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'employee_code' => $code,
            'name' => $name,
            'job_title' => 'فني خدمات',
            'employment_type' => 'full_time',
            'hire_date' => today(),
            'status' => 'active',
        ]);
    }

    private function skill(array $context, Employee $employee, array $overrides = []): EmployeeServiceSkill
    {
        return EmployeeServiceSkill::query()->forceCreate(array_merge([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'employee_id' => $employee->id,
            'service_id' => $context['service']->id,
            'skill_level' => 'senior',
            'is_primary' => true,
            'is_active' => true,
        ], $overrides));
    }

    private function workOrderLine(array $context)
    {
        $customer = Customer::factory()->create([
            'company_id' => $context['company']->id,
            'created_branch_id' => $context['branch']->id,
            'assigned_branch_id' => $context['branch']->id,
        ]);
        $brand = VehicleBrand::query()->create(['name_ar' => 'علامة', 'is_active' => true]);
        $model = VehicleModel::query()->create(['vehicle_brand_id' => $brand->id, 'name_ar' => 'موديل', 'is_active' => true]);
        $vehicle = Vehicle::query()->forceCreate([
            'company_id' => $context['company']->id, 'customer_id' => $customer->id,
            'created_branch_id' => $context['branch']->id, 'vehicle_brand_id' => $brand->id,
            'vehicle_model_id' => $model->id, 'plate_number' => 'T'.uniqid(),
            'normalized_plate_number' => 'T'.uniqid(), 'status' => 'active',
        ]);
        $warehouse = Warehouse::query()->forceCreate([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'code' => 'W'.uniqid(), 'name' => 'مخزن', 'warehouse_type' => 'main',
            'is_active' => true, 'is_system' => false, 'allows_work_order_issue' => true,
        ]);
        $order = WorkOrder::query()->forceCreate([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'warehouse_id' => $warehouse->id, 'work_order_number' => 'WO'.uniqid(),
            'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id,
            'status' => 'awaiting_inspection', 'priority' => 'normal',
            'estimated_subtotal' => 0, 'estimated_tax' => 0, 'estimated_total' => 0,
            'created_by' => $context['user']->id,
        ]);

        return $order->services()->create([
            'service_id' => $context['service']->id, 'description' => 'خدمة حماية',
            'quantity' => 1, 'status' => 'planned', 'planned_duration_minutes' => 60,
            'unit_price_snapshot' => 100, 'total_snapshot' => 100,
        ]);
    }
}
