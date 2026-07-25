<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;

class SupplierInvoicePricingService
{
    public function __construct(private MoneyRoundingService $rounding)
    {
    }

    public function calculate(array $data, array $items): array
    {
        $subtotal = $tax = '0.0000';
        $prepared = [];
        foreach ($items as $index => $item) {
            $quantity = (string) $item['quantity'];
            $price = (string) $item['unit_price'];
            if (bccomp($quantity, '0', 6) !== 1 || bccomp($price, '0', 4) < 0) {
                throw new BusinessRuleException('Supplier invoice quantity and price are invalid.');
            }
            $net = $this->rounding->round(bcmul($quantity, $price, 8), 2);
            $itemTax = $this->rounding->round(
                bcdiv(bcmul($net, (string) ($item['tax_rate'] ?? 0), 8), '100', 8),
                2
            );
            $prepared[] = array_merge($item, [
                'net_amount' => $net, 'tax_amount' => $itemTax,
                'total' => bcadd($net, $itemTax, 4), 'sort_order' => $index,
            ]);
            $subtotal = bcadd($subtotal, $net, 4);
            $tax = bcadd($tax, $itemTax, 4);
        }
        $discount = (string) ($data['discount_amount'] ?? 0);
        $shipping = (string) ($data['shipping_amount'] ?? 0);
        $other = (string) ($data['other_charges'] ?? 0);
        $rounding = (string) ($data['rounding_amount'] ?? 0);
        if (bccomp($discount, $subtotal, 4) === 1 || bccomp($discount, '0', 4) < 0
            || bccomp($shipping, '0', 4) < 0 || bccomp($other, '0', 4) < 0) {
            throw new BusinessRuleException('Supplier invoice charges are invalid.');
        }
        $total = bcadd(bcsub(bcadd($subtotal, $tax, 4), $discount, 4), bcadd($shipping, $other, 4), 4);
        $total = bcadd($total, $rounding, 4);

        return [
            'items' => $prepared,
            'totals' => [
                'subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => $tax,
                'shipping_amount' => $shipping, 'other_charges' => $other,
                'rounding_amount' => $rounding, 'total' => $total, 'balance_due' => $total,
            ],
        ];
    }
}
