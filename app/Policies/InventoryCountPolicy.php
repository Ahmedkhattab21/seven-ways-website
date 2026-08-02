<?php

namespace App\Policies;

use App\Models\InventoryCount;
use App\Models\User;
use App\Policies\Concerns\ChecksInventoryScope;
use Illuminate\Auth\Access\Response;

class InventoryCountPolicy
{
    use ChecksInventoryScope;

    public function view(User $user, InventoryCount $count): bool
    {
        return $this->branch($user, $count) && $user->hasPermission('inventory.view');
    }

    public function post(User $user, InventoryCount $count): bool
    {
        return $this->branch($user, $count)
            && $user->hasPermission('inventory.post')
            && $count->status === 'counting'
            && $count->counted_at !== null
            && (int) $count->warehouse?->company_id === (int) $count->company_id
            && (int) $count->warehouse?->branch_id === (int) $count->branch_id;
    }

    public function snapshot(User $user, InventoryCount $count): Response
    {
        if (! $this->canCount($user, $count)) {
            return Response::deny('لا تملك صلاحية بدء هذا الجرد.');
        }

        if ($count->status !== 'draft') {
            return Response::deny('لا يمكن بدء جرد غير موجود في حالة المسودة.');
        }

        return Response::allow();
    }

    public function count(User $user, InventoryCount $count): bool
    {
        return $this->canCount($user, $count)
            && $count->status === 'counting'
            && $count->counted_at === null;
    }

    private function canCount(User $user, InventoryCount $count): bool
    {
        return (int) $count->company_id === (int) $user->company_id
            && $user->canAccessBranch($count->branch)
            && $user->hasPermission('inventory.count')
            && (int) $count->warehouse?->company_id === (int) $count->company_id
            && (int) $count->warehouse?->branch_id === (int) $count->branch_id;
    }
}
