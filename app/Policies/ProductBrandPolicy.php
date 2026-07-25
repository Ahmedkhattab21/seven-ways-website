<?php

namespace App\Policies;

use App\Models\ProductBrand;
use App\Models\User;
use App\Policies\Concerns\ChecksInventoryScope;

class ProductBrandPolicy
{
    use ChecksInventoryScope;

    public function manage(User $user, ProductBrand $brand): bool
    {
        return $this->company($user, $brand) && $user->hasPermission('product_brands.manage');
    }
}
