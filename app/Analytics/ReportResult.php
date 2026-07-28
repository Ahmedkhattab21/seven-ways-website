<?php

namespace App\Analytics;

use Illuminate\Support\Collection;

class ReportResult
{
    public function __construct(
        public readonly array $summary,
        public readonly Collection $rows,
        public readonly array $meta = []
    ) {
    }
}
