<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\BranchAccountingSetting;
use App\Models\ProductAccountingMapping;

class PostingAccountResolver
{
    public function __construct(private TreasuryAccountResolver $treasury)
    {
    }

    public function branch(int $companyId, int $branchId, string $column): int
    {
        $id = BranchAccountingSetting::query()->where('company_id', $companyId)
            ->where('branch_id', $branchId)->value($column);
        if (! $id) {
            if ($column === 'customer_advance_account_id') {
                $branchName = (string) Branch::query()
                    ->where('company_id', $companyId)
                    ->whereKey($branchId)
                    ->value('name');
                $branchName = preg_replace('/^فرع\s+/u', '', $branchName) ?: $branchName;

                throw new BusinessRuleException(
                    "لم يتم تحديد حساب دفعات العملاء المقدمة لفرع {$branchName}. يرجى ضبطه من إعدادات المحاسبة."
                );
            }
            throw new BusinessRuleException("Missing branch accounting mapping: {$column}.");
        }

        return (int) $id;
    }

    public function paymentMethod(
        int $companyId,
        int $branchId,
        int $paymentMethodId,
        string $operationType = 'receipt',
        ?int $currencyId = null,
        ?string $amount = null
    ): int {
        $resolved = $this->treasury->resolve(
            $paymentMethodId,
            $branchId,
            $operationType,
            $currencyId ?: (int) \App\Models\Company::query()->findOrFail($companyId)->currency_id,
            $amount
        );

        return (int) ($operationType === 'receipt' && $resolved['clearing_account_id']
            ? $resolved['clearing_account_id'] : $resolved['account_id']);
    }

    public function product(int $companyId, int $productId, string $column, int $branchId, string $fallback): int
    {
        return (int) (ProductAccountingMapping::query()->where('company_id', $companyId)
            ->where('product_id', $productId)->where('is_active', true)->value($column)
            ?: $this->branch($companyId, $branchId, $fallback));
    }
}
