<?php

namespace App\Services;

class PhoneNormalizer
{
    public function normalize(?string $phone, string $defaultCountry = 'SA'): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = strtr($phone, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $digits = preg_replace('/\D+/', '', $digits) ?: null;
        if (! $digits) {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }
        if ($defaultCountry === 'SA') {
            if (preg_match('/^05\d{8}$/', $digits)) {
                return '966'.substr($digits, 1);
            }
            if (preg_match('/^5\d{8}$/', $digits)) {
                return '966'.$digits;
            }
        }

        return $digits;
    }
}
