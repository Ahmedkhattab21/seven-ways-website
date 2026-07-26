<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\AccountingSetting;
use App\Models\AccountType;
use App\Models\BranchAccountingSetting;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

class AccountingFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'accounting.account_types.view', 'accounting.account_types.manage',
            'accounting.account_groups.view', 'accounting.account_groups.create', 'accounting.account_groups.update', 'accounting.account_groups.disable',
            'accounting.accounts.view', 'accounting.accounts.create', 'accounting.accounts.update', 'accounting.accounts.disable',
            'accounting.accounts.move', 'accounting.accounts.view_sensitive',
            'accounting.fiscal_years.view', 'accounting.fiscal_years.create', 'accounting.fiscal_years.update',
            'accounting.fiscal_years.open', 'accounting.fiscal_years.soft_close', 'accounting.fiscal_years.reopen',
            'accounting.periods.view', 'accounting.periods.create', 'accounting.periods.update',
            'accounting.periods.soft_close', 'accounting.periods.reopen', 'accounting.periods.lock',
            'accounting.cost_centers.view', 'accounting.cost_centers.create', 'accounting.cost_centers.update',
            'accounting.cost_centers.disable', 'accounting.cost_centers.move',
            'accounting.settings.view', 'accounting.settings.update',
            'accounting.branch_mappings.view', 'accounting.branch_mappings.update',
            'accounting.posting_profiles.view', 'accounting.posting_profiles.create',
            'accounting.posting_profiles.update', 'accounting.posting_profiles.activate',
            'accounting.posting_profiles.supersede',
            'accounting.opening_balances.view', 'accounting.opening_balances.create',
            'accounting.opening_balances.update', 'accounting.opening_balances.submit',
            'accounting.opening_balances.approve', 'accounting.opening_balances.mark_ready',
            'accounting.opening_balances.view_sensitive',
        ];
        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(['name' => $permission], ['display_name' => $permission]);
        }
        $ids = Permission::query()->whereIn('name', $permissions)->pluck('id');
        Role::query()->whereIn('name', ['system_admin', 'company_owner'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
        $views = Permission::query()->whereIn('name', array_values(array_filter(
            $permissions, fn ($name) => str_ends_with($name, '.view') || str_contains($name, '.view_')
        )))->pluck('id');
        Role::query()->whereIn('name', ['general_manager', 'branch_manager'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($views));
        $accountantDenied = ['accounting.account_types.manage'];
        Role::query()->where('name', 'accountant')->get()->each(
            fn (Role $role) => $role->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('name', array_diff($permissions, $accountantDenied))->pluck('id')
            )
        );

        $types = [
            'ASSET' => ['الأصول', 'Assets', 'asset', 'debit', 'balance_sheet', 'none', 10],
            'LIABILITY' => ['الالتزامات', 'Liabilities', 'liability', 'credit', 'balance_sheet', 'none', 20],
            'EQUITY' => ['حقوق الملكية', 'Equity', 'equity', 'credit', 'balance_sheet', 'none', 30],
            'REVENUE' => ['الإيرادات', 'Revenue', 'revenue', 'credit', 'income_statement', 'operating', 40],
            'EXPENSE' => ['المصروفات', 'Expenses', 'expense', 'debit', 'income_statement', 'operating', 50],
        ];
        foreach ($types as $code => [$ar, $en, $class, $balance, $statement, $cashFlow, $sort]) {
            AccountType::query()->updateOrCreate(['company_id' => null, 'code' => $code], [
                'name_ar' => $ar, 'name_en' => $en, 'classification' => $class,
                'normal_balance' => $balance, 'statement_type' => $statement,
                'cash_flow_category' => $cashFlow, 'sort_order' => $sort, 'is_system' => true, 'is_active' => true,
            ]);
        }

        Company::query()->with(['branches', 'users'])->get()->each(fn (Company $company) => $this->seedCompany($company));
    }

    private function seedCompany(Company $company): void
    {
        $actor = $company->users->sortBy('id')->first();
        if (! $actor || ! $company->currency_id) {
            return;
        }
        $types = AccountType::query()->whereNull('company_id')->get()->keyBy('code');
        $groupRows = [
            ['100', 'ASSET', 'الأصول', null], ['110', 'ASSET', 'الأصول المتداولة', '100'],
            ['111', 'ASSET', 'النقدية والبنوك', '110'], ['113', 'ASSET', 'الذمم المدينة', '110'],
            ['114', 'ASSET', 'المخزون', '110'], ['115', 'ASSET', 'ضريبة القيمة المضافة', '110'],
            ['120', 'ASSET', 'الأصول غير المتداولة', '100'],
            ['200', 'LIABILITY', 'الالتزامات', null], ['210', 'LIABILITY', 'الالتزامات المتداولة', '200'],
            ['211', 'LIABILITY', 'الذمم الدائنة', '210'], ['212', 'LIABILITY', 'ضريبة القيمة المضافة', '210'],
            ['300', 'EQUITY', 'حقوق الملكية', null],
            ['400', 'REVENUE', 'الإيرادات', null], ['410', 'REVENUE', 'إيرادات الخدمات', '400'],
            ['420', 'REVENUE', 'إيرادات المنتجات', '400'],
            ['500', 'EXPENSE', 'تكلفة المبيعات', null], ['600', 'EXPENSE', 'مصروفات التشغيل', null],
        ];
        $groups = collect();
        foreach ($groupRows as [$code, $typeCode, $name, $parentCode]) {
            $parent = $parentCode ? $groups->get($parentCode) : null;
            $group = AccountGroup::withTrashed()->firstOrNew(['company_id' => $company->id, 'code' => $code]);
            $group->forceFill([
                'company_id' => $company->id, 'code' => $code,
                'account_type_id' => $types[$typeCode]->id, 'parent_group_id' => $parent?->id,
                'name_ar' => $name, 'level' => $parent ? $parent->level + 1 : 0,
                'path' => $parent ? $parent->path.'/pending' : null, 'is_system' => true,
                'is_active' => true, 'created_by' => $actor->id, 'deleted_at' => null,
            ])->save();
            $group->forceFill(['path' => $parent ? $parent->path.'/'.$group->id : (string) $group->id])->saveQuietly();
            $groups->put($code, $group);
        }

        $accountRows = [
            ['100000', 'ASSET', '100', 'الأصول', true, null],
            ['110000', 'ASSET', '110', 'الأصول المتداولة', true, '100000'],
            ['111000', 'ASSET', '111', 'النقدية', false, '110000'],
            ['112000', 'ASSET', '111', 'البنوك', false, '110000'],
            ['113000', 'ASSET', '113', 'العملاء', false, '110000'],
            ['114000', 'ASSET', '114', 'المخزون', false, '110000'],
            ['115000', 'ASSET', '115', 'ضريبة مدخلات', false, '110000'],
            ['200000', 'LIABILITY', '200', 'الالتزامات', true, null],
            ['210000', 'LIABILITY', '210', 'الالتزامات المتداولة', true, '200000'],
            ['211000', 'LIABILITY', '211', 'الموردون', false, '210000'],
            ['212000', 'LIABILITY', '212', 'ضريبة مخرجات', false, '210000'],
            ['213000', 'LIABILITY', '210', 'دفعات مقدمة من العملاء', false, '210000'],
            ['300000', 'EQUITY', '300', 'حقوق الملكية', true, null],
            ['310000', 'EQUITY', '300', 'رأس المال', false, '300000'],
            ['320000', 'EQUITY', '300', 'الأرباح المحتجزة', false, '300000'],
            ['400000', 'REVENUE', '400', 'الإيرادات', true, null],
            ['410000', 'REVENUE', '410', 'إيرادات الخدمات', false, '400000'],
            ['420000', 'REVENUE', '420', 'إيرادات المنتجات', false, '400000'],
            ['430000', 'REVENUE', '400', 'خصومات ومردودات المبيعات', false, '400000'],
            ['500000', 'EXPENSE', '500', 'تكلفة المبيعات', true, null],
            ['510000', 'EXPENSE', '500', 'تكلفة الخدمات', false, '500000'],
            ['520000', 'EXPENSE', '500', 'تكلفة المنتجات', false, '500000'],
            ['600000', 'EXPENSE', '600', 'المصروفات التشغيلية', true, null],
            ['610000', 'EXPENSE', '600', 'الرواتب', false, '600000'],
            ['620000', 'EXPENSE', '600', 'الإيجارات', false, '600000'],
            ['630000', 'EXPENSE', '600', 'التسويق', false, '600000'],
            ['640000', 'EXPENSE', '600', 'المصروفات الإدارية', false, '600000'],
            ['650000', 'EXPENSE', '600', 'فروق وتقريب', false, '600000'],
        ];
        $accounts = collect();
        foreach ($accountRows as [$code, $typeCode, $groupCode, $name, $header, $parentCode]) {
            $parent = $parentCode ? $accounts->get($parentCode) : null;
            $control = match ($code) {
                '113000' => 'accounts_receivable', '114000' => 'inventory', '115000' => 'vat_input',
                '211000' => 'accounts_payable', '212000' => 'vat_output', '213000' => 'customer_advances',
                default => null,
            };
            $account = Account::withTrashed()->firstOrNew(['company_id' => $company->id, 'account_code' => $code]);
            $account->forceFill([
                'company_id' => $company->id, 'account_code' => $code,
                'account_type_id' => $types[$typeCode]->id, 'account_group_id' => $groups[$groupCode]->id,
                'parent_account_id' => $parent?->id, 'name_ar' => $name,
                'account_level' => $parent ? $parent->account_level + 1 : 0,
                'account_path' => null, 'is_header' => $header, 'is_posting' => ! $header,
                'normal_balance' => $types[$typeCode]->normal_balance, 'currency_id' => $company->currency_id,
                'allows_multi_currency' => false, 'is_control_account' => $control !== null,
                'control_type' => $control, 'is_cash_account' => $code === '111000',
                'is_bank_account' => $code === '112000', 'is_inventory_account' => $code === '114000',
                'is_tax_account' => in_array($code, ['115000', '212000'], true),
                'is_system' => true, 'is_active' => true, 'allow_manual_entry' => ! $header && $control === null,
                'created_by' => $actor->id, 'deleted_at' => null,
            ])->save();
            $account->forceFill(['account_path' => $parent ? $parent->account_path.'/'.$account->id : (string) $account->id])->saveQuietly();
            $accounts->put($code, $account);
        }

        $root = CostCenter::withTrashed()->firstOrNew(['company_id' => $company->id, 'code' => 'COMPANY']);
        $root->forceFill([
            'company_id' => $company->id, 'code' => 'COMPANY',
            'name_ar' => 'مركز الشركة الرئيسي', 'cost_center_type' => 'company',
            'level' => 0, 'is_header' => true, 'is_posting' => false, 'is_system' => true,
            'is_active' => true, 'created_by' => $actor->id, 'deleted_at' => null,
        ])->save();
        $root->forceFill(['path' => (string) $root->id])->saveQuietly();
        foreach ($company->branches as $branch) {
            $center = CostCenter::withTrashed()->firstOrNew(['company_id' => $company->id, 'code' => 'BR-'.$branch->code]);
            $center->forceFill([
                'company_id' => $company->id, 'code' => 'BR-'.$branch->code,
                'branch_id' => $branch->id, 'parent_cost_center_id' => $root->id,
                'name_ar' => 'فرع '.$branch->name, 'level' => 1, 'cost_center_type' => 'branch',
                'is_header' => false, 'is_posting' => true, 'is_system' => true,
                'is_active' => true, 'created_by' => $actor->id, 'deleted_at' => null,
            ])->save();
            $center->forceFill(['path' => $root->path.'/'.$center->id])->saveQuietly();
            $mapping = BranchAccountingSetting::query()->firstOrNew(['branch_id' => $branch->id]);
            $mapping->forceFill([
                'branch_id' => $branch->id,
                'company_id' => $company->id, 'default_cost_center_id' => $center->id,
                'cash_account_id' => $accounts['111000']->id, 'bank_account_id' => $accounts['112000']->id,
                'accounts_receivable_account_id' => $accounts['113000']->id,
                'accounts_payable_account_id' => $accounts['211000']->id,
                'service_revenue_account_id' => $accounts['410000']->id,
                'product_revenue_account_id' => $accounts['420000']->id,
                'sales_discount_account_id' => $accounts['430000']->id,
                'sales_return_account_id' => $accounts['430000']->id,
                'inventory_account_id' => $accounts['114000']->id,
                'cost_of_goods_sold_account_id' => $accounts['500000']->id,
                'vat_input_account_id' => $accounts['115000']->id,
                'vat_output_account_id' => $accounts['212000']->id,
                'customer_advance_account_id' => $accounts['213000']->id,
                'rounding_account_id' => $accounts['650000']->id,
            ])->save();
            $this->sequence($company->id, $branch->id, 'opening_balance', '{BRANCH}-OB-{YYYY}-');
        }
        $this->sequence($company->id, null, 'opening_balance', 'ALL-OB-{YYYY}-');
        $this->sequence($company->id, null, 'posting_profile', 'ACC-PP-');
        $settings = AccountingSetting::query()->firstOrNew(['company_id' => $company->id]);
        $settings->forceFill([
            'company_id' => $company->id,
            'base_currency_id' => $company->currency_id, 'default_rounding_precision' => 4,
            'allow_manual_journals' => false, 'require_journal_approval' => true,
            'enforce_balanced_dimensions' => true, 'enforce_branch_on_posting' => true,
            'separation_of_duties' => true, 'auto_post_sales' => false, 'auto_post_purchases' => false,
            'auto_post_inventory' => false, 'auto_post_payments' => false,
        ])->save();
    }

    private function sequence(int $companyId, ?int $branchId, string $type, string $prefix): void
    {
        $sequence = DocumentSequence::query()->firstOrNew([
            'scope_key' => DocumentNumberService::scopeKey($companyId, $branchId, $type, null),
        ]);
        $sequence->forceFill([
            'company_id' => $companyId, 'branch_id' => $branchId, 'document_type' => $type,
            'prefix' => $prefix, 'current_number' => 0, 'padding' => 6,
            'reset_period' => $type === 'opening_balance' ? 'yearly' : 'never',
            'period_key' => null, 'is_active' => true,
        ])->save();
    }
}
