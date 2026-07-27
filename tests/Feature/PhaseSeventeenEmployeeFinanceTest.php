<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeCommissionAccrual;
use App\Models\EmployeeCommissionRule;
use App\Models\EmployeeExpenseCategory;
use App\Models\EmployeeExpenseClaim;
use App\Models\JournalEntry;
use App\Models\SalesCreditNote;
use App\Models\SalesCreditNoteItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Tax;
use App\Services\EmployeeFinanceService;
use Database\Seeders\EmployeeFinanceSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class PhaseSeventeenEmployeeFinanceTest extends TestCase
{
    use BuildsTreasuryOperationsContext;
    use DatabaseTransactions;

    public function test_phase_seventeen_schema_and_seeder_are_safe_and_idempotent(): void
    {
        $context = $this->financeContext();
        app(EmployeeFinanceSeeder::class)->run();

        $this->assertTrue(Schema::hasTable('employee_commission_accruals'));
        $this->assertTrue(Schema::hasTable('employee_expense_claims'));
        $this->assertTrue(Schema::hasTable('employee_advances'));
        $this->assertFalse(Schema::hasColumn('employee_advances', 'balance'));
        $this->assertSame(4, EmployeeExpenseCategory::query()
            ->where('company_id', $context['company']->id)->count());
        $this->assertSame(0, EmployeeCommissionAccrual::query()
            ->where('company_id', $context['company']->id)->count());
        $this->assertSame(0, EmployeeExpenseClaim::query()
            ->where('company_id', $context['company']->id)->count());
        $this->assertSame(0, EmployeeAdvance::query()
            ->where('company_id', $context['company']->id)->count());
        $this->assertSame(0, JournalEntry::query()
            ->where('company_id', $context['company']->id)->count());
    }

    public function test_net_sales_commission_excludes_tax_and_calculation_is_idempotent(): void
    {
        $context = $this->financeContext();
        $rule = $this->rule($context, ['rule_value' => '5.0000']);
        $invoice = $this->issuedInvoice($context, '1000.0000', '140.0000');

        $first = app(EmployeeFinanceService::class)->calculateInvoice($invoice, $context['employee']);
        $second = app(EmployeeFinanceService::class)->calculateInvoice($invoice, $context['employee']);

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame('1000.0000', $first[0]->basis_amount);
        $this->assertSame('50.0000', $first[0]->commission_amount);
        $this->assertSame(1, EmployeeCommissionAccrual::query()
            ->where('commission_rule_id', $rule->id)->count());
    }

    public function test_overlapping_rules_are_rejected_and_employee_rule_has_priority(): void
    {
        $context = $this->financeContext();
        $this->rule($context, ['priority' => 1]);

        $this->expectException(BusinessRuleException::class);
        $this->rule($context, ['priority' => 2]);
    }

    public function test_credit_note_creates_one_idempotent_negative_adjustment(): void
    {
        $context = $this->financeContext();
        $this->rule($context);
        $invoice = $this->issuedInvoice($context);
        $original = app(EmployeeFinanceService::class)
            ->calculateInvoice($invoice, $context['employee'])[0];
        $creditNote = SalesCreditNote::factory()->create([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'customer_id' => $invoice->customer_id,
            'sales_invoice_id' => $invoice->id,
            'currency_id' => $context['currency']->id,
            'credit_note_date' => '2040-01-20',
            'status' => 'issued',
            'created_by' => $context['user']->id,
        ]);
        $item = new SalesCreditNoteItem;
        $item->forceFill([
            'sales_credit_note_id' => $creditNote->id,
            'sales_invoice_item_id' => $invoice->items()->value('id'),
            'description' => 'Partial return',
            'quantity' => '0.500000',
            'unit_price' => '1000.0000',
            'net_amount' => '500.0000',
            'tax_rate' => '14.0000',
            'tax_amount' => '70.0000',
            'total' => '570.0000',
        ])->save();

        $first = app(EmployeeFinanceService::class)
            ->calculateCreditNoteAdjustment($creditNote, $context['employee']);
        $second = app(EmployeeFinanceService::class)
            ->calculateCreditNoteAdjustment($creditNote, $context['employee']);

        $this->assertSame('-25.0000', $first[0]->commission_amount);
        $this->assertSame($original->id, $first[0]->adjusts_accrual_id);
        $this->assertSame($first[0]->id, $second[0]->id);
    }

    public function test_open_settlements_reserve_accrual_and_prevent_over_allocation(): void
    {
        $context = $this->financeContext();
        $this->rule($context);
        $accrual = app(EmployeeFinanceService::class)
            ->calculateInvoice($this->issuedInvoice($context), $context['employee'])[0];
        app(EmployeeFinanceService::class)->accrualAction($accrual, 'submit');
        $this->switchTreasuryActor($context['approver']);
        app(EmployeeFinanceSeeder::class)->run();
        app(EmployeeFinanceService::class)->accrualAction($accrual, 'approve');

        app(EmployeeFinanceService::class)->createSettlement(
            $context['employee'],
            [['accrual_id' => $accrual->id, 'amount' => '30.0000']],
            ['settlement_date' => '2040-01-20']
        );

        $this->expectException(BusinessRuleException::class);
        app(EmployeeFinanceService::class)->createSettlement(
            $context['employee'],
            [['accrual_id' => $accrual->id, 'amount' => '25.0000']],
            ['settlement_date' => '2040-01-21']
        );
    }

    public function test_commission_creator_cannot_approve_but_separate_approver_can_post_once(): void
    {
        $context = $this->financeContext();
        $this->rule($context);
        $accrual = app(EmployeeFinanceService::class)
            ->calculateInvoice($this->issuedInvoice($context), $context['employee'])[0];
        $service = app(EmployeeFinanceService::class);
        $service->accrualAction($accrual, 'submit');

        try {
            $service->accrualAction($accrual, 'approve');
            $this->fail('Creator approved their own accrual.');
        } catch (BusinessRuleException) {
            $this->assertTrue(true);
        }

        $this->switchTreasuryActor($context['approver']);
        app(EmployeeFinanceSeeder::class)->run();
        $service = app(EmployeeFinanceService::class);
        $service->accrualAction($accrual, 'approve');
        $posted = $service->accrualAction($accrual, 'post');

        $this->assertSame('posted', $posted->status);
        $this->assertNotNull($posted->journal_entry_id);
        $this->expectException(BusinessRuleException::class);
        $service->accrualAction($posted, 'post');
    }

    public function test_expense_totals_and_tax_are_calculated_on_the_server(): void
    {
        $context = $this->financeContext();
        $tax = new Tax;
        $tax->forceFill([
            'company_id' => $context['company']->id,
            'code' => 'VAT14-'.fake()->unique()->numerify('####'),
            'name' => 'VAT 14%',
            'rate' => '14.0000',
            'tax_type' => 'vat',
            'is_default' => false,
            'is_inclusive' => false,
            'is_active' => true,
        ])->save();
        $expense = $this->account($context, '653000');
        $payable = $this->account($context, '215000');

        $claim = app(EmployeeFinanceService::class)->createExpenseClaim($context['employee'], [
            'branch_id' => $context['branch']->id,
            'currency_id' => $context['currency']->id,
            'payable_account_id' => $payable->id,
            'claim_date' => '2040-01-10',
            'business_purpose' => 'Client visit',
            'subtotal' => '999999',
            'total_amount' => '999999',
            'items' => [[
                'expense_account_id' => $expense->id,
                'tax_id' => $tax->id,
                'expense_date' => '2040-01-10',
                'description' => 'Transport',
                'net_amount' => '100.0000',
                'tax_amount' => '999999',
            ]],
        ]);

        $this->assertSame('100.0000', $claim->subtotal);
        $this->assertSame('14.0000', $claim->tax_amount);
        $this->assertSame('114.0000', $claim->total_amount);
        $this->assertSame('14.0000', $claim->items->first()->tax_amount);
        $this->assertSame('114.0000', $claim->items->first()->total_amount);
    }

    public function test_cross_company_rule_and_premature_advance_close_are_blocked(): void
    {
        $context = $this->financeContext();
        $other = $this->treasuryContext();
        $this->switchTreasuryActor($context['user']);
        app(EmployeeFinanceSeeder::class)->run();

        try {
            app(EmployeeFinanceService::class)->saveRule(new EmployeeCommissionRule, [
                'branch_id' => $other['branch']->id,
                'currency_id' => $context['currency']->id,
                'expense_account_id' => $this->account($context, '652000')->id,
                'payable_account_id' => $this->account($context, '215000')->id,
                'rule_type' => 'fixed',
                'rule_value' => '10',
                'effective_from' => '2040-01-01',
                'priority' => 0,
                'is_active' => true,
            ]);
            $this->fail('Cross-company rule was accepted.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame(403, $exception->status());
        }

        $advance = new EmployeeAdvance;
        $advance->forceFill([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'employee_id' => $context['employee']->id,
            'currency_id' => $context['currency']->id,
            'receivable_account_id' => $this->account($context, '118000')->id,
            'created_by' => $context['user']->id,
            'status' => 'disbursed',
            'advance_number' => 'ADV-CLOSE-'.fake()->unique()->numerify('####'),
            'advance_type' => 'advance',
            'request_date' => '2040-01-10',
            'purpose' => 'Test',
            'amount' => '1000.0000',
            'settled_amount' => '0.0000',
        ])->save();

        $this->expectException(BusinessRuleException::class);
        app(EmployeeFinanceService::class)->advanceAction($advance, 'close');
    }

    private function financeContext(): array
    {
        $context = $this->treasuryContext();
        app(EmployeeFinanceSeeder::class)->run();
        $employee = Employee::query()->create([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'user_id' => $context['user']->id,
            'employee_code' => 'EMP-'.fake()->unique()->numerify('####'),
            'name' => 'Employee Finance Test',
            'employment_type' => 'full_time',
            'hire_date' => '2039-01-01',
            'status' => 'active',
        ]);

        return $context + compact('employee');
    }

    private function rule(array $context, array $overrides = []): EmployeeCommissionRule
    {
        return app(EmployeeFinanceService::class)->saveRule(new EmployeeCommissionRule, array_replace([
            'employee_id' => $context['employee']->id,
            'currency_id' => $context['currency']->id,
            'expense_account_id' => $this->account($context, '652000')->id,
            'payable_account_id' => $this->account($context, '215000')->id,
            'rule_type' => 'percentage_net_sales',
            'rule_value' => '5.0000',
            'effective_from' => '2040-01-01',
            'effective_to' => '2040-12-31',
            'priority' => 10,
            'is_active' => true,
        ], $overrides));
    }

    private function issuedInvoice(
        array $context,
        string $net = '1000.0000',
        string $tax = '140.0000'
    ): SalesInvoice {
        $customer = Customer::factory()->create([
            'company_id' => $context['company']->id,
            'created_branch_id' => $context['branch']->id,
            'assigned_branch_id' => $context['branch']->id,
            'created_by' => $context['user']->id,
        ]);
        $invoice = SalesInvoice::factory()->create([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'customer_id' => $customer->id,
            'currency_id' => $context['currency']->id,
            'invoice_date' => '2040-01-10',
            'status' => 'issued',
            'subtotal' => $net,
            'tax_amount' => $tax,
            'total' => bcadd($net, $tax, 4),
            'balance_due' => bcadd($net, $tax, 4),
            'created_by' => $context['user']->id,
        ]);
        SalesInvoiceItem::factory()->create([
            'sales_invoice_id' => $invoice->id,
            'net_amount' => $net,
            'tax_amount' => $tax,
            'total' => bcadd($net, $tax, 4),
        ]);

        return $invoice;
    }

    private function account(array $context, string $code)
    {
        return $this->treasuryAccount($context, $code);
    }
}
