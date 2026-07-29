<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\CashBoxCustodianAssigned;
use App\Events\CashBoxCustodianRevoked;
use App\Models\CashBox;
use App\Models\CashBoxCustodian;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CashBoxCustodianService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function assign(CashBox $box, array $data): CashBoxCustodian
    {
        return DB::transaction(function () use ($box, $data) {
            $box = CashBox::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($box->id)->lockForUpdate()->firstOrFail();
            if ($box->status === 'closed') {
                throw new BusinessRuleException('Custodian cannot be assigned to a closed cash box.');
            }
            $user = User::query()->where('company_id', $box->company_id)->where('status', 'active')->findOrFail($data['user_id']);
            if (! $user->canAccessBranch($box->branch)) {
                throw new BusinessRuleException('Custodian user cannot access the cash box branch.');
            }
            if (! empty($data['employee_id'])) {
                Employee::query()->where('company_id', $box->company_id)
                    ->where('branch_id', $box->branch_id)->findOrFail($data['employee_id']);
            }
            $from = $data['valid_from'];
            $to = $data['valid_to'] ?? null;
            $overlap = CashBoxCustodian::query()->where('cash_box_id', $box->id)
                ->where('user_id', $user->id)->where('is_active', true)
                ->whereDate('valid_from', '<=', $to ?: '9999-12-31')
                ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $from))
                ->lockForUpdate()->exists();
            if ($overlap) {
                throw new BusinessRuleException('Overlapping custodian assignment is not allowed.');
            }
            if (! empty($data['is_primary'])) {
                $primaryOverlap = CashBoxCustodian::query()->where('cash_box_id', $box->id)
                    ->where('is_primary', true)->where('is_active', true)
                    ->whereDate('valid_from', '<=', $to ?: '9999-12-31')
                    ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $from))
                    ->lockForUpdate()->exists();
                if ($primaryOverlap) {
                    throw new BusinessRuleException('Only one primary custodian may be active for the same dates.');
                }
            }
            $custodian = new CashBoxCustodian($data);
            $custodian->forceFill([
                'company_id' => $box->company_id, 'cash_box_id' => $box->id,
                'assigned_by' => $this->tenant->user()->id, 'is_active' => true,
            ])->save();
            $this->audit->record('treasury.cash_box.custodian_assigned', $box, ['custodian_id' => $custodian->id]);
            DB::afterCommit(fn () => event(new CashBoxCustodianAssigned($custodian->id)));

            return $custodian;
        });
    }

    public function revoke(CashBoxCustodian $custodian, string $reason): CashBoxCustodian
    {
        return DB::transaction(function () use ($custodian, $reason) {
            $custodian = CashBoxCustodian::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($custodian->id)->lockForUpdate()->firstOrFail();
            if (! $this->tenant->user()->canAccessBranch($custodian->cashBox->branch)) {
                throw new BusinessRuleException('لا يمكن تعديل تكليف خارج نطاق الشركة أو الفرع المسموح.');
            }
            if (! $custodian->is_active || blank($reason)) {
                throw new BusinessRuleException('Active custodian and revocation reason are required.');
            }
            $custodian->forceFill([
                'is_active' => false, 'valid_to' => now()->toDateString(),
                'revoked_by' => $this->tenant->user()->id, 'revoked_at' => now(),
            ])->save();
            $this->audit->record('treasury.cash_box.custodian_revoked', $custodian->cashBox, [
                'custodian_id' => $custodian->id, 'reason' => $reason,
            ]);
            DB::afterCommit(fn () => event(new CashBoxCustodianRevoked($custodian->id)));

            return $custodian;
        });
    }

    public function assert(CashBox $box, string $ability, ?string $amount = null): void
    {
        if ($this->tenant->user()->hasPermission('treasury.cash_boxes.manage_custodians')) {
            return;
        }
        $custodian = CashBoxCustodian::query()->where('cash_box_id', $box->id)
            ->where('user_id', $this->tenant->user()->id)->where('is_active', true)
            ->whereDate('valid_from', '<=', now())->where(fn ($query) => $query->whereNull('valid_to')
                ->orWhereDate('valid_to', '>=', now()))->first();
        if (! $custodian) {
            throw new BusinessRuleException('المستخدم غير معيّن أمينًا نشطًا على الخزينة.');
        }
        if (! $custodian->{$ability}) {
            $messages = [
                'can_receive' => 'أمين الخزينة غير مخول بالقبض.',
                'can_pay' => 'أمين الخزينة غير مخول بالصرف.',
                'can_transfer' => 'أمين الخزينة غير مخول بالتحويل.',
            ];
            throw new BusinessRuleException($messages[$ability] ?? 'صلاحية أمين الخزينة غير كافية.');
        }
        if ($ability === 'can_pay' && $amount !== null && $custodian->payment_limit !== null
            && bccomp($amount, (string) $custodian->payment_limit, 4) === 1) {
            throw new BusinessRuleException('مبلغ الصرف يتجاوز الحد المسموح لأمين الخزينة.');
        }
    }

    public function update(CashBoxCustodian $custodian, array $data): CashBoxCustodian
    {
        return DB::transaction(function () use ($custodian, $data) {
            $custodian = CashBoxCustodian::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($custodian->id)->lockForUpdate()->firstOrFail();
            if (! $this->tenant->user()->canAccessBranch($custodian->cashBox->branch)) {
                throw new BusinessRuleException('لا يمكن تعديل تكليف خارج نطاق الشركة أو الفرع المسموح.');
            }
            if (! $custodian->is_active) {
                throw new BusinessRuleException('لا يمكن تعديل تكليف أمين غير نشط.');
            }
            $validTo = $data['valid_to'] ?? null;
            if ($validTo !== null && $validTo < $custodian->valid_from->toDateString()) {
                throw new BusinessRuleException('تاريخ نهاية التكليف يجب أن يكون بعد تاريخ البداية.');
            }
            if (! empty($data['is_primary'])) {
                $primaryOverlap = CashBoxCustodian::query()->where('cash_box_id', $custodian->cash_box_id)
                    ->whereKeyNot($custodian->id)->where('is_primary', true)->where('is_active', true)
                    ->whereDate('valid_from', '<=', $validTo ?: '9999-12-31')
                    ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $custodian->valid_from))
                    ->lockForUpdate()->exists();
                if ($primaryOverlap) {
                    throw new BusinessRuleException('لا يجوز وجود أمين رئيسي آخر في نفس الفترة.');
                }
            }
            $before = $custodian->only(['can_receive', 'can_pay', 'can_transfer', 'payment_limit', 'is_primary', 'valid_to']);
            $custodian->forceFill([
                'can_receive' => (bool) ($data['can_receive'] ?? false),
                'can_pay' => (bool) ($data['can_pay'] ?? false),
                'can_transfer' => (bool) ($data['can_transfer'] ?? false),
                'payment_limit' => $data['payment_limit'] ?? null,
                'is_primary' => (bool) ($data['is_primary'] ?? false),
                'valid_to' => $validTo,
            ])->save();
            $this->audit->record('treasury.cash_box.custodian_updated', $custodian->cashBox, [
                'custodian_id' => $custodian->id, 'before' => $before,
                'after' => $custodian->only(['can_receive', 'can_pay', 'can_transfer', 'payment_limit', 'is_primary', 'valid_to']),
            ]);

            return $custodian;
        });
    }
}
