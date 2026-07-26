<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\BranchAccountingSetting;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodAccountMapping;
use App\Models\Permission;
use App\Models\PostingProfile;
use App\Models\Product;
use App\Models\ProductAccountingMapping;
use App\Models\Role;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

class AccountingPostingSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'accounting.journals.view', 'accounting.journals.create', 'accounting.journals.update',
            'accounting.journals.submit', 'accounting.journals.approve', 'accounting.journals.post',
            'accounting.journals.reverse', 'accounting.journals.cancel', 'accounting.journals.view_sensitive',
            'accounting.journals.post_control_accounts', 'accounting.posting.preview',
            'accounting.posting.execute', 'accounting.posting.retry', 'accounting.posting.reverse',
            'accounting.posting.override_profile', 'accounting.posting.view_failures',
            'accounting.periods.post_soft_closed', 'accounting.opening_balances.post',
            'accounting.opening_balances.reverse', 'accounting.mappings.payment_methods',
            'accounting.mappings.products',
        ];
        foreach ($permissions as $name) {
            Permission::query()->updateOrCreate(['name' => $name], ['display_name' => $name]);
        }
        $all = Permission::query()->whereIn('name', $permissions)->pluck('id');
        Role::query()->whereIn('name', ['system_admin', 'company_owner', 'accountant'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($all));
        $views = Permission::query()->whereIn('name', [
            'accounting.journals.view', 'accounting.posting.preview',
        ])->pluck('id');
        Role::query()->whereIn('name', ['general_manager', 'branch_manager'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($views));

        Company::query()->with(['branches', 'users'])->get()->each(fn (Company $company) => $this->seedCompany($company));
    }

    private function seedCompany(Company $company): void
    {
        $actor = $company->users->sortBy('id')->first();
        if (! $actor || ! $company->currency_id) {
            return;
        }
        $assetGroup = AccountGroup::query()->where('company_id', $company->id)->where('code', '110')->first();
        $liabilityGroup = AccountGroup::query()->where('company_id', $company->id)->where('code', '210')->first();
        $expenseGroup = AccountGroup::query()->where('company_id', $company->id)->where('code', '600')->first();
        $assetsParent = Account::query()->where('company_id', $company->id)->where('account_code', '110000')->first();
        $liabilitiesParent = Account::query()->where('company_id', $company->id)->where('account_code', '210000')->first();
        $expensesParent = Account::query()->where('company_id', $company->id)->where('account_code', '600000')->first();
        if (! $assetGroup || ! $liabilityGroup || ! $expenseGroup || ! $assetsParent || ! $liabilitiesParent || ! $expensesParent) {
            return;
        }
        $grni = $this->account($company->id, $actor->id, '214000', 'Goods received not invoiced', $liabilityGroup, $liabilitiesParent);
        $returns = $this->account($company->id, $actor->id, '116000', 'Purchase return clearing', $assetGroup, $assetsParent);
        $supplierAdvance = $this->account($company->id, $actor->id, '117000', 'Supplier advances', $assetGroup, $assetsParent, 'supplier_advances');
        $adjustment = $this->account($company->id, $actor->id, '660000', 'Inventory adjustments', $expenseGroup, $expensesParent);

        foreach ($company->branches as $branch) {
            $mapping = BranchAccountingSetting::query()->where('branch_id', $branch->id)->first();
            if (! $mapping) {
                continue;
            }
            $mapping->forceFill([
                'purchase_account_id' => $grni->id, 'purchase_return_account_id' => $returns->id,
                'supplier_advance_account_id' => $supplierAdvance->id,
                'inventory_adjustment_account_id' => $adjustment->id,
            ])->save();
            $this->sequence($company->id, $branch->id, 'journal_entry', '{BRANCH}-JE-{YYYY}-');
            PaymentMethod::query()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $company->id))
                ->where('is_active', true)->get()->each(function (PaymentMethod $method) use ($mapping, $branch, $company, $actor) {
                    PaymentMethodAccountMapping::query()->updateOrCreate(
                        ['branch_id' => $branch->id, 'payment_method_id' => $method->id],
                        [
                            'company_id' => $company->id,
                            'account_id' => $method->is_cash ? $mapping->cash_account_id : $mapping->bank_account_id,
                            'is_active' => true, 'created_by' => $actor->id,
                        ]
                    );
                });
        }
        $this->sequence($company->id, null, 'journal_entry', 'ALL-JE-{YYYY}-');

        $default = BranchAccountingSetting::query()->where('company_id', $company->id)->first();
        if ($default) {
            Product::query()->where('company_id', $company->id)->get()->each(
                fn (Product $product) => ProductAccountingMapping::query()->updateOrCreate(
                    ['company_id' => $company->id, 'product_id' => $product->id],
                    [
                        'inventory_account_id' => $default->inventory_account_id,
                        'revenue_account_id' => $default->product_revenue_account_id,
                        'cogs_account_id' => $default->cost_of_goods_sold_account_id,
                        'purchase_return_account_id' => $default->purchase_return_account_id,
                        'adjustment_account_id' => $default->inventory_adjustment_account_id,
                        'is_active' => true, 'created_by' => $actor->id,
                    ]
                )
            );
        }
        foreach ([
            \App\Models\SalesInvoice::class, \App\Models\SalesCreditNote::class,
            \App\Models\CustomerPayment::class, \App\Models\CustomerRefund::class,
            \App\Models\SupplierInvoice::class, \App\Models\SupplierCreditNote::class,
            \App\Models\SupplierPayment::class, \App\Models\GoodsReceipt::class,
            \App\Models\PurchaseReturn::class, \App\Models\StockMovement::class,
            \App\Models\StockTransfer::class, \App\Models\OpeningBalanceDocument::class,
        ] as $sourceType) {
            $code = 'AUTO-'.strtoupper(substr(hash('sha1', $sourceType), 0, 10));
            $profile = PostingProfile::withTrashed()->firstOrNew([
                'company_id' => $company->id, 'code' => $code, 'version' => 1,
            ]);
            $profile->forceFill([
                'company_id' => $company->id, 'code' => $code,
                'name' => class_basename($sourceType).' system builder', 'source_type' => $sourceType,
                'description' => 'System builder policy seeded by Phase 14B.',
                'status' => 'active', 'is_default' => true, 'created_by' => $actor->id,
                'approved_by' => $actor->id, 'approved_at' => now(), 'deleted_at' => null,
            ])->save();
        }
    }

    private function account(int $companyId, int $actorId, string $code, string $name, AccountGroup $group, Account $parent, ?string $controlType = null): Account
    {
        $account = Account::withTrashed()->firstOrNew(['company_id' => $companyId, 'account_code' => $code]);
        $account->forceFill([
            'company_id' => $companyId, 'account_code' => $code,
            'account_type_id' => $group->account_type_id, 'account_group_id' => $group->id,
            'parent_account_id' => $parent->id, 'name_ar' => $name,
            'account_level' => $parent->account_level + 1, 'normal_balance' => $parent->normal_balance,
            'currency_id' => $parent->currency_id, 'is_header' => false, 'is_posting' => true,
            'is_control_account' => $controlType !== null, 'control_type' => $controlType,
            'is_system' => true, 'is_active' => true, 'allow_manual_entry' => $controlType === null,
            'created_by' => $actorId, 'deleted_at' => null,
        ])->save();
        $account->forceFill(['account_path' => $parent->account_path.'/'.$account->id])->saveQuietly();

        return $account;
    }

    private function sequence(int $companyId, ?int $branchId, string $type, string $prefix): void
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
