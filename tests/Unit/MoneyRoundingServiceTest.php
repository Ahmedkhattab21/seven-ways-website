<?php

namespace Tests\Unit;

use App\Services\MoneyRoundingService;
use Tests\TestCase;

class MoneyRoundingServiceTest extends TestCase
{
    public function test_decimal_rounding_is_half_up_without_float_drift(): void
    {
        $rounding = app(MoneyRoundingService::class);

        $this->assertSame('8.8889', $rounding->round(bcdiv('80', '9', 8), 4));
        $this->assertSame('1.2344', $rounding->round('1.234449999999', 4));
        $this->assertSame('1.2345', $rounding->round('1.234450000000', 4));
        $this->assertSame('-1.2345', $rounding->round('-1.234450000000', 4));
    }
}
