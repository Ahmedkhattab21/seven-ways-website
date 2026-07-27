<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\BankAccount;
use App\Models\CashBox;
use App\Models\CustomerPayment;
use App\Models\PaymentMethodAccountMapping;
use App\Models\SupplierPayment;
use App\Models\TreasuryTransfer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TreasuryBalanceService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function bank(BankAccount $account): array
    {
        $account = BankAccount::query()->where('company_id', $this->tenant->companyId())->findOrFail($account->id);

        return $this->calculate($account, $account->gl_account_id, $account->branch_id, 'bank_account_id');
    }

    public function cashBox(CashBox $box): array
    {
        $box = CashBox::query()->where('company_id', $this->tenant->companyId())->findOrFail($box->id);

        return $this->calculate($box, $box->gl_account_id, $box->branch_id, 'cash_box_id');
    }

    private function calculate(Model $treasury, int $glAccountId, ?int $branchId, string $mappingColumn): array
    {
        $book = DB::table('journal_entry_lines as lines')->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')
            ->where('entries.company_id', $this->tenant->companyId())->where('entries.status', 'posted')
            ->where('lines.account_id', $glAccountId)
            ->when($branchId, fn ($query) => $query->where('lines.branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(lines.base_debit_amount - lines.base_credit_amount), 0) balance')->value('balance');
        $mappingIds = PaymentMethodAccountMapping::query()->where('company_id', $this->tenant->companyId())
            ->where($mappingColumn, $treasury->id)->where('is_active', true)->pluck('payment_method_id');
        $receipts = CustomerPayment::query()->where('company_id', $this->tenant->companyId())
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('payment_method_id', $mappingIds)->whereNotIn('status', ['approved', 'cancelled'])->sum('amount');
        $payments = SupplierPayment::query()->where('company_id', $this->tenant->companyId())
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('payment_method_id', $mappingIds)->whereNotIn('status', ['processed', 'cancelled'])->sum('amount');
        $pending = TreasuryTransfer::query()->where('company_id', $this->tenant->companyId())
            ->whereIn('status', ['pending_approval', 'approved', 'ready_for_processing'])
            ->where('from_'.$mappingColumn, $treasury->id)->sum(DB::raw('amount + fees_amount'));
        $available = bcsub(bcadd((string) $book, (string) $receipts, 4), bcadd((string) $payments, (string) $pending, 4), 4);

        return [
            'book_balance' => number_format((float) $book, 4, '.', ''),
            'unposted_receipts' => number_format((float) $receipts, 4, '.', ''),
            'unposted_payments' => number_format((float) $payments, 4, '.', ''),
            'pending_transfers' => number_format((float) $pending, 4, '.', ''),
            'available_book_balance' => $available,
            'last_reconciled_date' => $treasury instanceof BankAccount ? $treasury->last_reconciled_date?->toDateString() : null,
        ];
    }
}
