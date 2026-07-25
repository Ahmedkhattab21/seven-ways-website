<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\GoodsReceiptItem;
use Illuminate\Database\Eloquent\Collection;

class PurchaseRollReceivingService
{
    public function __construct(private RollService $rolls)
    {
    }

    public function receive(GoodsReceiptItem $item): Collection
    {
        $definitions = $item->rolls ?? [];
        if ($item->product->tracking_type !== 'roll' || empty($definitions)) {
            throw new BusinessRuleException('Roll-tracked receipts require individual roll dimensions.');
        }
        $acceptedCount = (int) $item->accepted_quantity + (int) $item->free_quantity;
        if (count($definitions) !== $acceptedCount) {
            throw new BusinessRuleException('The roll count must match accepted plus free rolls.');
        }
        $areas = array_map(
            fn (array $roll) => bcmul((string) $roll['width'], (string) $roll['length'], 6),
            $definitions
        );
        $totalArea = array_reduce($areas, fn (string $carry, string $area) => bcadd($carry, $area, 6), '0.000000');
        if (bccomp($totalArea, '0', 6) !== 1) {
            throw new BusinessRuleException('Roll area must be positive.');
        }
        $allocated = '0.0000';
        $created = new Collection;
        $last = array_key_last($definitions);
        foreach ($definitions as $index => $definition) {
            $cost = $index === $last
                ? bcsub($item->total_cost, $allocated, 4)
                : round((float) bcmul($item->total_cost, bcdiv($areas[$index], $totalArea, 8), 8), 2);
            $cost = number_format((float) $cost, 4, '.', '');
            $allocated = bcadd($allocated, $cost, 4);
            $created->push($this->rolls->receive($item->receipt->warehouse, $item->product, [
                'supplier_roll_number' => $definition['supplier_roll_number'] ?? null,
                'batch_number' => $definition['batch_number'] ?? $item->batch_number,
                'width' => $definition['width'],
                'original_length' => $definition['length'],
                'total_cost' => $cost,
                'manufacturing_date' => $definition['manufacturing_date'] ?? $item->manufacture_date,
                'expiry_date' => $definition['expiry_date'] ?? $item->expiry_date,
                'supplier_id' => $item->receipt->supplier_id,
                'purchase_order_item_id' => $item->purchase_order_item_id,
                'goods_receipt_item_id' => $item->id,
            ], [
                'type' => 'goods_receipt_item', 'id' => $item->id, 'movement_type' => 'purchase_roll_receipt',
            ]));
        }

        return $created;
    }
}
