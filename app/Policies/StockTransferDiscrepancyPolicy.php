<?php

namespace App\Policies;

use App\Models\StockTransferDiscrepancy;
use App\Models\User;

class StockTransferDiscrepancyPolicy
{
    public function view(User $user, StockTransferDiscrepancy $discrepancy): bool
    {
        return (new StockTransferPolicy)->view($user, $discrepancy->transfer);
    }

    public function resolve(User $user, StockTransferDiscrepancy $discrepancy): bool
    {
        return (new StockTransferPolicy)->view($user, $discrepancy->transfer)
            && $user->hasPermission('stock_transfers.resolve_discrepancy');
    }
}
