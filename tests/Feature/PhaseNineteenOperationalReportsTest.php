<?php

namespace Tests\Feature;

use App\Analytics\ReportFilterData;
use App\Core\Tenancy\TenantContext;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\AnalyticsReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAnalyticsContext;
use Tests\TestCase;

class PhaseNineteenOperationalReportsTest extends TestCase
{
    use BuildsAnalyticsContext;
    use DatabaseTransactions;

    public function test_inventory_valuation_uses_average_cost_not_sale_price(): void
    {
        $context = $this->analyticsContext();
        $unit = Unit::query()->where('is_active', true)->firstOrFail();
        $category = new ProductCategory;
        $category->forceFill([
            'company_id' => $context['company']->id,
            'code' => 'ANALYTICS',
            'name' => 'Analytics',
            'created_by' => $context['user']->id,
        ]);
        $category->save();
        $product = Product::factory()->create([
            'company_id' => $context['company']->id,
            'category_id' => $category->id,
            'purchase_unit_id' => $unit->id,
            'stock_unit_id' => $unit->id,
            'sale_unit_id' => $unit->id,
            'default_sale_price' => 999,
            'minimum_stock' => 3,
        ]);
        $warehouse = new Warehouse;
        $warehouse->forceFill([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'code' => 'ANALYTICS',
            'name' => 'Analytics warehouse',
            'warehouse_type' => 'other',
            'is_main' => false,
            'is_system' => false,
            'is_active' => true,
        ]);
        $warehouse->save();
        DB::table('stock_balances')->insert([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'reserved_quantity' => 1,
            'available_quantity' => 4,
            'average_cost' => 12,
            'last_movement_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $report = app(AnalyticsReportService::class)->run('inventory', $this->filters($context));

        $this->assertSame('60.0000', $report->summary['stock_valuation']);
        $this->assertEquals(12, $report->rows->first()->unit_cost);
    }

    public function test_receivable_aging_uses_due_date_and_keeps_unallocated_payments_visible(): void
    {
        $context = $this->analyticsContext();
        $invoice = $this->analyticsInvoice($context, now()->subDays(200)->toDateString(), '100', '0', '14', '114');
        $invoice->forceFill([
            'due_date' => now()->subDays(45)->toDateString(),
            'balance_due' => 80,
            'status' => 'partially_paid',
        ])->save();
        DB::table('customer_payments')->insert([
            'uuid' => fake()->uuid(),
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'payment_number' => 'PAY-19',
            'customer_id' => $context['customer']->id,
            'currency_id' => $context['currency']->id,
            'payment_method_id' => $context['method']->id,
            'source_type' => 'manual',
            'status' => 'approved',
            'payment_date' => today(),
            'amount' => 20,
            'allocated_amount' => 0,
            'unallocated_amount' => 20,
            'received_by' => $context['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $report = app(AnalyticsReportService::class)->run('receivables', $this->filters($context));

        $this->assertSame('80.0000', $report->summary['outstanding']);
        $this->assertSame('80.0000', $report->summary['aging']['31-60']);
        $this->assertSame('20.0000', $report->summary['unallocated_payments']);
    }

    public function test_employee_finance_and_approval_summaries_are_derived_without_kpi_tables(): void
    {
        $context = $this->analyticsContext();
        $employee = Employee::query()->create([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'employee_code' => 'E19',
            'name' => 'Employee 19',
            'employment_type' => 'full_time',
            'hire_date' => today(),
            'status' => 'active',
        ]);
        $ruleId = DB::table('employee_commission_rules')->insertGetId([
            'uuid' => fake()->uuid(), 'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id, 'employee_id' => $employee->id,
            'currency_id' => $context['currency']->id,
            'expense_account_id' => $this->treasuryAccount($context, '651000')->id,
            'payable_account_id' => $this->treasuryAccount($context, '214000')->id,
            'rule_type' => 'percentage_net_sales', 'rule_value' => 10,
            'effective_from' => '2039-01-01', 'priority' => 0, 'is_active' => true,
            'created_by' => $context['user']->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('employee_commission_accruals')->insert([
            'uuid' => fake()->uuid(), 'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id, 'employee_id' => $employee->id,
            'commission_rule_id' => $ruleId,
            'currency_id' => $context['currency']->id, 'source_key' => fake()->sha256(),
            'accrual_date' => '2040-01-10', 'basis_amount' => 100, 'rule_value' => 10,
            'commission_amount' => 10, 'settled_amount' => 4, 'calculation_snapshot' => '{}',
            'status' => 'approved', 'created_by' => $context['user']->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('approval_tasks')->insert([
            'uuid' => fake()->uuid(), 'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id, 'module' => 'sales',
            'document_type' => 'SalesInvoice', 'document_number' => 'INV-19',
            'stage' => 1, 'status' => 'pending', 'requested_by' => $context['user']->id,
            'required_permission' => 'sales.invoices.approve',
            'requested_at' => '2040-01-10 10:00:00', 'priority' => 'normal',
            'due_at' => '2040-01-11 10:00:00', 'idempotency_key' => fake()->sha256(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $employeeReport = app(AnalyticsReportService::class)->run('employee-finance', $this->filters($context));
        $approvalReport = app(AnalyticsReportService::class)->run('approvals', $this->filters($context));
        $this->assertSame('6.0000', $employeeReport->summary['commission_outstanding']);
        $this->assertSame(1, $approvalReport->summary['pending']);
    }

    private function filters(array $context): ReportFilterData
    {
        return ReportFilterData::from([
            'branch_id' => $context['branch']->id,
            'currency_id' => $context['currency']->id,
            'date_from' => '2039-01-01',
            'date_to' => '2040-01-31',
        ], app(TenantContext::class));
    }
}
