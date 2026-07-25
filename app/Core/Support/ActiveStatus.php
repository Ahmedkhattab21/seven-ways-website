<?php

namespace App\Core\Support;

final class ActiveStatus
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public static function values(): array
    {
        return [self::ACTIVE, self::INACTIVE];
    }

    private function __construct()
    {
    }
}
