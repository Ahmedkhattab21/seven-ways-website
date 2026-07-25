<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Customer;
use App\Models\QualityCheck;
use App\Models\ReworkOrder;
use App\Models\User;
use App\Models\VehicleInspection;
use App\Models\WarrantyClaim;

class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        return $this->allowed($user, $attachment, 'view');
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $this->allowed($user, $attachment, 'manage_attachments');
    }

    private function allowed(User $user, Attachment $attachment, string $action): bool
    {
        if ((int) $attachment->company_id !== (int) $user->company_id) {
            return false;
        }
        if ($attachment->attachable instanceof VehicleInspection) {
            $permission = $action === 'view'
                ? 'vehicle_inspections.view'
                : 'vehicle_inspections.manage_photos';

            return ($action === 'view' || $attachment->attachable->status === 'draft')
                && $user->hasPermission($permission)
                && $user->canAccessBranch($attachment->attachable->workOrder->branch);
        }
        if ($attachment->attachable instanceof QualityCheck) {
            return ($action === 'view' || in_array($attachment->attachable->status, ['draft', 'in_progress'], true))
                && $user->hasPermission($action === 'view' ? 'quality_checks.view' : 'quality_checks.perform')
                && $user->canAccessBranch($attachment->attachable->workOrder->branch);
        }
        if ($attachment->attachable instanceof ReworkOrder) {
            return ($action === 'view' || $attachment->attachable->status !== 'completed')
                && $user->hasPermission($action === 'view' ? 'rework_orders.view' : 'rework_orders.complete')
                && $user->canAccessBranch($attachment->attachable->workOrder->branch);
        }
        if ($attachment->attachable instanceof WarrantyClaim) {
            return ($action === 'view' || ! in_array($attachment->attachable->status, ['resolved', 'rejected', 'cancelled'], true))
                && $user->hasPermission($action === 'view' ? 'warranty_claims.view' : 'warranty_claims.inspect')
                && $user->canAccessBranch($attachment->attachable->warranty->branch);
        }
        $prefix = $attachment->attachable instanceof Customer ? 'customers' : 'vehicles';
        $branch = $attachment->attachable instanceof Customer
            ? $attachment->attachable->assignedBranch
            : $attachment->attachable->customer?->assignedBranch;

        return $user->hasPermission("{$prefix}.{$action}")
            && ($user->isCompanyAdministrator() || ($branch && $user->canAccessBranch($branch)));
    }
}
