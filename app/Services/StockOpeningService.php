<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Product;
use App\Models\StockOpeningDocument;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class StockOpeningService
{
    public function __construct(
        private TenantContext $tenant,
        private InventoryService $inventory,
        private RollService $rolls,
        private AuditService $audit
    ) {
    }

    public function post(StockOpeningDocument $document): StockOpeningDocument
    {
        return DB::transaction(function () use ($document) {
            $document = StockOpeningDocument::query()->whereKey($document->id)->lockForUpdate()->with('items')->firstOrFail();
            if ($document->company_id !== $this->tenant->companyId()
                || ! $this->tenant->accessibleBranches()->contains('id', $document->branch_id)) {
                throw new BusinessRuleException('Opening document is outside the current tenant.', status: 403);
            }
            if ($document->status !== 'draft') {
                throw new BusinessRuleException('Opening balance can be posted once only.');
            }
            $warehouse = Warehouse::findOrFail($document->warehouse_id);
            if ($warehouse->is_system || $warehouse->warehouse_type === 'transit') {
                throw new BusinessRuleException('Opening stock cannot be posted to a system Transit warehouse.');
            }
            foreach ($document->items->sortBy('product_id') as $item) {
                $product = Product::findOrFail($item->product_id);
                if ($product->tracking_type === 'roll') {
                    $roll = $this->rolls->receive($warehouse, $product, [
                        'roll_number' => $item->roll_number, 'width' => $item->roll_width,
                        'original_length' => $item->roll_length,
                        'total_cost' => bcmul(bcmul($item->roll_width, $item->roll_length, 6), $item->unit_cost, 4),
                    ], ['type' => 'stock_opening', 'id' => $document->id]);
                    $item->forceFill(['inventory_roll_id' => $roll->id])->save();
                } else {
                    $this->inventory->receive($warehouse, $product, $item->quantity, $item->unit_cost, 'opening_balance', [
                        'type' => 'stock_opening', 'id' => $document->id,
                    ]);
                }
            }
            $document->forceFill(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $this->tenant->user()->id])->save();
            $this->audit->record('stock_opening.posted', $document);

            return $document;
        });
    }
}
