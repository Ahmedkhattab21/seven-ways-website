<?php

namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\User;
use App\Policies\Concerns\PurchasingPolicyScope;

class GoodsReceiptPolicy
{
    use PurchasingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('goods_receipts.view');
    }

    public function view(User $user, GoodsReceipt $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('goods_receipts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('goods_receipts.create');
    }

    public function update(User $user, GoodsReceipt $model): bool
    {
        return $this->purchasingScoped($user, $model) && $model->status === 'draft' && $user->hasPermission('goods_receipts.update');
    }

    public function inspect(User $user, GoodsReceipt $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('goods_receipts.inspect');
    }

    public function post(User $user, GoodsReceipt $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('goods_receipts.post');
    }

    public function viewCost(User $user, GoodsReceipt $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('goods_receipts.view_cost');
    }
}
