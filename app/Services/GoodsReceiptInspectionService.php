<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\GoodsReceiptPartiallyRejected;
use App\Models\Attachment;
use App\Models\GoodsReceipt;
use Illuminate\Support\Facades\DB;

class GoodsReceiptInspectionService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function inspect(GoodsReceipt $receipt, array $decisions): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $decisions) {
            $receipt = GoodsReceipt::whereKey($receipt->id)->lockForUpdate()->with('items')->firstOrFail();
            abort_unless($receipt->company_id === $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($receipt->branch), 403);
            if (! in_array($receipt->status, ['received', 'inspection_pending'], true)) {
                throw new BusinessRuleException('Receipt is not awaiting inspection.');
            }
            $hasRejected = false;
            foreach ($receipt->items as $item) {
                $decision = $decisions[$item->id] ?? null;
                if (! $decision) {
                    throw new BusinessRuleException('Every receipt item needs an inspection decision.');
                }
                $accepted = (string) $decision['accepted_quantity'];
                $rejected = (string) $decision['rejected_quantity'];
                if (bccomp(bcadd($accepted, $rejected, 6), $item->received_quantity, 6) !== 0) {
                    throw new BusinessRuleException('Inspection quantities must equal received quantity.');
                }
                if (bccomp($rejected, '0', 6) === 1) {
                    $hasRejected = true;
                    if (blank($decision['rejection_reason'] ?? null)) {
                        throw new BusinessRuleException('Rejected goods require a reason.');
                    }
                    if (($decision['condition'] ?? null) === 'damaged'
                        && ! Attachment::where('attachable_type', GoodsReceipt::class)
                            ->where('attachable_id', $receipt->id)->where('category', 'goods_receipt_damage')->exists()) {
                        throw new BusinessRuleException('Damaged goods require a private inspection photo.');
                    }
                }
                $item->forceFill([
                    'accepted_quantity' => $accepted, 'rejected_quantity' => $rejected,
                    'condition' => $decision['condition'] ?? 'good',
                    'rejection_reason' => $decision['rejection_reason'] ?? null,
                ])->save();
            }
            $receipt->forceFill([
                'status' => $hasRejected ? 'partially_rejected' : 'accepted',
                'inspected_by' => $this->tenant->user()->id, 'inspected_at' => now(),
            ])->save();
            $this->audit->record('goods_receipt.inspected', $receipt, ['partially_rejected' => $hasRejected]);
            if ($hasRejected) {
                DB::afterCommit(fn () => event(new GoodsReceiptPartiallyRejected($receipt->id)));
            }

            return $receipt;
        });
    }
}
