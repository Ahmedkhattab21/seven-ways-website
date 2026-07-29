<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\CashBox;
use App\Services\CashBoxCountService;
use App\Services\CashBoxCustodianService;
use App\Services\CashBoxSessionService;
use App\Services\CashOperationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class UatDef013OpeningCountGateTest extends TestCase
{
    use BuildsTreasuryOperationsContext;
    use DatabaseTransactions;

    public function test_shift_cash_operations_require_approved_opening_count_and_use_snapshot(): void
    {
        $context = $this->treasuryContext();
        $box = CashBox::query()->where('company_id', $context['company']->id)
            ->where('branch_id', $context['branch']->id)->firstOrFail();
        $box->forceFill(['requires_shift_opening' => true])->save();
        app(CashBoxCustodianService::class)->assign($box, [
            'user_id' => $context['cashier']->id, 'valid_from' => '2020-01-01',
            'can_receive' => true, 'can_pay' => true, 'can_transfer' => true, 'is_primary' => true,
        ]);
        $this->switchTreasuryActor($context['cashier']);
        $session = app(CashBoxSessionService::class)->open([
            'cash_box_id' => $box->id, 'custodian_user_id' => $context['cashier']->id,
            'business_date' => '2040-01-10',
        ]);
        try {
            app(CashOperationService::class)->create('receipt', [
                'branch_id' => $context['branch']->id, 'cash_box_id' => $box->id,
                'cash_box_session_id' => $session->id, 'receipt_type' => 'other_income',
                'document_date' => '2040-01-10', 'currency_id' => $context['currency']->id,
                'exchange_rate' => 1, 'amount' => 10,
                'offset_account_id' => $this->treasuryAccount($context, '410000')->id,
                'description' => 'blocked before opening count',
            ]);
            $this->fail('Cash operation was created before an approved opening count.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('العد الافتتاحي', $exception->getMessage());
        }

        $count = app(CashBoxCountService::class)->create($session, [
            'count_type' => 'opening', 'zero_count' => true,
        ]);
        $this->assertSame((string) $session->opening_book_balance, (string) $count->book_total);
        app(CashBoxCountService::class)->action($count, 'submit');
        $this->switchTreasuryActor($context['approver']);
        app(CashBoxCountService::class)->action($count->fresh(), 'review');
        app(CashBoxCountService::class)->action($count->fresh(), 'approve');
        $this->assertSame('counting', $session->fresh()->status);

        $operation = app(CashOperationService::class)->create('receipt', [
            'branch_id' => $context['branch']->id, 'cash_box_id' => $box->id,
            'cash_box_session_id' => $session->id, 'receipt_type' => 'other_income',
            'document_date' => '2040-01-10', 'currency_id' => $context['currency']->id,
            'exchange_rate' => 1, 'amount' => 10,
            'offset_account_id' => $this->treasuryAccount($context, '410000')->id,
            'description' => 'allowed after opening count',
        ]);
        $this->assertSame('draft', $operation->status);
    }
}
