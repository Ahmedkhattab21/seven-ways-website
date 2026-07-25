<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\StockTransfer;
use App\Models\StockTransferDiscrepancy;
use App\Models\StockTransferItem;
use Illuminate\Support\Facades\DB;

class TransferDiscrepancyService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function report(StockTransfer $transfer, StockTransferItem $item, array $data): StockTransferDiscrepancy
    {
        if ($item->stock_transfer_id !== $transfer->id || $transfer->company_id !== $this->tenant->companyId()) {
            throw new BusinessRuleException('Discrepancy scope is invalid.', status: 403);
        }
        $discrepancy = StockTransferDiscrepancy::query()->create([
            'stock_transfer_id' => $transfer->id, 'stock_transfer_item_id' => $item->id,
            'discrepancy_type' => $data['discrepancy_type'], 'quantity' => $data['quantity'] ?? null,
            'description' => $data['description'], 'reported_by' => $this->tenant->user()->id, 'status' => 'open',
        ]);
        $this->audit->record('stock_transfer.discrepancy_reported', $discrepancy);

        return $discrepancy;
    }

    public function resolve(StockTransferDiscrepancy $discrepancy, string $resolution, string $status = 'resolved'): void
    {
        DB::transaction(function () use ($discrepancy, $resolution, $status) {
            $discrepancy = StockTransferDiscrepancy::query()->whereKey($discrepancy->id)->lockForUpdate()->firstOrFail();
            if ($discrepancy->status !== 'open' || trim($resolution) === '') {
                throw new BusinessRuleException('Only an open discrepancy can be resolved with a resolution.');
            }
            $discrepancy->forceFill([
                'status' => $status, 'resolution' => $resolution, 'resolved_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('stock_transfer.discrepancy_resolved', $discrepancy);
        });
    }
}
