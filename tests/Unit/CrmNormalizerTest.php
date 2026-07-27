<?php

namespace Tests\Unit;

use App\Services\PhoneNormalizer;
use App\Services\PlateNormalizer;
use PHPUnit\Framework\TestCase;

class CrmNormalizerTest extends TestCase
{
    public function test_phone_normalizer_handles_egyptian_local_e164_and_international_numbers(): void
    {
        $normalizer = new PhoneNormalizer();

        $this->assertSame('201012345678', $normalizer->normalize('٠١٠ ١٢٣٤-٥٦٧٨'));
        $this->assertSame('201012345678', $normalizer->normalize('+20 (10) 1234 5678'));
        $this->assertSame('20212345678', $normalizer->normalize('02 1234 5678'));
        $this->assertSame('442071838750', $normalizer->normalize('00 44 20 7183 8750'));
    }

    public function test_plate_normalizer_handles_arabic_digits_spaces_and_english_case(): void
    {
        $normalizer = new PlateNormalizer();

        $this->assertSame('أبج1234', $normalizer->normalize(' أ ب ج ١٢٣٤ '));
        $this->assertSame('ABC123', $normalizer->normalize('abc-123'));
    }
}
