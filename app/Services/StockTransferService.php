<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\InventoryRoll;
use App\Models\Product;
use App\Models\RollScrap;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function create(array $data): StockTransfer
    {
        return DB::transaction(function () use ($data) {
            $from = $this->warehouse((int) $data['from_warehouse_id'], true);
            $to = $this->warehouse((int) $data['to_warehouse_id']);
            if ($from->id === $to->id || $from->company_id !== $to->company_id) {
                throw new BusinessRuleException('Source and destination warehouses must differ inside one company.');
            }
            $transfer = new StockTransfer;
            $transfer->forceFill([
                'company_id' => $this->tenant->companyId(),
                'transfer_number' => $this->numbers->next('stock_transfer', $from->company_id, $from->branch_id),
                'transfer_type' => $from->branch_id === $to->branch_id ? 'internal' : 'inter_branch',
                'from_branch_id' => $from->branch_id, 'from_warehouse_id' => $from->id,
                'to_branch_id' => $to->branch_id, 'to_warehouse_id' => $to->id,
                'status' => 'draft', 'requested_by' => $this->tenant->user()->id,
                'requested_at' => now(), 'expected_delivery_at' => $data['expected_delivery_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->save();
            foreach ($data['items'] as $item) {
                $transfer->items()->create($this->itemData($from, $item));
            }
            if (! $transfer->items()->exists()) {
                throw new BusinessRuleException('Transfer must contain at least one item.');
            }
            $this->audit->record('stock_transfer.created', $transfer, ['items' => $transfer->items()->count()]);

            return $transfer;
        });
    }

    public function submit(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer) {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $this->assertTenant($transfer);
            if ($transfer->status !== 'draft' || ! $transfer->items()->exists()) {
                throw new BusinessRuleException('Only a non-empty draft can be submitted.');
            }
            $transfer->forceFill(['status' => 'pending_approval'])->save();
            $this->audit->record('stock_transfer.requested', $transfer);

            return $transfer;
        });
    }

    public function update(StockTransfer $transfer, array $data): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $data) {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $this->assertTenant($transfer);
            if ($transfer->status !== 'draft') {
                throw new BusinessRuleException('Only a draft transfer can be edited.');
            }
            $from = $this->warehouse((int) $data['from_warehouse_id'], true);
            $to = $this->warehouse((int) $data['to_warehouse_id']);
            if ($from->id === $to->id || $from->company_id !== $to->company_id) {
                throw new BusinessRuleException('Source and destination warehouses must differ inside one company.');
            }
            $transfer->items()->delete();
            foreach ($data['items'] as $item) {
                $transfer->items()->create($this->itemData($from, $item));
            }
            $transfer->forceFill([
                'transfer_type' => $from->branch_id === $to->branch_id ? 'internal' : 'inter_branch',
                'from_branch_id' => $from->branch_id, 'from_warehouse_id' => $from->id,
                'to_branch_id' => $to->branch_id, 'to_warehouse_id' => $to->id,
                'expected_delivery_at' => $data['expected_delivery_at'] ?? null, 'notes' => $data['notes'] ?? null,
            ])->save();
            $this->audit->record('stock_transfer.updated', $transfer);

            return $transfer;
        });
    }

    private function itemData(Warehouse $from, array $item): array
    {
        $product = Product::query()->whereKey($item['product_id'])->where('company_id', $from->company_id)->firstOrFail();
        $type = $item['item_type'];
        $quantity = (string) ($item['requested_quantity'] ?? 0);
        $rollId = $item['roll_id'] ?? null;
        $scrapId = $item['scrap_id'] ?? null;
        if ($type === 'quantity' && ($rollId || $scrapId || bccomp($quantity, '0', 6) <= 0)) {
            throw new BusinessRuleException('Quantity transfer item is invalid.');
        }
        if ($type === 'roll') {
            $roll = InventoryRoll::query()->whereKey($rollId)->where('company_id', $from->company_id)
                ->where('warehouse_id', $from->id)->where('product_id', $product->id)
                ->whereIn('status', ['available', 'opened'])->firstOrFail();
            $quantity = '1';
        }
        if ($type === 'scrap') {
            $scrap = RollScrap::query()->whereKey($scrapId)->where('company_id', $from->company_id)
                ->where('warehouse_id', $from->id)->where('status', 'available')->with('sourceRoll')->firstOrFail();
            if ($scrap->sourceRoll->product_id !== $product->id) {
                throw new BusinessRuleException('Scrap product does not match the transfer item.');
            }
            $quantity = '1';
        }
        if (! in_array($type, ['quantity', 'roll', 'scrap'], true) || ($rollId && $scrapId)) {
            throw new BusinessRuleException('Transfer item type is invalid.');
        }

        return [
            'product_id' => $product->id, 'item_type' => $type, 'roll_id' => $rollId,
            'scrap_id' => $scrapId, 'requested_quantity' => $quantity,
            'unit_id' => $product->stock_unit_id, 'notes' => $item['notes'] ?? null,
        ];
    }

    private function warehouse(int $id, bool $mustAccess = false): Warehouse
    {
        $warehouse = Warehouse::query()->whereKey($id)->where('company_id', $this->tenant->companyId())
            ->where('is_active', true)->where('is_system', false)->firstOrFail();
        if ($mustAccess && ! $this->tenant->accessibleBranches()->contains('id', $warehouse->branch_id)) {
            throw new BusinessRuleException('Source branch is outside your access scope.', status: 403);
        }

        return $warehouse;
    }

    private function assertTenant(StockTransfer $transfer): void
    {
        if ($transfer->company_id !== $this->tenant->companyId()) {
            throw new BusinessRuleException('Transfer is outside the current company.', status: 403);
        }
    }
}
