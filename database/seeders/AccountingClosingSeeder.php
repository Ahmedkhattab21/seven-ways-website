<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Models\YearEndClosingSetting;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

class AccountingClosingSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'accounting.closing.view', 'accounting.closing.start', 'accounting.closing.validate',
            'accounting.closing.review', 'accounting.closing.approve', 'accounting.closing.cancel',
            'accounting.periods.soft_close', 'accounting.periods.hard_close', 'accounting.periods.lock',
            'accounting.periods.reopen', 'accounting.periods.reopen_locked', 'accounting.periods.manage_module_locks',
            'accounting.adjustments.view', 'accounting.adjustments.create', 'accounting.adjustments.update',
            'accounting.adjustments.submit', 'accounting.adjustments.approve', 'accounting.adjustments.post',
            'accounting.adjustments.schedule_reversal', 'accounting.adjustments.cancel_reversal',
            'accounting.year_end.view', 'accounting.year_end.start', 'accounting.year_end.validate',
            'accounting.year_end.review', 'accounting.year_end.approve', 'accounting.year_end.execute',
            'accounting.year_end.lock', 'accounting.year_end.reopen',
            'accounting.closing_exceptions.view', 'accounting.closing_exceptions.resolve',
            'accounting.closing_exceptions.waive', 'accounting.closing_settings.view',
            'accounting.closing_settings.update', 'accounting.closing_reports.view', 'accounting.closing_reports.export',
        ];
        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(['name' => $permission], ['display_name' => $permission]);
        }
        $all = Permission::query()->whereIn('name', $permissions)->pluck('id');
        Role::query()->whereIn('name', ['system_admin', 'company_owner', 'finance_manager'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($all));
        $accountant = Permission::query()->whereIn('name', [
            'accounting.closing.view', 'accounting.closing.validate', 'accounting.adjustments.view',
            'accounting.adjustments.create', 'accounting.adjustments.update', 'accounting.adjustments.submit',
            'accounting.adjustments.schedule_reversal', 'accounting.closing_exceptions.view',
            'accounting.closing_reports.view',
        ])->pluck('id');
        Role::query()->where('name', 'accountant')->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($accountant));
        $review = Permission::query()->whereIn('name', [
            'accounting.closing.view', 'accounting.closing.review', 'accounting.closing.approve',
            'accounting.year_end.view', 'accounting.year_end.review', 'accounting.year_end.approve',
            'accounting.closing_reports.view',
        ])->pluck('id');
        Role::query()->where('name', 'general_manager')->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($review));
        Company::query()->with('users')->get()->each(fn (Company $company) => $this->company($company));
    }

    private function company(Company $company): void
    {
        $actor = $company->users->sortBy('id')->first();
        $parent = Account::query()->where('company_id', $company->id)->where('account_code', '300000')->first();
        $group = AccountGroup::query()->where('company_id', $company->id)->where('code', '300')->first();
        if (! $actor || ! $parent || ! $group) {
            return;
        }
        $summary = $this->account($company->id, $actor->id, $parent, $group, '320000', 'Income Summary');
        $retained = $this->account($company->id, $actor->id, $parent, $group, '330000', 'Retained Earnings');
        YearEndClosingSetting::query()->updateOrCreate(['company_id' => $company->id], [
            'income_summary_account_id' => $summary->id, 'retained_earnings_account_id' => $retained->id,
            'close_revenue_directly_to_retained_earnings' => false, 'create_opening_journal' => true,
            'auto_create_next_fiscal_year' => true, 'auto_generate_next_periods' => true,
            'lock_year_after_close' => false, 'require_all_reconciliations' => true,
        ]);
        $sequence = DocumentSequence::query()->firstOrNew([
            'scope_key' => DocumentNumberService::scopeKey($company->id, null, 'accounting_closing_run', null),
        ]);
        if (! $sequence->exists) {
            $sequence->forceFill([
                'company_id' => $company->id, 'branch_id' => null, 'document_type' => 'accounting_closing_run',
                'prefix' => 'ACC-CL-{YYYY}-', 'current_number' => 0, 'padding' => 6,
                'reset_period' => 'yearly', 'period_key' => null, 'is_active' => true,
            ])->save();
        }
    }

    private function account(int $companyId, int $actorId, Account $parent, AccountGroup $group, string $code, string $name): Account
    {
        $account = Account::withTrashed()->firstOrNew(['company_id' => $companyId, 'account_code' => $code]);
        $account->forceFill([
            'company_id' => $companyId, 'account_code' => $code, 'account_type_id' => $group->account_type_id,
            'account_group_id' => $group->id, 'parent_account_id' => $parent->id, 'name_ar' => $name,
            'account_level' => $parent->account_level + 1, 'account_path' => $parent->account_path,
            'normal_balance' => 'credit', 'is_header' => false, 'is_posting' => true,
            'is_control_account' => false, 'is_system' => true, 'is_active' => true,
            'allow_manual_entry' => false, 'created_by' => $actorId, 'deleted_at' => null,
        ])->save();
        $account->forceFill(['account_path' => $parent->account_path.'/'.$account->id])->saveQuietly();

        return $account;
    }
}
