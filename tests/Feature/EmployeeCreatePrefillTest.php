<?php

namespace Tests\Feature;

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
use App\Services\EmployeeManagementService;
use App\Services\EmployeeServiceSkillService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class EmployeeCreatePrefillTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableModules('technicians', 'work_orders');
    }

    public function test_create_page_works_with_and_without_valid_prefill_without_writing_data(): void
    {
        $context = $this->context();
        $before = Employee::query()->count();

        $this->actingAs($context['user'])->get(route('employees.create'))
            ->assertOk()
            ->assertSee('إضافة موظف أو فني')
            ->assertSee('data-add-employee-skill', false)
            ->assertSee('type="button"', false)
            ->assertSee('لم تتم إضافة مهارات خدمات لهذا الموظف.');

        $response = $this->actingAs($context['user'])->get(route('employees.create', [
            'branch_id' => $context['branch']->id,
            'service_id' => $context['service']->id,
            'return_url' => '/work-orders/2',
        ]));

        $response->assertOk()
            ->assertSee($context['branch']->name)
            ->assertViewHas('selectedBranchId', $context['branch']->id)
            ->assertViewHas('prefillServiceId', $context['service']->id)
            ->assertViewHas('returnUrl', '/work-orders/2');
        $config = $this->skillsConfig($response->getContent());
        $this->assertSame($context['service']->id, $config['initialRows'][0]['service_id']);
        $this->assertSame(1, $config['initialRows'][0]['is_primary']);
        $this->assertSame(1, $config['initialRows'][0]['is_active']);
        $this->assertSame($before, Employee::query()->count());
        $this->assertSame(0, EmployeeServiceSkill::query()->count());
    }

    public function test_dynamic_skill_state_and_old_input_are_rendered_for_the_browser(): void
    {
        $context = $this->context();

        $response = $this->followingRedirects()
            ->actingAs($context['user'])
            ->from(route('employees.create'))
            ->post(route('employees.store'), [
                'branch_id' => $context['branch']->id,
                'employee_code' => 'TECH-OLD',
                'name' => '',
                'job_title' => 'فني',
                'employment_type' => 'full_time',
                'status' => 'active',
                'skills_managed' => 1,
                'skills' => [[
                    'service_id' => $context['service']->id,
                    'skill_level' => 'expert',
                    'is_primary' => 1,
                    'is_active' => 0,
                    'certified_at' => '2026-07-01',
                    'certification_expires_at' => '2026-12-31',
                    'notes' => 'مهارة مستعادة',
                ]],
            ]);

        $response->assertOk()->assertSee('data-employee-skills-config', false);
        $config = $this->skillsConfig($response->getContent());
        $this->assertSame('expert', $config['initialRows'][0]['skill_level']);
        $this->assertSame(1, (int) $config['initialRows'][0]['is_primary']);
        $this->assertSame(0, (int) $config['initialRows'][0]['is_active']);
        $this->assertSame('2026-07-01', $config['initialRows'][0]['certified_at']);
        $this->assertSame('مهارة مستعادة', $config['initialRows'][0]['notes']);
        $this->assertArrayHasKey('name', $config['errors']);
    }

    public function test_employee_can_be_saved_with_multiple_distinct_branch_services(): void
    {
        $context = $this->context();
        $secondService = Service::query()->forceCreate([
            'company_id' => $context['company']->id,
            'service_category_id' => $context['service']->service_category_id,
            'code' => 'S'.uniqid(),
            'name' => 'خدمة ثانية',
            'service_type' => 'ppf',
            'pricing_type' => 'fixed',
            'default_duration_minutes' => 30,
            'requires_vehicle' => true,
            'is_active' => true,
        ]);
        BranchService::query()->forceCreate([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'service_id' => $secondService->id,
            'is_available' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($context['user'])->post(route('employees.store'), [
            'branch_id' => $context['branch']->id,
            'employee_code' => 'TECH-MULTI',
            'name' => 'فني متعدد المهارات',
            'job_title' => 'فني',
            'employment_type' => 'full_time',
            'status' => 'active',
            'skills_managed' => 1,
            'skills' => [
                [
                    'service_id' => $context['service']->id,
                    'skill_level' => 'expert',
                    'is_primary' => 1,
                    'is_active' => 1,
                ],
                [
                    'service_id' => $secondService->id,
                    'skill_level' => 'intermediate',
                    'is_primary' => 0,
                    'is_active' => 1,
                ],
            ],
        ]);

        $employee = Employee::query()->where('employee_code', 'TECH-MULTI')->firstOrFail();
        $response->assertRedirect(route('employees.show', $employee));
        $this->assertSame(2, $employee->serviceSkills()->count());
    }

    public function test_missing_branch_or_service_ids_do_not_cause_server_error(): void
    {
        $context = $this->context();

        $this->actingAs($context['user'])->get(route('employees.create', ['branch_id' => 999999]))
            ->assertOk();
        $this->actingAs($context['user'])->get(route('employees.create', ['service_id' => 999999]))
            ->assertOk();
    }

    public function test_cross_company_branch_and_service_prefill_are_forbidden(): void
    {
        $context = $this->context();
        $other = $this->otherCompanyRecords();

        $this->actingAs($context['user'])->get(route('employees.create', ['branch_id' => $other['branch']->id]))
            ->assertForbidden();
        $this->actingAs($context['user'])->get(route('employees.create', [
            'branch_id' => $context['branch']->id,
            'service_id' => $other['service']->id,
        ]))->assertForbidden();
    }

    public function test_service_unavailable_in_selected_branch_is_not_prefilled(): void
    {
        $context = $this->context();
        $context['branchService']->update(['is_available' => false]);

        $this->actingAs($context['user'])->get(route('employees.create', [
            'branch_id' => $context['branch']->id,
            'service_id' => $context['service']->id,
        ]))->assertOk()->assertDontSee($context['service']->name);
    }

    public function test_only_internal_return_urls_are_kept(): void
    {
        $context = $this->context();

        $this->actingAs($context['user'])->get(route('employees.create', [
            'return_url' => route('work-orders.show', 2),
        ]))->assertOk()->assertSee('value="/work-orders/2"', false);

        $this->actingAs($context['user'])->get(route('employees.create', [
            'return_url' => 'https://evil.example/work-orders/2',
        ]))->assertOk()->assertDontSee('evil.example');
    }

    public function test_employee_and_skill_are_saved_then_return_to_work_order_as_qualified(): void
    {
        $context = $this->context();
        $workOrder = $this->workOrder($context);

        $response = $this->actingAs($context['user'])->post(route('employees.store'), [
            'branch_id' => $context['branch']->id,
            'employee_code' => 'TECH-PREFILL',
            'name' => 'فني مؤهل جديد',
            'job_title' => 'فني تركيب أفلام',
            'employment_type' => 'full_time',
            'hire_date' => today()->format('Y-m-d'),
            'status' => 'active',
            'skills_managed' => 1,
            'skills' => [[
                'service_id' => $context['service']->id,
                'skill_level' => 'expert',
                'is_primary' => 1,
                'is_active' => 1,
            ]],
            'return_url' => route('work-orders.show', $workOrder),
        ]);

        $employee = Employee::query()->where('employee_code', 'TECH-PREFILL')->firstOrFail();
        $response->assertRedirect('/work-orders/'.$workOrder->id)
            ->assertSessionHas('status', 'تم إنشاء الفني وربط مهارات الخدمات بنجاح.');
        $this->assertDatabaseHas('employee_service_skills', [
            'employee_id' => $employee->id,
            'service_id' => $context['service']->id,
            'skill_level' => 'expert',
            'is_primary' => 1,
            'is_active' => 1,
        ]);
        $this->actingAs($context['user'])->get(route('work-orders.show', $workOrder))
            ->assertOk()
            ->assertSee($employee->name);
    }

    public function test_skill_failure_rolls_back_employee_creation(): void
    {
        $context = $this->context();
        $skills = $this->mock(EmployeeServiceSkillService::class);
        $skills->shouldReceive('save')->once()->andThrow(new RuntimeException('skill failure'));
        $manager = app(EmployeeManagementService::class);

        try {
            $manager->save(new Employee(), [
                'branch_id' => $context['branch']->id,
                'employee_code' => 'TECH-ROLLBACK',
                'name' => 'فني تراجع',
                'job_title' => 'فني',
                'employment_type' => 'full_time',
                'status' => 'active',
                'skills_managed' => true,
                'skills' => [[
                    'service_id' => $context['service']->id,
                    'skill_level' => 'expert',
                    'is_primary' => true,
                    'is_active' => true,
                ]],
            ]);
            $this->fail('The skill failure must abort the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('skill failure', $exception->getMessage());
        }

        $this->assertDatabaseMissing('employees', ['employee_code' => 'TECH-ROLLBACK']);
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
            'name' => 'Employee Prefill '.uniqid(),
            'currency_id' => $currency->id,
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'B'.uniqid(),
            'name' => 'الفرع الرئيسي - القاهرة',
            'is_main' => true,
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id,
            'name' => 'company_owner',
            'display_name' => 'مالك الشركة',
            'scope' => 'company',
            'is_active' => true,
        ]);
        foreach (['employees.create', 'employees.manage_skills', 'work_orders.view', 'work_orders.assign_technicians'] as $permission) {
            $role->permissions()->syncWithoutDetaching(
                Permission::query()->firstOrCreate(['name' => $permission], ['display_name' => $permission])
            );
        }
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch->id, [
            'is_default' => true,
            'can_view' => true,
        ]);
        app(TenantContext::class)->initialize($user);

        $category = ServiceCategory::query()->forceCreate([
            'company_id' => $company->id,
            'code' => 'SC'.uniqid(),
            'name' => 'خدمات',
            'is_active' => true,
        ]);
        $service = Service::query()->forceCreate([
            'company_id' => $company->id,
            'service_category_id' => $category->id,
            'code' => 'S'.uniqid(),
            'name' => 'خدمة تركيب أفلام',
            'service_type' => 'ppf',
            'pricing_type' => 'fixed',
            'default_duration_minutes' => 60,
            'requires_vehicle' => true,
            'is_active' => true,
        ]);
        $branchService = BranchService::query()->forceCreate([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'is_available' => true,
            'is_active' => true,
        ]);

        return compact('company', 'branch', 'user', 'service', 'branchService');
    }

    private function otherCompanyRecords(): array
    {
        $company = Company::query()->create(['name' => 'Other '.uniqid(), 'is_active' => true]);
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'O'.uniqid(),
            'name' => 'فرع شركة أخرى',
            'is_main' => true,
            'is_active' => true,
        ]);
        $category = ServiceCategory::query()->forceCreate([
            'company_id' => $company->id,
            'code' => 'OSC'.uniqid(),
            'name' => 'خدمات أخرى',
            'is_active' => true,
        ]);
        $service = Service::query()->forceCreate([
            'company_id' => $company->id,
            'service_category_id' => $category->id,
            'code' => 'OS'.uniqid(),
            'name' => 'خدمة شركة أخرى',
            'service_type' => 'ppf',
            'pricing_type' => 'fixed',
            'default_duration_minutes' => 60,
            'requires_vehicle' => true,
            'is_active' => true,
        ]);

        return compact('branch', 'service');
    }

    private function workOrder(array $context): WorkOrder
    {
        $customer = Customer::factory()->create([
            'company_id' => $context['company']->id,
            'created_branch_id' => $context['branch']->id,
            'assigned_branch_id' => $context['branch']->id,
        ]);
        $brand = VehicleBrand::query()->create(['name_ar' => 'علامة', 'is_active' => true]);
        $model = VehicleModel::query()->create([
            'vehicle_brand_id' => $brand->id,
            'name_ar' => 'موديل',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::query()->forceCreate([
            'company_id' => $context['company']->id,
            'customer_id' => $customer->id,
            'created_branch_id' => $context['branch']->id,
            'vehicle_brand_id' => $brand->id,
            'vehicle_model_id' => $model->id,
            'plate_number' => 'T'.uniqid(),
            'normalized_plate_number' => 'T'.uniqid(),
            'status' => 'active',
        ]);
        $warehouse = Warehouse::query()->forceCreate([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'code' => 'W'.uniqid(),
            'name' => 'مخزن',
            'warehouse_type' => 'main',
            'is_active' => true,
            'is_system' => false,
            'allows_work_order_issue' => true,
        ]);
        $workOrder = WorkOrder::query()->forceCreate([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'warehouse_id' => $warehouse->id,
            'work_order_number' => 'WO'.uniqid(),
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'awaiting_inspection',
            'priority' => 'normal',
            'estimated_subtotal' => 0,
            'estimated_tax' => 0,
            'estimated_total' => 0,
            'created_by' => $context['user']->id,
        ]);
        $workOrder->services()->create([
            'service_id' => $context['service']->id,
            'description' => 'خدمة تركيب أفلام',
            'quantity' => 1,
            'status' => 'planned',
            'planned_duration_minutes' => 60,
            'unit_price_snapshot' => 100,
            'total_snapshot' => 100,
        ]);

        return $workOrder;
    }

    private function skillsConfig(string $html): array
    {
        $matched = preg_match(
            '/<script type="application\/json" data-employee-skills-config>(.*?)<\/script>/s',
            $html,
            $matches
        );
        $this->assertSame(1, $matched);

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5), true, flags: JSON_THROW_ON_ERROR);
    }
}
