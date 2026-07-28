<?php

namespace Tests\Feature\PhaseTwentyOne;

use App\Models\ApprovalDelegation;
use App\Models\ApprovalTask;
use App\Models\Employee;
use App\Models\EmployeeCommissionAccrual;
use App\Models\EmployeeExpenseClaim;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\UsesPhaseTwentyOneUat;
use Tests\TestCase;

class PhaseTwentyOneEmployeeApprovalCycleTest extends TestCase
{
    use DatabaseTransactions;
    use UsesPhaseTwentyOneUat;

    public function test_employee_reference_data_has_no_financial_or_workflow_side_effects(): void
    {
        $this->setUpUatContext();

        $this->assertSame(10, Employee::query()->where('company_id', $this->uatCompany->id)
            ->where('employee_code', 'like', 'UAT-%')->count());
        $this->assertSame(0, EmployeeCommissionAccrual::query()
            ->where('company_id', $this->uatCompany->id)->count());
        $this->assertSame(0, EmployeeExpenseClaim::query()
            ->where('company_id', $this->uatCompany->id)->count());
        $this->assertSame(0, ApprovalTask::query()->where('company_id', $this->uatCompany->id)->count());
        $this->assertSame(0, ApprovalDelegation::query()
            ->where('company_id', $this->uatCompany->id)->count());
        $this->assertFalse($this->uatUser('uat.accountant@sevenways.test')
            ->hasPermission('employee_expenses.approve'));
    }
}
