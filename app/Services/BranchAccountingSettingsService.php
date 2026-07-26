<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\BranchAccountingMappingsUpdated;
use App\Models\Account;
use App\Models\Branch;
use App\Models\BranchAccountingSetting;
use App\Models\CostCenter;
use Illuminate\Support\Facades\DB;

class BranchAccountingSettingsService
{
    public const ACCOUNT_COLUMNS = [
        'cash_account_id', 'bank_account_id', 'accounts_receivable_account_id',
        'accounts_payable_account_id', 'sales_revenue_account_id', 'service_revenue_account_id',
        'product_revenue_account_id', 'sales_discount_account_id', 'sales_return_account_id',
        'inventory_account_id', 'cost_of_goods_sold_account_id', 'inventory_adjustment_account_id',
        'purchase_account_id', 'purchase_return_account_id', 'vat_input_account_id',
        'vat_output_account_id', 'customer_advance_account_id', 'supplier_advance_account_id',
        'rounding_account_id',
    ];

    private const CONTROL_TYPES = [
        'accounts_receivable_account_id' => 'accounts_receivable',
        'accounts_payable_account_id' => 'accounts_payable',
        'inventory_account_id' => 'inventory',
        'vat_input_account_id' => 'vat_input',
        'vat_output_account_id' => 'vat_output',
        'customer_advance_account_id' => 'customer_advances',
        'supplier_advance_account_id' => 'supplier_advances',
    ];

    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function update(Branch $branch, array $data): BranchAccountingSetting
    {
        if ($branch->company_id !== $this->tenant->companyId() || ! $this->tenant->user()->canAccessBranch($branch)) {
            throw new BusinessRuleException('Branch is outside the accessible scope.');
        }
        if (! empty($data['default_cost_center_id'])) {
            $center = CostCenter::query()->whereKey($data['default_cost_center_id'])
                ->where('company_id', $branch->company_id)->where('is_active', true)->where('is_posting', true)->firstOrFail();
            if ($center->branch_id && $center->branch_id !== $branch->id) {
                throw new BusinessRuleException('Default cost center belongs to another branch.');
            }
        }
        foreach (self::ACCOUNT_COLUMNS as $column) {
            if (empty($data[$column])) {
                continue;
            }
            $account = Account::query()->whereKey($data[$column])->where('company_id', $branch->company_id)
                ->where('is_active', true)->where('is_posting', true)->firstOrFail();
            if (isset(self::CONTROL_TYPES[$column]) && $account->control_type !== self::CONTROL_TYPES[$column]) {
                throw new BusinessRuleException("Invalid control account for {$column}.");
            }
            if ($column === 'cash_account_id' && ! $account->is_cash_account) {
                throw new BusinessRuleException('Cash mapping requires a cash account.');
            }
            if ($column === 'bank_account_id' && ! $account->is_bank_account) {
                throw new BusinessRuleException('Bank mapping requires a bank account.');
            }
        }

        return DB::transaction(function () use ($branch, $data) {
            $settings = BranchAccountingSetting::query()->firstOrNew(['branch_id' => $branch->id]);
            $settings->forceFill(
                array_intersect_key($data, array_flip(array_merge(['default_cost_center_id'], self::ACCOUNT_COLUMNS)))
                + ['company_id' => $branch->company_id, 'branch_id' => $branch->id]
            )->save();
            $this->audit->record('branch_accounting_mappings.updated', $settings);
            DB::afterCommit(fn () => event(new BranchAccountingMappingsUpdated($settings->id)));

            return $settings;
        });
    }
}
