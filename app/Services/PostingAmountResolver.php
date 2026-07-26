<?php

namespace App\Services;

class PostingAmountResolver
{
    public function __construct(private MoneyRoundingService $rounding)
    {
    }

    public function amount(mixed $source, string $field): string
    {
        return $this->rounding->round((string) data_get($source, $field, '0'), 4);
    }

    public function base(string $amount, string $exchangeRate): string
    {
        return $this->rounding->round(bcmul($amount, $exchangeRate, 8), 4);
    }
}
