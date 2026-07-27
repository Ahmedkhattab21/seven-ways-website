<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\EmployeeExpenseCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TreasuryApprovalLimit;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

class EmployeeFinanceSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'employees.view', 'commissions.view', 'commissions.manage_rules',
            'commissions.calculate', 'commissions.submit', 'commissions.approve',
            'commissions.post', 'commissions.settle', 'commissions.reverse',
            'employee_expenses.view', 'employee_expenses.create',
            'employee_expenses.create_for_others', 'employee_expenses.submit',
            'employee_expenses.approve', 'employee_expenses.reject',
            'employee_expenses.post', 'employee_expenses.pay',
            'employee_expenses.reverse', 'employee_expenses.use_control_accounts',
            'employee_advances.view', 'employee_advances.create',
            'employee_advances.submit', 'employee_advances.approve',
            'employee_advances.disburse', 'employee_advances.settle',
            'employee_advances.close', 'employee_advances.reverse',
            'employee_finance.reports',
        ];
        foreach ($permissions as $name) {
            Permission::query()->updateOrCreate(['name' => $name], ['display_name' => $name]);
        }
        $all = Permission::query()->whereIn('name', $permissions)->pluck('id');
        Role::query()->whereIn('name', ['system_admin', 'company_owner', 'finance_manager'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($all));
        $accountantDenied = [
            'commissions.approve', 'employee_expenses.approve', 'employee_expenses.reject',
            'employee_advances.approve', 'employee_expenses.use_control_accounts',
        ];
        Role::query()->where('name', 'accountant')->get()->each(
            fn (Role $role) => $role->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('name', array_diff($permissions, $accountantDenied))->pluck('id')
            )
        );
        $managerPermissions = Permission::query()->whereIn('name', [
            'employees.view', 'commissions.view', 'commissions.approve',
            'employee_expenses.view', 'employee_expenses.approve', 'employee_expenses.reject',
            'employee_advances.view', 'employee_advances.approve', 'employee_finance.reports',
        ])->pluck('id');
        Role::query()->whereIn('name', ['general_manager', 'branch_manager'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($managerPermissions));

        Company::query()->with(['users', 'branches'])->get()->each(function (Company $company): void {
            $actor = $company->users->sortBy('id')->first();
            if (! $actor || ! $company->currency_id) {
                return;
            }
            $expense = $this->account($company, $actor->id, '652000', 'مصروف عمولات الموظفين', '600000', '600', 'debit');
            $claimExpense = $this->account($company, $actor->id, '653000', 'مصروفات الموظفين', '600000', '600', 'debit');
            $payable = $this->account($company, $actor->id, '215000', 'مستحقات الموظفين', '210000', '210', 'credit', 'employee_payables');
            $advance = $this->account($company, $actor->id, '118000', 'سلف وعهد الموظفين', '110000', '113', 'debit', 'employee_advances');
            foreach ([
                ['TRAVEL', 'انتقالات وسفر'], ['MEALS', 'وجبات ومهمات'],
                ['SUPPLIES', 'مستلزمات عمل'], ['OTHER', 'مصروفات أخرى'],
            ] as [$code, $name]) {
                $category = EmployeeExpenseCategory::withTrashed()->firstOrNew([
                    'company_id' => $company->id, 'code' => $code,
                ]);
                $category->forceFill([
                    'company_id' => $company->id, 'code' => $code,
                    'name' => $name, 'expense_account_id' => $claimExpense->id,
                    'tax_id' => null, 'is_active' => true,
                    'created_by' => $category->created_by ?: $actor->id,
                    'deleted_at' => null,
                ])->save();
            }
            foreach ($company->branches as $branch) {
                foreach ([
                    'employee_commission_settlement' => 'COM-SET',
                    'employee_expense_claim' => 'EXP',
                    'employee_advance' => 'ADV',
                ] as $type => $prefix) {
                    $period = now()->format('Y');
                    $scopeKey = DocumentNumberService::scopeKey(
                        $company->id, $branch->id, $type, $period
                    );
                    $sequence = DocumentSequence::query()->firstOrNew(
                        ['scope_key' => $scopeKey]
                    );
                    $sequence->forceFill(
                        [
                            'scope_key' => $scopeKey,
                            'company_id' => $company->id, 'branch_id' => $branch->id,
                            'document_type' => $type, 'reset_period' => 'yearly',
                            'prefix' => $branch->code.'-'.$prefix.'-{YYYY}-',
                            'current_number' => 0, 'padding' => 6, 'period_key' => $period,
                            'is_active' => true,
                        ]
                    )->save();
                }
            }
            $approvalRoles = Role::query()->where('company_id', $company->id)
                ->whereIn('name', ['company_owner', 'finance_manager', 'general_manager', 'branch_manager'])
                ->get();
            foreach ($approvalRoles as $role) {
                foreach (['employee_commission', 'employee_expense', 'employee_advance'] as $operation) {
                    $limit = TreasuryApprovalLimit::query()->firstOrNew([
                        'company_id' => $company->id,
                        'role_id' => $role->id,
                        'operation_type' => $operation,
                        'currency_id' => $company->currency_id,
                        'branch_id' => null,
                        'valid_from' => '2000-01-01',
                    ]);
                    $limit->forceFill([
                        'company_id' => $company->id, 'role_id' => $role->id,
                        'user_id' => null, 'branch_id' => null,
                        'operation_type' => $operation, 'currency_id' => $company->currency_id,
                        'minimum_amount' => 0, 'maximum_amount' => '999999999.9999',
                        'approval_level' => 1, 'can_create' => true, 'can_submit' => true,
                        'can_approve' => true,
                        'can_post' => in_array($role->name, ['company_owner', 'finance_manager'], true),
                        'valid_from' => '2000-01-01', 'valid_to' => null, 'is_active' => true,
                        'created_by' => $limit->created_by ?: $actor->id,
                    ])->save();
                }
            }
            Role::query()->where('company_id', $company->id)->where('name', 'accountant')->get()
                ->each(function (Role $role) use ($company, $actor): void {
                    foreach (['employee_commission', 'employee_expense', 'employee_advance'] as $operation) {
                        $limit = TreasuryApprovalLimit::query()->firstOrNew([
                            'company_id' => $company->id,
                            'role_id' => $role->id,
                            'operation_type' => $operation,
                            'currency_id' => $company->currency_id,
                            'branch_id' => null,
                            'valid_from' => '2000-01-01',
                        ]);
                        $limit->forceFill([
                            'company_id' => $company->id, 'role_id' => $role->id,
                            'user_id' => null, 'branch_id' => null,
                            'operation_type' => $operation, 'currency_id' => $company->currency_id,
                            'minimum_amount' => 0, 'maximum_amount' => '999999999.9999',
                            'approval_level' => 1, 'can_create' => true, 'can_submit' => true,
                            'can_approve' => false, 'can_post' => true,
                            'valid_from' => '2000-01-01', 'valid_to' => null, 'is_active' => true,
                            'created_by' => $limit->created_by ?: $actor->id,
                        ])->save();
                    }
                });
            unset($expense, $payable, $advance);
        });
    }

    private function account(
        Company $company,
        int $actorId,
        string $code,
        string $name,
        string $parentCode,
        string $groupCode,
        string $normalBalance,
        ?string $controlType = null
    ): Account {
        $parent = Account::query()->where('company_id', $company->id)
            ->where('account_code', $parentCode)->firstOrFail();
        $group = AccountGroup::query()->where('company_id', $company->id)
            ->where('code', $groupCode)->firstOrFail();
        $account = Account::withTrashed()->firstOrNew([
            'company_id' => $company->id, 'account_code' => $code,
        ]);
        $account->forceFill([
            'company_id' => $company->id, 'account_code' => $code,
            'account_type_id' => $group->account_type_id, 'account_group_id' => $group->id,
            'parent_account_id' => $parent->id, 'name_ar' => $name,
            'account_level' => $parent->account_level + 1,
            'normal_balance' => $normalBalance, 'currency_id' => $company->currency_id,
            'is_header' => false, 'is_posting' => true, 'is_control_account' => $controlType !== null,
            'control_type' => $controlType, 'is_system' => true, 'is_active' => true,
            'allow_manual_entry' => $controlType === null,
            'created_by' => $account->created_by ?: $actorId, 'deleted_at' => null,
        ])->save();
        $account->forceFill(['account_path' => $parent->account_path.'/'.$account->id])->saveQuietly();

        return $account;
    }
}
