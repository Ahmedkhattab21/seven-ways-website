<?php

namespace App\Services;

use App\Models\PostingProfile;

class PostingProfileResolver
{
    public function resolve(int $companyId, string $sourceType, string $date, ?int $overrideId = null): ?PostingProfile
    {
        return PostingProfile::query()->where('company_id', $companyId)
            ->where('source_type', $sourceType)->where('status', 'active')
            ->when($overrideId, fn ($query) => $query->whereKey($overrideId))
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('is_default')->orderByDesc('version')->first();
    }
}
