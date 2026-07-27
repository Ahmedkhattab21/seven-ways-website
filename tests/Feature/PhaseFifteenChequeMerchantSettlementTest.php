<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\JournalEntry;
use App\Services\AccountingPostingService;
use App\Services\ChequeService;
use App\Services\MerchantSettlementPostingService;
use App\Services\MerchantSettlementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class PhaseFifteenChequeMerchantSettlementTest extends TestCase
{
    use BuildsTreasuryOperationsContext;
    use DatabaseTransactions;

    public function test_received_cheque_clearance_bounce_history_and_duplicate_protection(): void
    {
        $context = $this->treasuryContext();
        $bank = $this->activeTreasuryBank($context);
        $data = [
            'branch_id' => $context['branch']->id, 'direction' => 'received',
            'cheque_number' => '00990011', 'bank_id' => $bank->bank_id,
            'bank_account_id' => $bank->id, 'drawer_name' => 'Masked Drawer',
            'currency_id' => $context['currency']->id, 'amount' => 500,
            'issue_date' => '2040-01-05', 'due_date' => '2040-01-10',
            'received_date' => '2040-01-05',
            'clearing_account_id' => $this->treasuryAccount($context, '116000')->id,
            'offset_account_id' => $this->treasuryAccount($context, '113000')->id,
        ];
        $cheque = app(ChequeService::class)->create($data);
        $this->assertStringEndsWith('0011', $cheque->maskedNumber());
        try {
            app(ChequeService::class)->create($data);
            $this->fail('Duplicate cheque number was accepted in the same bank scope.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, Cheque::query()->where('company_id', $context['company']->id)->count());
        }
        app(ChequeService::class)->action($cheque, 'submit');
        $this->switchTreasuryActor($context['approver']);
        app(ChequeService::class)->action($cheque->fresh(), 'approve');
        app(ChequeService::class)->action($cheque->fresh(), 'deposit', ['date' => '2040-01-10']);
        $cleared = app(ChequeService::class)->action($cheque->fresh(), 'clear', ['date' => '2040-01-11']);
        $this->assertNotNull($cleared->clearance_journal_entry_id);
        try {
            app(ChequeService::class)->action($cleared->fresh(), 'clear', ['date' => '2040-01-11']);
            $this->fail('Cheque was cleared twice.');
        } catch (BusinessRuleException) {
            $this->assertSame('cleared', $cleared->fresh()->status);
        }
        $bounced = app(ChequeService::class)->action($cleared->fresh(), 'bounce', [
            'date' => '2040-01-12', 'reason' => 'Insufficient drawer funds',
        ]);
        $clearance = JournalEntry::query()->findOrFail($cleared->clearance_journal_entry_id);
        $bounce = JournalEntry::query()->findOrFail($bounced->bounce_journal_entry_id);
        $this->assertSame('bounced', $bounced->status);
        $this->assertSame($clearance->total_debit, $bounce->total_credit);
        $this->assertGreaterThanOrEqual(6, $bounced->histories()->count());
        $replacement = app(ChequeService::class)->replace($bounced, [
            'replacement_cheque_number' => '00990012',
            'replacement_issue_date' => '2040-01-13', 'replacement_due_date' => '2040-02-13',
        ]);
        $this->assertSame('replaced', $bounced->fresh()->status);
        $this->assertSame('cheque_replacement', $replacement->source_type);
        $this->assertSame($bounced->id, $replacement->source_id);
    }

    public function test_issued_cheque_presentation_and_clearance_post_once(): void
    {
        $context = $this->treasuryContext();
        $bank = $this->activeTreasuryBank($context);
        $cheque = app(ChequeService::class)->create([
            'branch_id' => $context['branch']->id, 'direction' => 'issued',
            'cheque_number' => '77001122', 'bank_id' => $bank->bank_id,
            'bank_account_id' => $bank->id, 'beneficiary_name' => 'Supplier',
            'currency_id' => $context['currency']->id, 'amount' => 300,
            'issue_date' => '2040-01-05', 'due_date' => '2040-01-10',
            'clearing_account_id' => $this->treasuryAccount($context, '214000')->id,
            'offset_account_id' => $this->treasuryAccount($context, '211000')->id,
        ]);
        app(ChequeService::class)->action($cheque, 'submit');
        $this->switchTreasuryActor($context['approver']);
        app(ChequeService::class)->action($cheque->fresh(), 'approve');
        app(ChequeService::class)->action($cheque->fresh(), 'present', ['date' => '2040-01-10']);
        $cleared = app(ChequeService::class)->action($cheque->fresh(), 'clear', ['date' => '2040-01-11']);
        $journal = JournalEntry::query()->findOrFail($cleared->clearance_journal_entry_id);
        $this->assertSame('cleared', $cleared->status);
        $this->assertSame($journal->total_debit, $journal->total_credit);
    }

    public function test_merchant_totals_are_backend_owned_partial_and_post_once(): void
    {
        $context = $this->treasuryContext();
        $bank = $this->activeTreasuryBank($context);
        $customer = Customer::factory()->create([
            'company_id' => $context['company']->id,
            'created_branch_id' => $context['branch']->id,
            'assigned_branch_id' => $context['branch']->id,
            'created_by' => $context['user']->id,
        ]);
        $source = CustomerPayment::factory()->create([
            'company_id' => $context['company']->id, 'branch_id' => $context['branch']->id,
            'customer_id' => $customer->id, 'currency_id' => $context['currency']->id,
            'payment_method_id' => $context['method']->id, 'payment_date' => '2040-01-05',
            'amount' => 1000, 'unallocated_amount' => 1000, 'received_by' => $context['user']->id,
        ]);
        app(AccountingPostingService::class)->post($source);
        $settlement = app(MerchantSettlementService::class)->create([
            'branch_id' => $context['branch']->id, 'bank_account_id' => $bank->id,
            'payment_method_id' => $context['method']->id, 'settlement_reference' => 'SET-2040-001',
            'period_start' => '2040-01-01', 'period_end' => '2040-01-10',
            'settlement_date' => '2040-01-10', 'currency_id' => $context['currency']->id,
            'gross_amount' => 999999, 'fees_amount' => 20, 'tax_amount' => 0,
            'net_amount' => 1, 'lines' => [[
                'source_type' => 'customer_payment', 'source_id' => $source->id,
                'allocated_amount' => 600, 'gross_amount' => 1, 'net_amount' => 1,
            ]],
        ]);
        $this->assertSame('600.0000', $settlement->gross_amount);
        $this->assertSame('580.0000', $settlement->net_amount);
        try {
            app(MerchantSettlementService::class)->create([
                'branch_id' => $context['branch']->id, 'bank_account_id' => $bank->id,
                'payment_method_id' => $context['method']->id, 'settlement_reference' => 'SET-2040-002',
                'period_start' => '2040-01-01', 'period_end' => '2040-01-10',
                'settlement_date' => '2040-01-10', 'currency_id' => $context['currency']->id,
                'fees_amount' => 0, 'tax_amount' => 0, 'lines' => [[
                    'source_type' => 'customer_payment', 'source_id' => $source->id, 'allocated_amount' => 500,
                ]],
            ]);
            $this->fail('Merchant clearing source was over-allocated.');
        } catch (BusinessRuleException) {
            $this->assertTrue(true);
        }
        app(MerchantSettlementService::class)->action($settlement, 'submit');
        $this->switchTreasuryActor($context['approver']);
        app(MerchantSettlementService::class)->action($settlement->fresh(), 'approve');
        $posted = app(MerchantSettlementService::class)->action($settlement->fresh(), 'post');
        $first = app(MerchantSettlementPostingService::class)->post($posted);
        $second = app(MerchantSettlementPostingService::class)->post($posted);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('600.0000', $first->total_debit);
        $this->assertSame($first->total_debit, $first->total_credit);
    }
}
