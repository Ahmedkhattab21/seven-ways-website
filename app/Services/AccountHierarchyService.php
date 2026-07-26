<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

class AccountHierarchyService
{
    public function move(Account $account, ?Account $parent): Account
    {
        return DB::transaction(function () use ($account, $parent) {
            $account = Account::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $parent = $parent ? Account::query()->whereKey($parent->id)->lockForUpdate()->firstOrFail() : null;
            $this->assertParent($account, $parent);
            $account->forceFill(['parent_account_id' => $parent?->id])->save();
            $this->refreshPath($account);

            return $account->fresh();
        });
    }

    public function assertParent(Account $account, ?Account $parent): void
    {
        if (! $parent) {
            return;
        }
        if ($parent->company_id !== $account->company_id || $parent->account_type_id !== $account->account_type_id) {
            throw new BusinessRuleException('Parent account must belong to the same company and account type.');
        }
        if (! $parent->is_header || $parent->is_posting) {
            throw new BusinessRuleException('Children can only be created under header accounts.');
        }
        if ($account->exists && ($parent->id === $account->id || $this->isDescendant($parent, $account))) {
            throw new BusinessRuleException('Account hierarchy cycle is not allowed.');
        }
    }

    public function refreshPath(Account $account): void
    {
        $account->refresh();
        $level = $account->parent ? $account->parent->account_level + 1 : 0;
        $path = $account->parent ? $account->parent->account_path.'/'.$account->id : (string) $account->id;
        $account->forceFill(['account_level' => $level, 'account_path' => $path])->saveQuietly();
        foreach ($account->children()->get() as $child) {
            $this->refreshPath($child);
        }
    }

    private function isDescendant(Account $candidate, Account $ancestor): bool
    {
        return $candidate->account_path !== null
            && ($candidate->account_path === (string) $ancestor->id
                || str_starts_with($candidate->account_path, $ancestor->account_path.'/'));
    }
}
