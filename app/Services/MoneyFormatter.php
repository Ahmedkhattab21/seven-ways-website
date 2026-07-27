<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Currency;

class MoneyFormatter
{
    public function format(
        int|float|string $amount,
        ?Currency $currency = null,
        ?string $locale = null,
        ?Company $company = null
    ): string {
        $currency ??= $company?->currency;
        $locale ??= app()->getLocale();
        $formatted = number_format(
            (float) $amount,
            (int) ($currency?->decimal_places ?? $company?->money_decimal_places ?? 2),
            '.',
            ','
        );
        $label = $locale === 'ar'
            ? ($currency?->symbol ?: $currency?->code)
            : $currency?->code;
        $label ??= config('localization.default_currency_code', 'EGP');

        return trim($formatted.' '.$label);
    }

    public function formatDocument(
        int|float|string $amount,
        object $document,
        ?Company $company = null,
        ?string $locale = null
    ): string {
        $documentCurrency = $document->currency ?? null;

        return $this->format(
            $amount,
            $documentCurrency instanceof Currency ? $documentCurrency : null,
            $locale,
            $company
        );
    }
}
