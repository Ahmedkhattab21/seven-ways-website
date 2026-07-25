<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Customer;
use App\Models\User;

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
        $prefix = $attachment->attachable instanceof Customer ? 'customers' : 'vehicles';
        $branch = $attachment->attachable instanceof Customer
            ? $attachment->attachable->assignedBranch
            : $attachment->attachable->customer?->assignedBranch;

        return $user->hasPermission("{$prefix}.{$action}")
            && ($user->isCompanyAdministrator() || ($branch && $user->canAccessBranch($branch)));
    }
}
