<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\CashBox;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\PaymentMethodAccountMapping;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TreasuryApprovalLimit;
use App\Services\AccountantCashSessionPermissionReconciler;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

class TreasuryOperationsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'treasury.transfers.process', 'treasury.transfers.reverse',
            'treasury.cash_sessions.view', 'treasury.cash_sessions.open', 'treasury.cash_sessions.count',
            'treasury.cash_sessions.review',
            'treasury.cash_sessions.submit', 'treasury.cash_sessions.approve', 'treasury.cash_sessions.close',
            'treasury.cash_sessions.reopen', 'treasury.cash_sessions.override_custodian',
            'treasury.cash_receipts.view', 'treasury.cash_receipts.create', 'treasury.cash_receipts.submit',
            'treasury.cash_receipts.approve', 'treasury.cash_receipts.post', 'treasury.cash_receipts.reverse',
            'treasury.cash_payments.view', 'treasury.cash_payments.create', 'treasury.cash_payments.submit',
            'treasury.cash_payments.approve', 'treasury.cash_payments.post', 'treasury.cash_payments.reverse',
            'treasury.cash_over_short.view', 'treasury.cash_over_short.approve', 'treasury.cash_over_short.post',
            'treasury.cheques.view', 'treasury.cheques.view_sensitive', 'treasury.cheques.create',
            'treasury.cheques.submit', 'treasury.cheques.approve', 'treasury.cheques.deposit',
            'treasury.cheques.present', 'treasury.cheques.clear', 'treasury.cheques.bounce',
            'treasury.cheques.return', 'treasury.cheques.cancel', 'treasury.cheques.replace',
            'treasury.cheques.endorse', 'treasury.merchant_settlements.view',
            'treasury.merchant_settlements.create', 'treasury.merchant_settlements.submit',
            'treasury.merchant_settlements.approve', 'treasury.merchant_settlements.post',
            'treasury.merchant_settlements.reverse', 'treasury.approval_limits.view',
            'treasury.approval_limits.manage', 'treasury.approval_limits.unlimited',
            'treasury.reports.view',
        ];
        foreach ($permissions as $name) {
            Permission::query()->updateOrCreate(['name' => $name], ['display_name' => $name]);
        }
        $all = Permission::query()->whereIn('name', $permissions)->pluck('id');
        Role::query()->whereIn('name', ['system_admin', 'company_owner', 'finance_manager'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($all));
        $cashierPermissions = Permission::query()->whereIn('name', [
            'treasury.cash_sessions.view', 'treasury.cash_sessions.open', 'treasury.cash_sessions.count',
            'treasury.cash_sessions.submit', 'treasury.cash_receipts.view', 'treasury.cash_receipts.create',
            'treasury.cash_receipts.submit', 'treasury.cash_payments.view', 'treasury.cash_payments.create',
            'treasury.cash_payments.submit', 'treasury.cheques.view', 'treasury.cheques.create',
            'treasury.cheques.submit',
        ])->pluck('id');
        Role::query()->whereIn('name', ['cashier', 'receptionist'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($cashierPermissions));
        app(AccountantCashSessionPermissionReconciler::class)->reconcile();

        Company::query()->with(['branches', 'users'])->get()->each(fn (Company $company) => $this->company($company));
    }

    private function company(Company $company): void
    {
        $actor = $company->users->sortBy('id')->first();
        if (! $actor || ! $company->currency_id) {
            return;
        }
        $chequesReceivable = $this->account($company, $actor->id, '116000', 'شيكات تحت التحصيل', '110000', '113');
        $merchantClearing = $this->account($company, $actor->id, '117000', 'تحصيلات نقاط البيع', '110000', '113');
        $this->account($company, $actor->id, '214000', 'شيكات واجبة الدفع', '210000', '211');
        $merchantFees = $this->account($company, $actor->id, '651000', 'رسوم نقاط البيع والبنوك', '600000', '600');
        $overShort = Account::query()->where('company_id', $company->id)->where('account_code', '650000')->first();
        CashBox::query()->where('company_id', $company->id)->whereNull('over_short_account_id')
            ->update(['over_short_account_id' => $overShort?->id]);

        foreach ($company->branches as $branch) {
            foreach ([
                'cash_box_session' => 'CS', 'cash_receipt' => 'CR', 'cash_payment' => 'CP',
                'cheque_received' => 'RCH', 'cheque_issued' => 'ICH', 'merchant_settlement' => 'MS',
            ] as $type => $prefix) {
                $this->sequence($company->id, $branch->id, $type, $branch->code.'-'.$prefix.'-{YYYY}-');
            }
            PaymentMethodAccountMapping::query()->where('company_id', $company->id)
                ->where('branch_id', $branch->id)->whereHas('paymentMethod', fn ($q) => $q->where('is_cash', false))
                ->get()->each(function (PaymentMethodAccountMapping $mapping) use (
                    $merchantClearing, $merchantFees
                ) {
                    $mapping->forceFill([
                        'clearing_account_id' => $mapping->clearing_account_id ?: $merchantClearing->id,
                        'fees_account_id' => $mapping->fees_account_id ?: $merchantFees->id,
                    ])->save();
                });
        }
        $role = Role::query()->where('company_id', $company->id)
            ->whereIn('name', ['finance_manager', 'company_owner'])->orderByRaw("name = 'finance_manager' desc")->first();
        if (! $role) {
            return;
        }
        foreach ([
            'treasury_transfer', 'cash_receipt', 'cash_payment', 'cash_over_short',
            'received_cheque', 'issued_cheque', 'cheque_clearance', 'cheque_bounce', 'merchant_settlement',
        ] as $operation) {
            $limit = TreasuryApprovalLimit::query()->firstOrNew([
                'company_id' => $company->id, 'branch_id' => null, 'role_id' => $role->id,
                'user_id' => null, 'operation_type' => $operation, 'currency_id' => $company->currency_id,
                'valid_from' => '2026-01-01',
            ]);
            if (! $limit->exists) {
                $limit->forceFill([
                    'company_id' => $company->id, 'branch_id' => null, 'role_id' => $role->id,
                    'operation_type' => $operation, 'currency_id' => $company->currency_id,
                    'minimum_amount' => 0, 'maximum_amount' => 100000, 'approval_level' => 1,
                    'can_create' => true, 'can_submit' => true, 'can_approve' => true,
                    'can_post' => true, 'is_active' => true, 'valid_from' => '2026-01-01',
                    'created_by' => $actor->id,
                ])->save();
            }
        }
        unset($chequesReceivable);
    }

    private function account(
        Company $company,
        int $actorId,
        string $code,
        string $name,
        string $parentCode,
        string $groupCode
    ): Account {
        $parent = Account::query()->where('company_id', $company->id)->where('account_code', $parentCode)->firstOrFail();
        $group = AccountGroup::query()->where('company_id', $company->id)->where('code', $groupCode)->firstOrFail();
        $account = Account::withTrashed()->firstOrNew(['company_id' => $company->id, 'account_code' => $code]);
        $account->forceFill([
            'company_id' => $company->id, 'account_code' => $code,
            'account_type_id' => $group->account_type_id, 'account_group_id' => $group->id,
            'parent_account_id' => $parent->id, 'name_ar' => $name,
            'account_level' => $parent->account_level + 1, 'normal_balance' => $parent->normal_balance,
            'currency_id' => $company->currency_id, 'is_header' => false, 'is_posting' => true,
            'is_control_account' => false, 'is_system' => true, 'is_active' => true,
            'allow_manual_entry' => false, 'created_by' => $actorId, 'deleted_at' => null,
        ])->save();
        $account->forceFill(['account_path' => $parent->account_path.'/'.$account->id])->saveQuietly();

        return $account;
    }

    private function sequence(int $companyId, int $branchId, string $type, string $prefix): void
    {
        $sequence = DocumentSequence::query()->firstOrNew([
            'scope_key' => DocumentNumberService::scopeKey($companyId, $branchId, $type, null),
        ]);
        if (! $sequence->exists) {
            $sequence->forceFill([
                'company_id' => $companyId, 'branch_id' => $branchId, 'document_type' => $type,
                'prefix' => $prefix, 'current_number' => 0, 'padding' => 6,
                'reset_period' => 'yearly', 'period_key' => null, 'is_active' => true,
            ])->save();
        }
    }
}
