<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\CashBox;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

class TreasuryFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'treasury.banks.view', 'treasury.banks.manage',
            'treasury.bank_accounts.view', 'treasury.bank_accounts.view_sensitive',
            'treasury.bank_accounts.create', 'treasury.bank_accounts.update',
            'treasury.bank_accounts.activate', 'treasury.bank_accounts.suspend',
            'treasury.bank_accounts.close', 'treasury.bank_accounts.manage_branch_access',
            'treasury.cash_boxes.view', 'treasury.cash_boxes.create', 'treasury.cash_boxes.update',
            'treasury.cash_boxes.activate', 'treasury.cash_boxes.suspend', 'treasury.cash_boxes.close',
            'treasury.cash_boxes.manage_custodians', 'treasury.mappings.view', 'treasury.mappings.update',
            'treasury.balances.view', 'treasury.balances.view_sensitive',
            'treasury.transfers.view', 'treasury.transfers.create', 'treasury.transfers.update',
            'treasury.transfers.submit', 'treasury.transfers.approve', 'treasury.transfers.cancel',
        ];
        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(['name' => $permission], ['display_name' => $permission]);
        }
        $all = Permission::query()->whereIn('name', $permissions)->pluck('id');
        Role::query()->whereIn('name', ['system_admin', 'company_owner', 'finance_manager'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($all));
        $accountant = Permission::query()->whereIn('name', [
            'treasury.banks.view', 'treasury.bank_accounts.view', 'treasury.bank_accounts.create',
            'treasury.cash_boxes.view', 'treasury.mappings.view', 'treasury.balances.view',
            'treasury.transfers.view', 'treasury.transfers.create', 'treasury.transfers.update',
            'treasury.transfers.submit', 'treasury.transfers.cancel',
        ])->pluck('id');
        Role::query()->where('name', 'accountant')->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($accountant));
        $approvals = Permission::query()->whereIn('name', [
            'treasury.banks.view', 'treasury.bank_accounts.view', 'treasury.cash_boxes.view',
            'treasury.balances.view', 'treasury.transfers.view', 'treasury.transfers.approve',
        ])->pluck('id');
        Role::query()->whereIn('name', ['general_manager', 'branch_manager'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($approvals));
        $custodian = Permission::query()->whereIn('name', [
            'treasury.cash_boxes.view', 'treasury.balances.view', 'treasury.transfers.view',
            'treasury.transfers.create', 'treasury.transfers.submit',
        ])->pluck('id');
        Role::query()->whereIn('name', ['cashier', 'receptionist'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($custodian));

        foreach ([
            ['SNB', 'البنك الأهلي السعودي', 'Saudi National Bank', 'NCBKSAJE'],
            ['RJHI', 'مصرف الراجحي', 'Al Rajhi Bank', 'RJHISARI'],
            ['RIBL', 'بنك الرياض', 'Riyad Bank', 'RIBLSARI'],
            ['SABB', 'البنك السعودي الأول', 'Saudi Awwal Bank', 'SABBSARI'],
        ] as [$code, $nameAr, $nameEn, $swift]) {
            $bank = Bank::withTrashed()->firstOrNew(['scope_key' => 'system:'.$code]);
            $bank->forceFill([
                'company_id' => null, 'scope_key' => 'system:'.$code, 'code' => $code,
                'name_ar' => $nameAr, 'name_en' => $nameEn, 'swift_code' => $swift,
                'is_system' => true, 'is_active' => true, 'deleted_at' => null,
            ])->save();
        }

        Company::query()->with(['branches', 'users'])->get()->each(fn (Company $company) => $this->company($company));
    }

    private function company(Company $company): void
    {
        $actor = $company->users->sortBy('id')->first();
        if (! $actor) {
            return;
        }
        foreach ($company->branches as $branch) {
            $gl = $this->cashAccount($company, $branch, $actor->id);
            $box = CashBox::withTrashed()->where('company_id', $company->id)
                ->where('code', 'MAIN-'.$branch->code)->first() ?? new CashBox;
            $box->forceFill([
                'company_id' => $company->id, 'code' => 'MAIN-'.$branch->code,
                'branch_id' => $branch->id, 'name' => 'الخزينة الرئيسية - '.$branch->name,
                'currency_id' => $company->currency_id, 'gl_account_id' => $gl->id,
                'status' => 'active', 'is_primary' => true, 'allows_receipts' => true,
                'allows_payments' => true, 'requires_shift_opening' => false,
                'created_by' => $actor->id, 'deleted_at' => null,
            ])->save();
            $sequence = DocumentSequence::query()->firstOrNew([
                'scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, 'treasury_transfer', null),
            ]);
            if (! $sequence->exists) {
                $sequence->forceFill([
                    'company_id' => $company->id, 'branch_id' => $branch->id,
                    'document_type' => 'treasury_transfer', 'prefix' => $branch->code.'-TR-{YYYY}-',
                    'current_number' => 0, 'padding' => 6, 'reset_period' => 'yearly',
                    'period_key' => null, 'is_active' => true,
                ])->save();
            }
        }
    }

    private function cashAccount(Company $company, Branch $branch, int $actorId): Account
    {
        $parent = Account::query()->where('company_id', $company->id)->where('account_code', '110000')->firstOrFail();
        $group = AccountGroup::query()->where('company_id', $company->id)->where('code', '111')->firstOrFail();
        $code = '111-CASH-'.$branch->code;
        $account = Account::withTrashed()->firstOrNew(['company_id' => $company->id, 'account_code' => $code]);
        $account->forceFill([
            'company_id' => $company->id, 'account_type_id' => $group->account_type_id,
            'account_group_id' => $group->id, 'parent_account_id' => $parent->id,
            'account_code' => $code, 'name_ar' => 'خزينة '.$branch->name,
            'account_level' => $parent->account_level + 1, 'normal_balance' => 'debit',
            'is_header' => false, 'is_posting' => true, 'requires_branch' => true,
            'is_control_account' => false, 'is_bank_account' => false, 'is_cash_account' => true,
            'is_active' => true, 'allow_manual_entry' => false, 'created_by' => $actorId, 'deleted_at' => null,
        ])->save();
        $account->forceFill(['account_path' => $parent->account_path.'/'.$account->id])->saveQuietly();

        return $account;
    }
}
