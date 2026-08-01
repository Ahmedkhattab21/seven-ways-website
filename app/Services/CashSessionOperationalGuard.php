<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\CashBox;
use App\Models\CashBoxSession;

class CashSessionOperationalGuard
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function assertReady(CashBox $box, ?int $sessionId = null): ?CashBoxSession
    {
        if (! $box->requires_shift_opening) {
            return null;
        }

        return $this->assertActiveSession($box, $sessionId);
    }

    public function assertActiveSession(CashBox $box, ?int $sessionId): CashBoxSession
    {
        if (! $sessionId) {
            throw new BusinessRuleException('يجب تحديد جلسة خزينة نشطة.');
        }

        $session = CashBoxSession::query()
            ->where('company_id', $this->tenant->companyId())
            ->where('cash_box_id', $box->id)
            ->where('branch_id', $box->branch_id)
            ->where('active_guard', 'active')
            ->where('status', 'counting')
            ->whereKey($sessionId)
            ->latest('id')
            ->first();

        if (! $session || ! $session->counts()->where('count_type', 'opening')->where('status', 'approved')->exists()) {
            throw new BusinessRuleException('يجب تسجيل ومراجعة واعتماد العد الافتتاحي قبل تنفيذ حركات الخزينة.');
        }

        return $session;
    }
}
