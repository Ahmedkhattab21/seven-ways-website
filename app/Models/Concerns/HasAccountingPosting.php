<?php

namespace App\Models\Concerns;

use App\Models\AccountingPostingLink;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAccountingPosting
{
    public function accountingPostingLinks(): MorphMany
    {
        return $this->morphMany(AccountingPostingLink::class, 'source', 'source_type', 'source_id');
    }

    public function isPostedToAccounting(string $action = 'post'): bool
    {
        return AccountingPostingLink::query()->where('company_id', $this->company_id)
            ->where('source_type', $this->getMorphClass())->where('source_id', $this->getKey())
            ->where('posting_action', $action)->whereIn('status', ['posted', 'not_required'])->exists();
    }
}
