<?php

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;

class StockTransferPolicy
{
    public function view(User $user, StockTransfer $transfer): bool
    {
        return $this->related($user, $transfer) && $user->hasPermission('stock_transfers.view');
    }

    public function update(User $user, StockTransfer $transfer): bool
    {
        return $transfer->status === 'draft' && $this->source($user, $transfer) && $user->hasPermission('stock_transfers.update');
    }

    public function approve(User $user, StockTransfer $transfer): bool
    {
        if (! $this->related($user, $transfer) || ! $user->hasPermission('stock_transfers.approve')) {
            return false;
        }

        return $transfer->transfer_type === 'internal' || $user->isCompanyAdministrator();
    }

    public function prepare(User $user, StockTransfer $transfer): bool
    {
        return $this->source($user, $transfer) && $user->hasPermission('stock_transfers.prepare');
    }

    public function ship(User $user, StockTransfer $transfer): bool
    {
        return $this->source($user, $transfer) && $user->hasPermission('stock_transfers.ship');
    }

    public function receive(User $user, StockTransfer $transfer): bool
    {
        return $this->destination($user, $transfer) && $user->hasPermission('stock_transfers.receive');
    }

    public function cancel(User $user, StockTransfer $transfer): bool
    {
        return $this->related($user, $transfer) && $user->hasPermission('stock_transfers.cancel');
    }

    public function reverse(User $user, StockTransfer $transfer): bool
    {
        return $user->isCompanyAdministrator() && $this->related($user, $transfer)
            && $user->hasPermission('stock_transfers.reverse');
    }

    private function related(User $user, StockTransfer $transfer): bool
    {
        return (int) $transfer->company_id === (int) $user->company_id
            && ($user->isCompanyAdministrator() || $this->source($user, $transfer) || $this->destination($user, $transfer));
    }

    private function source(User $user, StockTransfer $transfer): bool
    {
        return (int) $transfer->company_id === (int) $user->company_id
            && $user->canAccessBranch($transfer->fromBranch);
    }

    private function destination(User $user, StockTransfer $transfer): bool
    {
        return (int) $transfer->company_id === (int) $user->company_id
            && $user->canAccessBranch($transfer->toBranch);
    }
}
