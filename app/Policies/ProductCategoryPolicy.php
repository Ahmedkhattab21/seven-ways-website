<?php

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;
use App\Policies\Concerns\ChecksInventoryScope;

class ProductCategoryPolicy
{
    use ChecksInventoryScope;

    public function manage(User $user, ProductCategory $category): bool
    {
        return $this->company($user, $category) && $user->hasPermission('product_categories.manage');
    }
}
