<?php

namespace App\Core\Support;

final class DocumentStatus
{
    public const DRAFT = 'draft';

    public const PENDING_APPROVAL = 'pending_approval';

    public const APPROVED = 'approved';

    public const POSTED = 'posted';

    public const CANCELLED = 'cancelled';

    public static function values(): array
    {
        return [
            self::DRAFT,
            self::PENDING_APPROVAL,
            self::APPROVED,
            self::POSTED,
            self::CANCELLED,
        ];
    }

    private function __construct()
    {
    }
}
