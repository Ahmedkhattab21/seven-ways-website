<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\PostingProfileActivated;
use App\Events\PostingProfileCreated;
use App\Events\PostingProfileSuperseded;
use App\Models\Account;
use App\Models\PostingProfile;
use Illuminate\Support\Facades\DB;

class PostingProfileService
{
    public function __construct(
        private TenantContext $tenant,
        private PostingProfileValidationService $validator,
        private AuditService $audit
    ) {
    }

    public function create(array $data, array $lines): PostingProfile
    {
        return DB::transaction(function () use ($data, $lines) {
            $companyId = $this->tenant->companyId();
            $version = (int) PostingProfile::query()->where('company_id', $companyId)
                ->where('code', $data['code'])->max('version') + 1;
            $profile = new PostingProfile($data);
            $profile->forceFill([
                'company_id' => $companyId, 'version' => $version, 'status' => 'draft',
                'created_by' => $this->tenant->user()->id,
            ])->save();
            foreach ($lines as $index => $line) {
                if (! empty($line['fixed_account_id'])) {
                    Account::query()->whereKey($line['fixed_account_id'])->where('company_id', $companyId)
                        ->where('is_active', true)->where('is_posting', true)->firstOrFail();
                }
                $profile->lines()->create($line + ['line_number' => $line['line_number'] ?? $index + 1]);
            }
            $this->audit->record('posting_profile.created', $profile);
            DB::afterCommit(fn () => event(new PostingProfileCreated($profile->id)));

            return $profile->load('lines');
        });
    }

    public function activate(PostingProfile $profile): PostingProfile
    {
        return DB::transaction(function () use ($profile) {
            $profile = PostingProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $this->assertTenant($profile);
            if ($profile->status !== 'draft') {
                throw new BusinessRuleException('Only draft profiles can be activated.');
            }
            $this->validator->assertActivatable($profile);
            if ($profile->is_default) {
                PostingProfile::query()->where('company_id', $profile->company_id)
                    ->where('source_type', $profile->source_type)->where('status', 'active')
                    ->where('is_default', true)->whereKeyNot($profile->id)
                    ->update(['status' => 'superseded', 'is_default' => false]);
            }
            $profile->forceFill([
                'status' => 'active', 'approved_by' => $this->tenant->user()->id, 'approved_at' => now(),
            ])->save();
            $this->audit->record('posting_profile.activated', $profile);
            DB::afterCommit(fn () => event(new PostingProfileActivated($profile->id)));

            return $profile;
        });
    }

    public function supersede(PostingProfile $profile): PostingProfile
    {
        $this->assertTenant($profile);
        if ($profile->status !== 'active') {
            throw new BusinessRuleException('Only active profiles can be superseded.');
        }
        $profile->forceFill(['status' => 'superseded', 'is_default' => false])->save();
        $this->audit->record('posting_profile.superseded', $profile);
        DB::afterCommit(fn () => event(new PostingProfileSuperseded($profile->id)));

        return $profile;
    }

    private function assertTenant(PostingProfile $profile): void
    {
        if ($profile->company_id !== $this->tenant->companyId()) {
            throw new BusinessRuleException('Posting profile is outside the current company.');
        }
    }
}
