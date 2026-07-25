<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\PurchaseOrderSent;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderIssuanceService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function send(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $order = PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->company_id === $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($order->branch), 403);
            if ($order->status !== 'approved') {
                throw new BusinessRuleException('Only approved purchase orders can be sent.');
            }
            $order->forceFill([
                'status' => 'sent', 'sent_by' => $this->tenant->user()->id, 'sent_at' => now(),
            ])->save();
            $this->audit->record('purchase_order.sent', $order);
            DB::afterCommit(fn () => event(new PurchaseOrderSent($order->id)));

            return $order;
        });
    }
}
