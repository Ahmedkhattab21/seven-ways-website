<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\CashBox;
use App\Models\CashOverShortAdjustment;
use App\Models\CashReceipt;
use App\Models\JournalEntry;
use App\Services\CashBoxCountService;
use App\Services\CashBoxCustodianService;
use App\Services\CashBoxSessionService;
use App\Services\CashOperationPostingService;
use App\Services\CashOperationService;
use App\Services\CashOverShortService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class PhaseFifteenCashSessionOperationsTest extends TestCase
{
    use BuildsTreasuryOperationsContext;
    use DatabaseTransactions;

    public function test_zero_cash_count_creates_auditable_zero_without_fake_lines(): void
    {
        $context = $this->treasuryContext();
        $box = CashBox::query()->where('company_id', $context['company']->id)->where('branch_id', $context['branch']->id)->firstOrFail();
        app(CashBoxCustodianService::class)->assign($box, [
            'user_id' => $context['cashier']->id, 'valid_from' => '2040-01-01',
            'valid_to' => '2040-12-31', 'can_receive' => true, 'can_pay' => true,
            'can_transfer' => true, 'is_primary' => true,
        ]);
        $session = app(CashBoxSessionService::class)->open([
            'cash_box_id' => $box->id, 'custodian_user_id' => $context['cashier']->id,
            'business_date' => '2040-01-10',
        ]);
        app(CashBoxSessionService::class)->action($session, 'start_counting');
        $count = app(CashBoxCountService::class)->create($session->fresh(), [
            'count_type' => 'opening', 'zero_count' => true, 'notes' => 'Empty cash box UAT',
        ]);
        $this->assertSame('0.0000', $count->counted_total);
        $this->assertSame('0.0000', $count->difference);
        $this->assertCount(0, $count->lines);
        $this->assertStringContainsString('Zero cash count', $count->notes);
    }

    public function test_one_session_backend_count_and_over_short_blocking_close(): void
    {
        $context = $this->treasuryContext();
        $box = CashBox::query()->where('company_id', $context['company']->id)
            ->where('branch_id', $context['branch']->id)->firstOrFail();
        app(CashBoxCustodianService::class)->assign($box, [
            'user_id' => $context['cashier']->id, 'valid_from' => '2040-01-01',
            'valid_to' => '2040-12-31', 'can_receive' => true, 'can_pay' => true,
            'can_transfer' => true, 'is_primary' => true,
        ]);
        $session = app(CashBoxSessionService::class)->open([
            'cash_box_id' => $box->id, 'custodian_user_id' => $context['cashier']->id,
            'business_date' => '2040-01-10', 'opening_notes' => 'Controlled opening',
        ]);
        $this->expectSecondOpenFailure($box->id, $context['cashier']->id);
        app(CashBoxSessionService::class)->action($session, 'start_counting');
        $count = app(CashBoxCountService::class)->create($session->fresh(), [
            'count_type' => 'closing',
            'lines' => [['denomination' => 50, 'quantity' => 2, 'line_total' => 999999]],
            'notes' => 'Server-calculated count',
        ]);
        $this->assertSame('100.0000', $count->counted_total);
        $this->assertSame('100.0000', $count->lines->first()->line_total);
        app(CashBoxCountService::class)->action($count, 'submit');
        $this->switchTreasuryActor($context['approver']);
        app(CashBoxCountService::class)->action($count->fresh(), 'review');
        app(CashBoxCountService::class)->action($count->fresh(), 'approve');
        app(CashBoxSessionService::class)->action($session->fresh(), 'submit');
        app(CashBoxSessionService::class)->action($session->fresh(), 'approve');
        try {
            app(CashBoxSessionService::class)->action($session->fresh(), 'close');
            $this->fail('Session closed with an unresolved count difference.');
        } catch (BusinessRuleException) {
            $this->assertSame('approved', $session->fresh()->status);
        }
        $adjustment = app(CashOverShortService::class)->create($count->fresh(), 'Reviewed cash over');
        app(CashOverShortService::class)->action($adjustment, 'submit');
        app(CashOverShortService::class)->action($adjustment->fresh(), 'approve');
        app(CashOverShortService::class)->action($adjustment->fresh(), 'post');
        $closed = app(CashBoxSessionService::class)->action($session->fresh(), 'close');
        $this->assertSame('closed', $closed->status);
        $this->assertNull($closed->active_guard);
        $this->assertSame('posted', CashOverShortAdjustment::query()->findOrFail($adjustment->id)->status);
        $this->expectException(BusinessRuleException::class);
        app(CashBoxSessionService::class)->action($closed, 'start_counting');
    }

    public function test_cash_receipt_posts_once_and_reversal_is_exact(): void
    {
        $context = $this->treasuryContext();
        $box = CashBox::query()->where('company_id', $context['company']->id)
            ->where('branch_id', $context['branch']->id)->firstOrFail();
        $operation = app(CashOperationService::class)->create('receipt', [
            'branch_id' => $context['branch']->id, 'cash_box_id' => $box->id,
            'receipt_type' => 'other_income', 'document_date' => '2040-01-10',
            'currency_id' => $context['currency']->id, 'exchange_rate' => 1,
            'amount' => 250, 'offset_account_id' => $this->treasuryAccount($context, '410000')->id,
            'description' => 'General cash receipt',
        ]);
        app(CashOperationService::class)->action($operation, 'submit');
        $this->switchTreasuryActor($context['approver']);
        app(CashOperationService::class)->action($operation->fresh(), 'approve');
        $posted = app(CashOperationService::class)->action($operation->fresh(), 'post');
        $first = app(CashOperationPostingService::class)->post($posted);
        $second = app(CashOperationPostingService::class)->post($posted);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->total_debit, $first->total_credit);
        $before = JournalEntry::query()->count();
        $reversed = app(CashOperationService::class)
            ->action($posted->fresh(), 'reverse', 'Approved cash receipt reversal', '2040-01-11');
        $this->assertSame('reversed', $reversed->status);
        $this->assertSame($before + 1, JournalEntry::query()->count());
        $this->assertNotNull($reversed->reversal_journal_entry_id);
        $this->assertInstanceOf(CashReceipt::class, $reversed);
    }

    public function test_cash_payment_uses_server_workflow_and_balanced_journal(): void
    {
        $context = $this->treasuryContext();
        $box = CashBox::query()->where('company_id', $context['company']->id)
            ->where('branch_id', $context['branch']->id)->firstOrFail();
        $payment = app(CashOperationService::class)->create('payment', [
            'branch_id' => $context['branch']->id, 'cash_box_id' => $box->id,
            'payment_type' => 'general_expense', 'document_date' => '2040-01-10',
            'currency_id' => $context['currency']->id, 'exchange_rate' => 1,
            'amount' => 75, 'offset_account_id' => $this->treasuryAccount($context, '640000')->id,
            'description' => 'General cash payment',
        ]);
        app(CashOperationService::class)->action($payment, 'submit');
        $this->switchTreasuryActor($context['approver']);
        app(CashOperationService::class)->action($payment->fresh(), 'approve');
        $posted = app(CashOperationService::class)->action($payment->fresh(), 'post');
        $journal = JournalEntry::query()->with('lines')->findOrFail($posted->journal_entry_id);
        $this->assertSame('posted', $posted->status);
        $this->assertSame($journal->total_debit, $journal->total_credit);
        $this->assertSame(2, $journal->lines->count());
    }

    private function expectSecondOpenFailure(int $cashBoxId, int $custodianId): void
    {
        try {
            app(CashBoxSessionService::class)->open([
                'cash_box_id' => $cashBoxId, 'custodian_user_id' => $custodianId,
                'business_date' => '2040-01-10',
            ]);
            $this->fail('A second active cash session was accepted.');
        } catch (BusinessRuleException) {
            $this->assertTrue(true);
        }
    }
}
