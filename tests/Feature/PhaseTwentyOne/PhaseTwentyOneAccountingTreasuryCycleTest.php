<?php

namespace Tests\Feature\PhaseTwentyOne;

use App\Models\BankAccount;
use App\Models\CashBox;
use App\Models\JournalEntry;
use App\Models\TreasuryTransfer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesPhaseTwentyOneUat;
use Tests\TestCase;

class PhaseTwentyOneAccountingTreasuryCycleTest extends TestCase
{
    use DatabaseTransactions;
    use UsesPhaseTwentyOneUat;

    public function test_uat_accounting_and_treasury_foundation_has_no_stored_or_posted_balance(): void
    {
        $this->setUpUatContext('uat.accountant@sevenways.test');

        $this->assertSame(3, CashBox::query()->where('company_id', $this->uatCompany->id)
            ->whereIn('code', ['UAT-CAI-CASH', 'UAT-GIZ-CASH', 'UAT-ALX-CASH'])->count());
        $this->assertSame(2, BankAccount::query()->where('company_id', $this->uatCompany->id)
            ->whereIn('account_code', ['UAT-BANK-CAI', 'UAT-BANK-GIZ'])->count());
        $this->assertFalse(Schema::hasColumn('cash_boxes', 'balance'));
        $this->assertFalse(Schema::hasColumn('bank_accounts', 'balance'));
        $this->assertSame(0, TreasuryTransfer::query()->where('company_id', $this->uatCompany->id)->count());
        $this->assertSame(0, JournalEntry::query()->where('company_id', $this->uatCompany->id)->count());
        $this->assertSame(0, DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $this->uatCompany->id)->count());
    }

    public function test_accountant_and_cashier_cannot_approve_treasury_operations(): void
    {
        $this->setUpUatContext();

        $this->assertFalse($this->uatUser('uat.accountant@sevenways.test')
            ->hasPermission('treasury.cash_receipts.approve'));
        $this->assertFalse($this->uatUser('uat.cairo.cashier@sevenways.test')
            ->hasPermission('treasury.cash_receipts.approve'));
        $this->assertTrue($this->uatUser('uat.treasury.manager@sevenways.test')
            ->hasPermission('treasury.cash_receipts.approve'));
    }
}
