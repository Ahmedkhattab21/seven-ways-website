<?php

namespace App\Services;

class MoneyRoundingService
{
    public function round(string|int|float $amount, int $decimals = 2): string
    {
        $amount = (string) $amount;
        $increment = $decimals === 0
            ? '0.5'
            : '0.'.str_repeat('0', $decimals).'5';
        $adjusted = bccomp($amount, '0', $decimals + 1) >= 0
            ? bcadd($amount, $increment, $decimals + 1)
            : bcsub($amount, $increment, $decimals + 1);

        return bcdiv($adjusted, '1', $decimals);
    }
}
