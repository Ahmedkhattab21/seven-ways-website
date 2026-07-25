<?php

namespace Tests\Unit;

use App\Services\PhoneNormalizer;
use App\Services\PlateNormalizer;
use PHPUnit\Framework\TestCase;

class CrmNormalizerTest extends TestCase
{
    public function test_phone_normalizer_handles_arabic_digits_and_sa_local_and_international_numbers(): void
    {
        $normalizer = new PhoneNormalizer();

        $this->assertSame('966501234567', $normalizer->normalize('٠٥٠ ١٢٣-٤٥٦٧'));
        $this->assertSame('966501234567', $normalizer->normalize('+966 (50) 123 4567'));
        $this->assertSame('442071838750', $normalizer->normalize('00 44 20 7183 8750'));
    }

    public function test_plate_normalizer_handles_arabic_digits_spaces_and_english_case(): void
    {
        $normalizer = new PlateNormalizer();

        $this->assertSame('أبج1234', $normalizer->normalize(' أ ب ج ١٢٣٤ '));
        $this->assertSame('ABC123', $normalizer->normalize('abc-123'));
    }
}
