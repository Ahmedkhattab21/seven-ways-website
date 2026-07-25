<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\ProductUnitConversion;
use Illuminate\Support\Facades\DB;

class ProductUnitConversionService
{
    public function save(Product $product, int $fromUnitId, int $toUnitId, string $factor, array $flags = []): ProductUnitConversion
    {
        if ($fromUnitId === $toUnitId || bccomp($factor, '0', 8) <= 0) {
            throw new BusinessRuleException('Conversion units must differ and factor must be positive.');
        }
        if ($product->tracking_type === 'roll') {
            throw new BusinessRuleException('Roll dimensions are converted from the actual roll, not a fixed conversion.');
        }

        return DB::transaction(function () use ($product, $fromUnitId, $toUnitId, $factor, $flags) {
            $inverse = ProductUnitConversion::query()
                ->where('product_id', $product->id)
                ->where('from_unit_id', $toUnitId)
                ->where('to_unit_id', $fromUnitId)
                ->lockForUpdate()
                ->first();
            if ($inverse && bccomp(bcmul($factor, $inverse->factor, 8), '1', 8) !== 0) {
                throw new BusinessRuleException('Conflicting inverse unit conversion.');
            }

            return ProductUnitConversion::query()->updateOrCreate(
                ['product_id' => $product->id, 'from_unit_id' => $fromUnitId, 'to_unit_id' => $toUnitId],
                [
                    'product_id' => $product->id,
                    'from_unit_id' => $fromUnitId,
                    'to_unit_id' => $toUnitId,
                    'factor' => $factor,
                    'is_purchase_conversion' => (bool) ($flags['is_purchase_conversion'] ?? false),
                    'is_sale_conversion' => (bool) ($flags['is_sale_conversion'] ?? false),
                ]
            );
        });
    }

    public function convert(Product $product, int $fromUnitId, int $toUnitId, string $quantity): string
    {
        if ($fromUnitId === $toUnitId) {
            return bcadd($quantity, '0', 6);
        }
        $conversion = ProductUnitConversion::query()
            ->where(['product_id' => $product->id, 'from_unit_id' => $fromUnitId, 'to_unit_id' => $toUnitId])
            ->first();
        if ($conversion) {
            return bcmul($quantity, $conversion->factor, 6);
        }
        $inverse = ProductUnitConversion::query()
            ->where('product_id', $product->id)
            ->where('from_unit_id', $toUnitId)
            ->where('to_unit_id', $fromUnitId)
            ->first();
        if (! $inverse) {
            throw new BusinessRuleException('No product unit conversion is configured.');
        }

        return bcdiv($quantity, $inverse->factor, 6);
    }
}
