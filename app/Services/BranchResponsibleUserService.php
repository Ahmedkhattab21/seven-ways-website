<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchResponsibleUserService
{
    public function __construct(private AuditService $audit)
    {
    }

    public function assign(Branch $branch, User $user): Branch
    {
        return DB::transaction(function () use ($branch, $user) {
            $lockedBranch = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->company_id !== $lockedBranch->company_id || ! $lockedUser->isActive()) {
                throw ValidationException::withMessages([
                    'responsible_user_id' => 'مسؤول الفرع يجب أن يكون حسابًا نشطًا داخل نفس الشركة.',
                ]);
            }

            if (! $lockedUser->hasRole('branch_manager')) {
                throw ValidationException::withMessages([
                    'responsible_user_id' => 'المستخدم المحدد لا يملك دور مسؤول الفرع.',
                ]);
            }

            $otherBranch = Branch::query()
                ->where('responsible_user_id', $lockedUser->id)
                ->whereKeyNot($lockedBranch->id)
                ->lockForUpdate()
                ->first();

            if ($otherBranch) {
                throw ValidationException::withMessages([
                    'responsible_user_id' => 'هذا المستخدم مسؤول بالفعل عن فرع آخر.',
                ]);
            }

            $before = $lockedBranch->responsible_user_id;
            if ($before && $before !== $lockedUser->id) {
                $previousUser = User::query()->whereKey($before)->lockForUpdate()->first();
                if ($previousUser) {
                    if ($previousUser->branch_id === $lockedBranch->id) {
                        $previousUser->forceFill(['branch_id' => null])->save();
                    }
                    $previousUser->accessibleBranches()->detach($lockedBranch->id);
                }
            }

            $lockedBranch->forceFill([
                'responsible_user_id' => $lockedUser->id,
                'responsible_assigned_at' => now(),
            ])->save();

            $lockedUser->forceFill(['branch_id' => $lockedBranch->id])->save();
            $lockedUser->accessibleBranches()->sync([
                $lockedBranch->id => [
                    'is_default' => true,
                    'can_view' => true,
                    'can_create' => true,
                    'can_update' => true,
                    'can_approve' => false,
                ],
            ]);

            $this->audit->record('branch.responsible_user_assigned', $lockedBranch, [
                'before_user_id' => $before,
                'after_user_id' => $lockedUser->id,
            ]);

            return $lockedBranch->refresh();
        });
    }
}
