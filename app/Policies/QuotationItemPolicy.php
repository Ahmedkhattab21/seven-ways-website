<?php

namespace App\Policies;

use App\Models\QuotationItem;
use App\Models\User;

class QuotationItemPolicy
{
    public function update(User $user, QuotationItem $item): bool
    {
        return $user->can('update', $item->quotation);
    }
}
