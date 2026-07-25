<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;

class PurchaseOrderPricingService
{
    public function __construct(private MoneyRoundingService $rounding)
    {
    }

    public function calculate(array $data, array $items): array
    {
        $prepared = [];
        $subtotal = '0.0000';
        foreach ($items as $index => $item) {
            $quantity = (string) $item['ordered_quantity'];
            $unitPrice = (string) $item['unit_price'];
            if (bccomp($quantity, '0', 6) !== 1 || bccomp($unitPrice, '0', 4) < 0) {
                throw new BusinessRuleException('Purchase quantity must be positive and price cannot be negative.');
            }
            $gross = $this->rounding->round(bcmul($quantity, $unitPrice, 8), 2);
            $itemDiscount = $this->discount($gross, $item['discount_type'] ?? null, (string) ($item['discount_value'] ?? 0));
            $net = $this->rounding->round(bcsub($gross, $itemDiscount, 8), 2);
            $subtotal = bcadd($subtotal, $net, 4);
            $prepared[$index] = array_merge($item, [
                'gross_amount' => $gross, 'discount_amount' => $itemDiscount, 'net_before_global' => $net,
            ]);
        }
        $globalDiscount = $this->discount($subtotal, $data['discount_type'] ?? null, (string) ($data['discount_value'] ?? 0));
        $tax = $allocatedDiscount = '0.0000';
        $last = array_key_last($prepared);
        foreach ($prepared as $index => &$item) {
            $share = $index === $last
                ? bcsub($globalDiscount, $allocatedDiscount, 4)
                : (bccomp($subtotal, '0', 4) === 0 ? '0.0000' : $this->rounding->round(
                    bcmul($globalDiscount, bcdiv($item['net_before_global'], $subtotal, 8), 8),
                    2
                ));
            $allocatedDiscount = bcadd($allocatedDiscount, $share, 4);
            $net = $this->rounding->round(bcsub($item['net_before_global'], $share, 8), 2);
            $itemTax = $this->rounding->round(
                bcdiv(bcmul($net, (string) ($item['tax_rate'] ?? 0), 8), '100', 8),
                2
            );
            $item['discount_amount'] = bcadd($item['discount_amount'], $share, 4);
            $item['net_amount'] = $net;
            $item['tax_amount'] = $itemTax;
            $item['total'] = bcadd($net, $itemTax, 4);
            unset($item['net_before_global']);
            $tax = bcadd($tax, $itemTax, 4);
        }
        unset($item);
        $shipping = (string) ($data['shipping_amount'] ?? 0);
        $other = (string) ($data['other_charges'] ?? 0);
        $rounding = (string) ($data['rounding_amount'] ?? 0);
        foreach ([$shipping, $other] as $charge) {
            if (bccomp($charge, '0', 4) < 0) {
                throw new BusinessRuleException('Purchase charges cannot be negative.');
            }
        }
        $total = bcadd(bcadd(bcsub($subtotal, $globalDiscount, 4), $tax, 4), bcadd($shipping, $other, 4), 4);
        $total = bcadd($total, $rounding, 4);

        return [
            'items' => array_values($prepared),
            'totals' => [
                'subtotal' => $subtotal, 'discount_amount' => $globalDiscount, 'tax_amount' => $tax,
                'shipping_amount' => $shipping, 'other_charges' => $other, 'rounding_amount' => $rounding,
                'total' => $total,
            ],
        ];
    }

    private function discount(string $base, ?string $type, string $value): string
    {
        if (bccomp($value, '0', 4) < 0 || ($type === 'percentage' && bccomp($value, '100', 4) === 1)) {
            throw new BusinessRuleException('Invalid purchase discount.');
        }
        if ($type === 'percentage') {
            return $this->rounding->round(bcdiv(bcmul($base, $value, 8), '100', 8), 2);
        }
        if ($type === 'fixed') {
            if (bccomp($value, $base, 4) === 1) {
                throw new BusinessRuleException('Discount exceeds purchase value.');
            }

            return $this->rounding->round($value, 2);
        }

        return '0.0000';
    }
}
