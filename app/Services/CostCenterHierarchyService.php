<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\CostCenter;
use Illuminate\Support\Facades\DB;

class CostCenterHierarchyService
{
    public function move(CostCenter $center, ?CostCenter $parent): CostCenter
    {
        return DB::transaction(function () use ($center, $parent) {
            $center = CostCenter::query()->whereKey($center->id)->lockForUpdate()->firstOrFail();
            $parent = $parent ? CostCenter::query()->whereKey($parent->id)->lockForUpdate()->firstOrFail() : null;
            if ($parent && ($parent->company_id !== $center->company_id || ! $parent->is_header
                || $parent->id === $center->id || str_starts_with((string) $parent->path, $center->path.'/'))) {
                throw new BusinessRuleException('Invalid cost center parent or hierarchy cycle.');
            }
            $center->forceFill(['parent_cost_center_id' => $parent?->id])->save();
            $this->refreshPath($center);

            return $center->fresh();
        });
    }

    public function refreshPath(CostCenter $center): void
    {
        $center->refresh();
        $center->forceFill([
            'level' => $center->parent ? $center->parent->level + 1 : 0,
            'path' => $center->parent ? $center->parent->path.'/'.$center->id : (string) $center->id,
        ])->saveQuietly();
        foreach ($center->children()->get() as $child) {
            $this->refreshPath($child);
        }
    }
}
