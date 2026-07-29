<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;

class AccountantCashSessionPermissionReconciler
{
    public const REQUIRED = [
        'treasury.cash_sessions.view',
        'treasury.cash_sessions.review',
    ];

    public const FORBIDDEN = [
        'treasury.cash_sessions.open',
        'treasury.cash_sessions.count',
        'treasury.cash_sessions.submit',
        'treasury.cash_sessions.approve',
        'treasury.cash_sessions.close',
        'treasury.cash_sessions.reopen',
        'treasury.cash_sessions.override_custodian',
    ];

    /**
     * Reconcile the canonical accountant role and active company accountant
     * roles. Only the cash-session permission subset is touched.
     *
     * @return array<int, array{role: Role, added: array<int, string>, removed: array<int, string>}
     */
    public function reconcile(): array
    {
        $permissionNames = array_unique([...self::REQUIRED, ...self::FORBIDDEN]);
        $permissions = Permission::query()->whereIn('name', $permissionNames)->get()->keyBy('name');
        $review = Permission::query()->firstOrCreate(
            ['name' => 'treasury.cash_sessions.review'],
            ['display_name' => 'Review cash sessions']
        );
        $permissions->put($review->name, $review);

        $roles = Role::query()
            ->where('name', 'accountant')
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('company_id')
                    ->orWhereHas('users', fn ($users) => $users->where('status', 'active'));
            })
            ->get();

        return $roles->map(function (Role $role) use ($permissions): array {
            $before = $role->permissions()->whereIn('permissions.name', self::REQUIRED)->pluck('permissions.name')->all();
            $forbiddenBefore = $role->permissions()->whereIn('permissions.name', self::FORBIDDEN)->pluck('permissions.name')->all();
            $role->permissions()->syncWithoutDetaching(
                collect(self::REQUIRED)->map(fn (string $name) => $permissions[$name]->id)->all()
            );
            $role->permissions()->detach(
                collect(self::FORBIDDEN)->map(fn (string $name) => $permissions[$name]?->id)->filter()->all()
            );

            return [
                'role' => $role,
                'added' => array_values(array_diff(self::REQUIRED, $before)),
                'removed' => array_values($forbiddenBefore),
            ];
        })->all();
    }
}
