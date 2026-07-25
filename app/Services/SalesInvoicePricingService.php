<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Support\Collection;

class SalesInvoicePricingService
{
    public function __construct(private MoneyRoundingService $rounding)
    {
    }

    public function calculate(array $items, array $header = [], int $decimals = 2): array
    {
        if ($items === [] || ! empty($header['price_includes_tax'])) {
            throw new BusinessRuleException($items === [] ? 'Invoice must contain at least one item.' : 'Tax-inclusive pricing is not supported.');
        }
        $rows = collect($items)->values()->map(function (array $item, int $index) use ($decimals) {
            $quantity = (string) ($item['quantity'] ?? 0);
            $unitPrice = (string) ($item['unit_price'] ?? 0);
            if (bccomp($quantity, '0', 6) !== 1 || bccomp($unitPrice, '0', 4) === -1) {
                throw new BusinessRuleException('Invoice quantity and price are invalid.');
            }
            $gross = $this->rounding->round(bcmul($quantity, $unitPrice, 8), $decimals);
            $discount = $this->discount($gross, $item['discount_type'] ?? null, (string) ($item['discount_value'] ?? 0), $decimals);
            if (bccomp($discount, $gross, $decimals) === 1) {
                throw new BusinessRuleException('Item discount exceeds its gross amount.');
            }
            $net = $this->rounding->round(bcsub($gross, $discount, 8), $decimals);

            return array_merge($item, [
                'gross_amount' => $gross, 'discount_amount' => $discount, 'net_amount' => $net,
                'tax_rate' => (string) ($item['tax_rate'] ?? 0), 'sort_order' => $index,
            ]);
        });
        $subtotal = $this->sum($rows, 'net_amount', $decimals);
        $global = $this->discount($subtotal, $header['discount_type'] ?? null, (string) ($header['discount_value'] ?? 0), $decimals);
        if (bccomp($global, $subtotal, $decimals) === 1) {
            throw new BusinessRuleException('Invoice discount exceeds subtotal.');
        }
        $remaining = $global;
        $rows = $rows->map(function (array $row, int $index) use ($rows, $subtotal, $global, $decimals, &$remaining) {
            $allocation = $index === $rows->count() - 1
                ? $remaining
                : $this->rounding->round(bcdiv(bcmul($global, $row['net_amount'], 8), $subtotal ?: '1', 8), $decimals);
            $remaining = $this->rounding->round(bcsub($remaining, $allocation, 8), $decimals);
            $taxable = $this->rounding->round(bcsub($row['net_amount'], $allocation, 8), $decimals);
            $tax = $this->rounding->round(bcdiv(bcmul($taxable, $row['tax_rate'], 8), '100', 8), $decimals);
            $metadata = array_merge($row['metadata'] ?? [], ['header_discount_allocation' => $allocation]);

            return array_merge($row, [
                'tax_amount' => $tax, 'total' => $this->rounding->round(bcadd($taxable, $tax, 8), $decimals),
                'metadata' => $metadata,
            ]);
        });
        $tax = $this->sum($rows, 'tax_amount', $decimals);
        $unrounded = bcadd(bcsub($subtotal, $global, 8), $tax, 8);
        $total = $this->rounding->round($unrounded, $decimals);

        return [
            'items' => $rows->all(), 'subtotal' => $subtotal, 'discount_amount' => $global,
            'tax_amount' => $tax, 'rounding_amount' => $this->rounding->round(bcsub($total, $unrounded, 8), $decimals),
            'total' => $total,
        ];
    }

    private function discount(string $base, ?string $type, string $value, int $decimals): string
    {
        if (! $type || bccomp($value, '0', 4) !== 1) {
            return $this->rounding->round(0, $decimals);
        }
        if ($type === 'percentage' && bccomp($value, '100', 4) === 1) {
            throw new BusinessRuleException('Discount percentage cannot exceed 100.');
        }

        return $this->rounding->round($type === 'percentage' ? bcdiv(bcmul($base, $value, 8), '100', 8) : $value, $decimals);
    }

    private function sum(Collection $rows, string $key, int $decimals): string
    {
        return $this->rounding->round($rows->reduce(fn ($sum, $row) => bcadd($sum, (string) $row[$key], 8), '0'), $decimals);
    }
}
