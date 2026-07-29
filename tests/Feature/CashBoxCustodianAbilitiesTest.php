<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\CashBox;
use App\Services\CashBoxCustodianService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class CashBoxCustodianAbilitiesTest extends TestCase
{
    use DatabaseTransactions;
    use BuildsTreasuryOperationsContext;

    public function test_receive_pay_transfer_and_limit_are_independent(): void
    {
        $context = $this->treasuryContext();
        $box = CashBox::query()->where('company_id', $context['company']->id)
            ->where('branch_id', $context['branch']->id)->firstOrFail();
        $service = app(CashBoxCustodianService::class);
        $custodian = $service->assign($box, [
            'user_id' => $context['cashier']->id, 'valid_from' => now()->toDateString(),
            'can_receive' => true, 'can_pay' => false, 'can_transfer' => true,
            'payment_limit' => 200, 'is_primary' => true,
        ]);

        $this->switchTreasuryActor($context['cashier']);
        $service->assert($box, 'can_receive', '100');
        $service->assert($box, 'can_transfer', '100');
        try {
            $service->assert($box, 'can_pay', '100');
            $this->fail('A cashier without pay ability was allowed to pay.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('أمين الخزينة غير مخول بالصرف.', $exception->getMessage());
        }
        $this->expectExceptionMessage('مبلغ الصرف يتجاوز الحد المسموح لأمين الخزينة.');
        $service->update($custodian, ['can_receive' => true, 'can_pay' => true, 'can_transfer' => true, 'payment_limit' => 200, 'is_primary' => true]);
        $service->assert($box, 'can_pay', '201');
    }

    public function test_existing_active_assignment_can_be_updated_and_inactive_cannot(): void
    {
        $context = $this->treasuryContext();
        $box = CashBox::query()->where('company_id', $context['company']->id)
            ->where('branch_id', $context['branch']->id)->firstOrFail();
        $service = app(CashBoxCustodianService::class);
        $custodian = $service->assign($box, [
            'user_id' => $context['cashier']->id, 'valid_from' => now()->toDateString(),
            'can_receive' => true, 'can_pay' => false, 'can_transfer' => false,
            'is_primary' => true,
        ]);
        $updated = $service->update($custodian, [
            'can_receive' => true, 'can_pay' => true, 'can_transfer' => true,
            'payment_limit' => 10000, 'is_primary' => true, 'valid_to' => '2040-12-31',
        ]);
        $this->assertTrue($updated->can_pay);
        $this->assertSame('10000.0000', $updated->payment_limit);

        $updated->forceFill(['is_active' => false])->save();
        $this->expectExceptionMessage('لا يمكن تعديل تكليف أمين غير نشط.');
        $service->update($updated, ['can_receive' => true, 'can_pay' => false, 'can_transfer' => false, 'is_primary' => false]);
    }

    public function test_overlapping_primary_assignment_is_rejected(): void
    {
        $context = $this->treasuryContext();
        $box = CashBox::query()->where('company_id', $context['company']->id)
            ->where('branch_id', $context['branch']->id)->firstOrFail();
        $service = app(CashBoxCustodianService::class);
        $service->assign($box, [
            'user_id' => $context['cashier']->id, 'valid_from' => now()->toDateString(),
            'can_receive' => true, 'can_pay' => true, 'can_transfer' => true, 'is_primary' => true,
        ]);
        $other = $this->treasuryUser($context['company'], $context['branch'], $this->cashierRole($context));
        $second = $service->assign($box, [
            'user_id' => $other->id, 'valid_from' => now()->toDateString(),
            'can_receive' => true, 'can_pay' => true, 'can_transfer' => true, 'is_primary' => false,
        ]);
        $this->expectExceptionMessage('لا يجوز وجود أمين رئيسي آخر في نفس الفترة.');
        $service->update($second, ['can_receive' => true, 'can_pay' => true, 'can_transfer' => true, 'is_primary' => true]);
    }

    private function cashierRole(array $context): \App\Models\Role
    {
        return \App\Models\Role::query()->where('company_id', $context['company']->id)->where('name', 'cashier')->firstOrFail();
    }
}
