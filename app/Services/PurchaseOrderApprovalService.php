<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\PurchaseOrderApproved;
use App\Models\PurchaseOrder;
use App\Models\SupplierProduct;
use Illuminate\Support\Facades\DB;

class PurchaseOrderApprovalService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function submit(PurchaseOrder $order): PurchaseOrder
    {
        return $this->transition($order, 'draft', 'pending_approval', 'submitted');
    }

    public function approve(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lockScoped($order);
            if ($order->status !== 'pending_approval') {
                throw new BusinessRuleException('Only pending purchase orders can be approved.');
            }
            if ($order->supplier->status !== 'active') {
                throw new BusinessRuleException('Blocked or inactive suppliers cannot be approved.');
            }
            if (config('purchasing.separation_of_duties', true) && $order->created_by === $this->tenant->user()->id) {
                throw new BusinessRuleException('The purchase order creator cannot approve it.');
            }
            foreach ($order->items as $item) {
                $last = SupplierProduct::where('supplier_id', $order->supplier_id)
                    ->where('product_id', $item->product_id)->value('last_purchase_price');
                if ($last && bccomp($last, '0', 4) === 1) {
                    $variance = bcmul(bcdiv(bcsub($item->unit_price, $last, 4), $last, 8), '100', 4);
                    if (bccomp($variance, (string) config('purchasing.price_variance_percentage', 10), 4) === 1
                        && ! $this->tenant->user()->hasPermission('purchase_orders.override_price')) {
                        throw new BusinessRuleException('Purchase price variance requires override permission.');
                    }
                }
            }
            $order->forceFill([
                'status' => 'approved', 'approved_by' => $this->tenant->user()->id, 'approved_at' => now(),
            ])->save();
            $this->audit->record('purchase_order.approved', $order);
            DB::afterCommit(fn () => event(new PurchaseOrderApproved($order->id)));

            return $order;
        });
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lockScoped($order);
            if (! in_array($order->status, ['draft', 'pending_approval', 'approved'], true)
                || $order->items()->where('received_quantity', '>', 0)->exists()) {
                throw new BusinessRuleException('This purchase order can no longer be cancelled.');
            }
            $order->forceFill([
                'status' => 'cancelled', 'cancelled_by' => $this->tenant->user()->id, 'cancelled_at' => now(),
            ])->save();

            return $order;
        });
    }

    private function transition(PurchaseOrder $order, string $from, string $to, string $action): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $from, $to, $action) {
            $order = $this->lockScoped($order);
            if ($order->status !== $from || ! $order->items()->exists()) {
                throw new BusinessRuleException("Purchase order must be {$from} and contain items.");
            }
            $order->forceFill([
                'status' => $to, "{$action}_by" => $this->tenant->user()->id, "{$action}_at" => now(),
            ])->save();

            return $order;
        });
    }

    private function lockScoped(PurchaseOrder $order): PurchaseOrder
    {
        $order = PurchaseOrder::whereKey($order->id)->lockForUpdate()->with(['supplier', 'items'])->firstOrFail();
        abort_unless($order->company_id === $this->tenant->companyId()
            && $this->tenant->user()->canAccessBranch($order->branch), 403);

        return $order;
    }
}
