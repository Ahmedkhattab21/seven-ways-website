<?php

namespace App\Core\Support;

final class ApprovalStatus
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public static function values(): array
    {
        return [self::PENDING, self::APPROVED, self::REJECTED];
    }

    private function __construct()
    {
    }
}
