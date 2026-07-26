<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\BranchAccountingSetting;
use App\Models\PaymentMethodAccountMapping;
use App\Models\ProductAccountingMapping;

class PostingAccountResolver
{
    public function branch(int $companyId, int $branchId, string $column): int
    {
        $id = BranchAccountingSetting::query()->where('company_id', $companyId)
            ->where('branch_id', $branchId)->value($column);
        if (! $id) {
            throw new BusinessRuleException("Missing branch accounting mapping: {$column}.");
        }

        return (int) $id;
    }

    public function paymentMethod(int $companyId, int $branchId, int $paymentMethodId): int
    {
        $id = PaymentMethodAccountMapping::query()->where('company_id', $companyId)
            ->where('branch_id', $branchId)->where('payment_method_id', $paymentMethodId)
            ->where('is_active', true)->value('account_id');
        if (! $id) {
            throw new BusinessRuleException('Payment method has no active accounting mapping.');
        }

        return (int) $id;
    }

    public function product(int $companyId, int $productId, string $column, int $branchId, string $fallback): int
    {
        return (int) (ProductAccountingMapping::query()->where('company_id', $companyId)
            ->where('product_id', $productId)->where('is_active', true)->value($column)
            ?: $this->branch($companyId, $branchId, $fallback));
    }
}
