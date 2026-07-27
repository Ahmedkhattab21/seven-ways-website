<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\CashBox;
use App\Models\JournalEntry;
use App\Models\TreasuryTransfer;
use App\Services\TreasuryTransferProcessingService;
use App\Services\TreasuryTransferService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class PhaseFifteenTreasuryTransferProcessingTest extends TestCase
{
    use BuildsTreasuryOperationsContext;
    use DatabaseTransactions;

    public function test_all_transfer_directions_post_balanced_once_and_bank_fee_is_separate(): void
    {
        $context = $this->treasuryContext();
        $bank = $this->activeTreasuryBank($context);
        $secondBank = $this->secondTreasuryBank($context);
        $boxes = CashBox::query()->where('company_id', $context['company']->id)->get();
        $cash = $boxes->firstWhere('branch_id', $context['branch']->id);
        $secondCash = $boxes->firstWhere('branch_id', $context['secondBranch']->id);
        foreach ([$context['user'], $context['approver']] as $actor) {
            $actor->accessibleBranches()->syncWithoutDetaching([
                $context['secondBranch']->id => ['is_default' => false, 'can_view' => true],
            ]);
        }
        $directions = [
            ['bank', $bank->id, 'cash_box', $cash->id, $context['branch']->id, 5],
            ['cash_box', $cash->id, 'bank', $bank->id, $context['branch']->id, 0],
            ['bank', $bank->id, 'bank', $secondBank->id, $context['branch']->id, 0],
            ['cash_box', $cash->id, 'cash_box', $secondCash->id, $context['secondBranch']->id, 0],
        ];
        foreach ($directions as $index => [$fromType, $fromId, $toType, $toId, $destinationBranch, $fees]) {
            $this->switchTreasuryActor($context['user']);
            $transfer = app(TreasuryTransferService::class)->create([
                'transfer_type' => 'transfer', 'from_type' => $fromType,
                'from_bank_account_id' => $fromType === 'bank' ? $fromId : null,
                'from_cash_box_id' => $fromType === 'cash_box' ? $fromId : null,
                'to_type' => $toType, 'to_bank_account_id' => $toType === 'bank' ? $toId : null,
                'to_cash_box_id' => $toType === 'cash_box' ? $toId : null,
                'branch_id' => $context['branch']->id, 'destination_branch_id' => $destinationBranch,
                'currency_id' => $context['currency']->id, 'exchange_rate' => 1,
                'amount' => 100 + $index, 'fees_amount' => $fees,
                'transfer_date' => '2040-01-10',
            ]);
            app(TreasuryTransferService::class)->action($transfer, 'submit');
            $this->switchTreasuryActor($context['approver']);
            app(TreasuryTransferService::class)->action($transfer->fresh(), 'approve');
            $processed = app(TreasuryTransferProcessingService::class)->process($transfer->fresh());
            $journal = JournalEntry::query()->with('lines')->findOrFail($processed->journal_entry_id);
            $this->assertSame('completed', $processed->status);
            $this->assertSame($journal->total_debit, $journal->total_credit);
            $this->assertSame($fees ? 4 : 2, $journal->lines->count());
            $before = JournalEntry::query()->count();
            $retry = app(TreasuryTransferProcessingService::class)->process($processed->fresh());
            $this->assertSame($processed->journal_entry_id, $retry->journal_entry_id);
            $this->assertSame($before, JournalEntry::query()->count());
        }
    }

    public function test_transfer_reversal_is_exact_and_treasury_module_lock_fails_safely(): void
    {
        $context = $this->treasuryContext();
        $bank = $this->activeTreasuryBank($context);
        $cash = CashBox::query()->where('company_id', $context['company']->id)
            ->where('branch_id', $context['branch']->id)->firstOrFail();
        $transfer = $this->approvedTransfer($context, $bank->id, $cash->id);
        $this->switchTreasuryActor($context['approver']);
        $processed = app(TreasuryTransferProcessingService::class)->process($transfer);
        $reversed = app(TreasuryTransferProcessingService::class)
            ->reverse($processed, 'Approved exact treasury reversal', '2040-01-11');
        $original = JournalEntry::query()->with('lines')->findOrFail($processed->journal_entry_id);
        $opposite = JournalEntry::query()->with('lines')->findOrFail($reversed->reversal_journal_entry_id);
        $this->assertSame('reversed', $reversed->status);
        $this->assertSame($original->total_debit, $opposite->total_credit);
        $this->assertSame($original->total_credit, $opposite->total_debit);

        $second = $this->approvedTransfer($context, $bank->id, $cash->id);
        $context['period']->forceFill(['locked_modules' => ['treasury']])->save();
        try {
            app(TreasuryTransferProcessingService::class)->process($second);
            $this->fail('Treasury transfer posted in a locked module.');
        } catch (BusinessRuleException) {
            $this->assertSame('failed', $second->fresh()->status);
            $this->assertNull($second->fresh()->journal_entry_id);
        }
    }

    private function approvedTransfer(array $context, int $bankId, int $cashId): TreasuryTransfer
    {
        $this->switchTreasuryActor($context['user']);
        $transfer = app(TreasuryTransferService::class)->create([
            'from_type' => 'bank', 'from_bank_account_id' => $bankId, 'from_cash_box_id' => null,
            'to_type' => 'cash_box', 'to_bank_account_id' => null, 'to_cash_box_id' => $cashId,
            'branch_id' => $context['branch']->id, 'destination_branch_id' => $context['branch']->id,
            'currency_id' => $context['currency']->id, 'exchange_rate' => 1,
            'amount' => 125, 'fees_amount' => 0, 'transfer_date' => '2040-01-10',
        ]);
        app(TreasuryTransferService::class)->action($transfer, 'submit');
        $this->switchTreasuryActor($context['approver']);
        app(TreasuryTransferService::class)->action($transfer->fresh(), 'approve');

        return $transfer->fresh();
    }
}
