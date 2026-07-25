<?php

namespace App\Services;

class MoneyRoundingService
{
    public function round(string|int|float $amount, int $decimals = 2): string
    {
        return number_format(round((float) $amount, $decimals, PHP_ROUND_HALF_UP), $decimals, '.', '');
    }
}
