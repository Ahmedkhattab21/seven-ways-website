<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\AccountGroup;
use App\Models\AccountType;
use Illuminate\Support\Facades\DB;

class AccountGroupService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function save(AccountGroup $group, array $data): AccountGroup
    {
        return DB::transaction(function () use ($group, $data) {
            $companyId = $this->tenant->companyId();
            $type = AccountType::query()->whereKey($data['account_type_id'])
                ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
                ->where('is_active', true)->firstOrFail();
            $parent = empty($data['parent_group_id']) ? null : AccountGroup::query()
                ->whereKey($data['parent_group_id'])->where('company_id', $companyId)->lockForUpdate()->firstOrFail();
            $this->assertParent($group, $parent, $type->id);
            if ($group->exists && $group->is_system && ($group->account_type_id !== $type->id || $group->code !== $data['code'])) {
                throw new BusinessRuleException('System group classification and code are protected.');
            }
            $group->forceFill($data + [
                'company_id' => $companyId, 'account_type_id' => $type->id,
                'created_by' => $group->created_by ?: $this->tenant->user()->id,
                'updated_by' => $group->exists ? $this->tenant->user()->id : null,
            ])->save();
            $this->refreshPath($group);
            $this->audit->record($group->wasRecentlyCreated ? 'account_group.created' : 'account_group.updated', $group);

            return $group->fresh();
        });
    }

    public function disable(AccountGroup $group): void
    {
        if ($group->company_id !== $this->tenant->companyId() || $group->is_system) {
            throw new BusinessRuleException('System or cross-company group cannot be disabled.');
        }
        if ($group->children()->where('is_active', true)->exists() || $group->accounts()->where('is_active', true)->exists()) {
            throw new BusinessRuleException('Disable active children and accounts first.');
        }
        $group->forceFill(['is_active' => false, 'updated_by' => $this->tenant->user()->id])->save();
        $this->audit->record('account_group.disabled', $group);
    }

    private function assertParent(AccountGroup $group, ?AccountGroup $parent, int $typeId): void
    {
        if (! $parent) {
            return;
        }
        if ($parent->account_type_id !== $typeId) {
            throw new BusinessRuleException('Parent group must use the same account type.');
        }
        if ($group->exists && ($parent->id === $group->id || str_starts_with((string) $parent->path, $group->path.'/'))) {
            throw new BusinessRuleException('Account group cycle is not allowed.');
        }
    }

    private function refreshPath(AccountGroup $group): void
    {
        $group->refresh();
        $group->forceFill([
            'level' => $group->parent ? $group->parent->level + 1 : 0,
            'path' => $group->parent ? $group->parent->path.'/'.$group->id : (string) $group->id,
        ])->saveQuietly();
        foreach ($group->children()->get() as $child) {
            $this->refreshPath($child);
        }
    }
}
