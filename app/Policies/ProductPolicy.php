<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\ChecksInventoryScope;

class ProductPolicy
{
    use ChecksInventoryScope;

    public function view(User $user, Product $product): bool
    {
        return $this->company($user, $product) && $user->hasPermission('products.view');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->company($user, $product) && $user->hasPermission('products.update');
    }

    public function disable(User $user, Product $product): bool
    {
        return $this->company($user, $product) && $user->hasPermission('products.disable');
    }
}
