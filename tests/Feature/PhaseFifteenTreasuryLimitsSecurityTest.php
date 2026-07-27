<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\CashBoxSession;
use App\Models\CashReceipt;
use App\Models\Cheque;
use App\Models\JournalEntry;
use App\Models\MerchantSettlement;
use App\Models\TreasuryApprovalLimit;
use App\Models\TreasuryTransfer;
use App\Services\TreasuryApprovalLimitService;
use Database\Seeders\TreasuryOperationsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class PhaseFifteenTreasuryLimitsSecurityTest extends TestCase
{
    use BuildsTreasuryOperationsContext;
    use DatabaseTransactions;

    public function test_user_and_branch_limit_precedence_and_overlap_are_backend_enforced(): void
    {
        $context = $this->treasuryContext();
        $service = app(TreasuryApprovalLimitService::class);
        $roleId = $context['approver']->roles()->value('roles.id');
        $service->save($this->limitData($context, [
            'role_id' => $roleId, 'branch_id' => $context['branch']->id, 'maximum_amount' => 5000,
        ]));
        $service->save($this->limitData($context, [
            'role_id' => null, 'user_id' => $context['approver']->id,
            'branch_id' => $context['branch']->id, 'maximum_amount' => 1000,
        ]));
        $service->assert(
            $context['approver'], 'cash_payment', 'approve',
            $context['currency']->id, '900', $context['branch']->id
        );
        try {
            $service->assert(
                $context['approver'], 'cash_payment', 'approve',
                $context['currency']->id, '1500', $context['branch']->id
            );
            $this->fail('Role limit bypassed the more specific user limit.');
        } catch (BusinessRuleException) {
            $this->assertTrue(true);
        }
        $this->expectException(BusinessRuleException::class);
        $service->save($this->limitData($context, [
            'role_id' => null, 'user_id' => $context['approver']->id,
            'branch_id' => $context['branch']->id, 'maximum_amount' => 2000,
        ]));
    }

    public function test_schema_has_no_stored_balances_and_models_reject_protected_fields(): void
    {
        $context = $this->treasuryContext();
        foreach ([
            'cash_box_sessions', 'cash_box_counts', 'cash_box_count_lines',
            'cash_over_short_adjustments', 'cash_receipts', 'cash_payments', 'cheques',
            'cheque_status_histories', 'cheque_endorsements', 'merchant_settlements',
            'merchant_settlement_lines', 'treasury_approval_limits',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertFalse(Schema::hasColumn('cash_boxes', 'balance'));
        $this->assertFalse(Schema::hasColumn('bank_accounts', 'balance'));
        $receipt = new CashReceipt([
            'company_id' => $context['company']->id, 'status' => 'posted',
            'document_number' => 'SPOOF', 'journal_entry_id' => 1,
        ]);
        $this->assertNull($receipt->company_id);
        $this->assertNull($receipt->status);
        $this->assertNull($receipt->document_number);
        $this->assertNull($receipt->journal_entry_id);
        $cheque = new Cheque(['status' => 'cleared', 'source_type' => 'spoof', 'source_id' => 999]);
        $this->assertNull($cheque->status);
        $this->assertNull($cheque->source_type);
        $this->assertNull($cheque->source_id);
    }

    public function test_operations_seeder_is_idempotent_and_creates_no_transactions(): void
    {
        $this->treasuryContext();
        $before = [
            TreasuryApprovalLimit::query()->count(), TreasuryTransfer::query()->count(),
            CashBoxSession::query()->count(), Cheque::query()->count(),
            MerchantSettlement::query()->count(), JournalEntry::query()->count(),
        ];
        app(TreasuryOperationsSeeder::class)->run();
        app(TreasuryOperationsSeeder::class)->run();
        $this->assertSame($before, [
            TreasuryApprovalLimit::query()->count(), TreasuryTransfer::query()->count(),
            CashBoxSession::query()->count(), Cheque::query()->count(),
            MerchantSettlement::query()->count(), JournalEntry::query()->count(),
        ]);
    }

    private function limitData(array $context, array $overrides): array
    {
        return $overrides + [
            'branch_id' => null, 'role_id' => null, 'user_id' => null,
            'operation_type' => 'cash_payment', 'currency_id' => $context['currency']->id,
            'minimum_amount' => 0, 'maximum_amount' => 10000, 'approval_level' => 1,
            'can_create' => true, 'can_submit' => true, 'can_approve' => true, 'can_post' => true,
            'is_active' => true, 'valid_from' => '2026-01-01', 'valid_to' => '2041-12-31',
        ];
    }
}
