<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\CashBoxActivated;
use App\Events\CashBoxClosed;
use App\Events\CashBoxCreated;
use App\Events\CashBoxSuspended;
use App\Models\CashBox;
use App\Models\TreasuryTransfer;
use Illuminate\Support\Facades\DB;

class CashBoxService
{
    public function __construct(
        private TenantContext $tenant,
        private TreasuryScopeService $scope,
        private AuditService $audit
    ) {
    }

    public function create(array $data): CashBox
    {
        return DB::transaction(function () use ($data) {
            $this->validate($data);
            $box = new CashBox($data);
            $box->forceFill([
                'company_id' => $this->tenant->companyId(), 'status' => 'draft',
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('treasury.cash_box.created', $box);
            DB::afterCommit(fn () => event(new CashBoxCreated($box->id)));

            return $box;
        });
    }

    public function update(CashBox $box, array $data): CashBox
    {
        return DB::transaction(function () use ($box, $data) {
            $box = $this->locked($box);
            if ($box->status === 'closed') {
                throw new BusinessRuleException('Closed cash box cannot be changed.');
            }
            $this->validate($data);
            if ((int) $data['gl_account_id'] !== $box->gl_account_id) {
                throw new BusinessRuleException('Cash box GL account cannot be replaced.');
            }
            $box->fill($data);
            if ($box->status === 'active' && ! empty($data['is_primary'])) {
                CashBox::query()->where('company_id', $box->company_id)->where('branch_id', $box->branch_id)
                    ->where('currency_id', $box->currency_id)->whereKeyNot($box->id)
                    ->lockForUpdate()->update(['is_primary' => false]);
            }
            $box->forceFill(['updated_by' => $this->tenant->user()->id])->save();
            $this->audit->record('treasury.cash_box.updated', $box);

            return $box;
        });
    }

    public function action(CashBox $box, string $action, string $reason): CashBox
    {
        return DB::transaction(function () use ($box, $action, $reason) {
            $box = $this->locked($box);
            $transitions = [
                'activate' => [['draft', 'suspended'], 'active', CashBoxActivated::class],
                'suspend' => [['active'], 'suspended', CashBoxSuspended::class],
                'close' => [['draft', 'active', 'suspended'], 'closed', CashBoxClosed::class],
            ];
            if (! isset($transitions[$action]) || blank($reason)) {
                throw new BusinessRuleException('Invalid cash box action or reason.');
            }
            [$from, $to, $event] = $transitions[$action];
            if (! in_array($box->status, $from, true)) {
                throw new BusinessRuleException('Invalid cash box status transition.');
            }
            if ($action === 'close' && ($box->custodians()->where('is_active', true)->exists()
                || TreasuryTransfer::query()->whereIn('status', ['draft', 'pending_approval', 'approved', 'ready_for_processing'])
                    ->where(fn ($query) => $query->where('from_cash_box_id', $box->id)
                        ->orWhere('to_cash_box_id', $box->id))->exists())) {
                throw new BusinessRuleException('Active custodians or pending transfers block cash box closure.');
            }
            if ($to === 'active' && $box->is_primary) {
                CashBox::query()->where('company_id', $box->company_id)->where('branch_id', $box->branch_id)
                    ->where('currency_id', $box->currency_id)->whereKeyNot($box->id)
                    ->lockForUpdate()->update(['is_primary' => false]);
            }
            $box->forceFill([
                'status' => $to, 'updated_by' => $this->tenant->user()->id,
                'closed_by' => $to === 'closed' ? $this->tenant->user()->id : null,
                'closed_at' => $to === 'closed' ? now() : null,
            ])->save();
            $this->audit->record('treasury.cash_box.'.$action, $box, ['reason' => $reason]);
            DB::afterCommit(fn () => event(new $event($box->id)));

            return $box;
        });
    }

    private function validate(array $data): void
    {
        $this->scope->branch((int) $data['branch_id']);
        $this->scope->currency((int) $data['currency_id']);
        $this->scope->account((int) $data['gl_account_id'], 'cash');
        if (! empty($data['over_short_account_id'])) {
            $this->scope->account((int) $data['over_short_account_id']);
        }
        if (isset($data['minimum_cash_limit'], $data['maximum_cash_limit'])
            && bccomp((string) $data['minimum_cash_limit'], (string) $data['maximum_cash_limit'], 4) === 1) {
            throw new BusinessRuleException('Minimum cash limit cannot exceed maximum cash limit.');
        }
    }

    private function locked(CashBox $box): CashBox
    {
        return CashBox::query()->where('company_id', $this->tenant->companyId())
            ->whereKey($box->id)->lockForUpdate()->firstOrFail();
    }
}
