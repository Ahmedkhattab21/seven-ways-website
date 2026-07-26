<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class ComparativeFinancialReportService
{
    public function previousFilters(array $filters, string $comparison): array
    {
        $from = CarbonImmutable::parse($filters['date_from']);
        $to = CarbonImmutable::parse($filters['date_to']);
        if ($comparison === 'previous_year') {
            return [...$filters, 'date_from' => $from->subYear()->toDateString(), 'date_to' => $to->subYear()->toDateString()];
        }
        if ($from->isSameDay($from->startOfMonth()) && $to->isSameDay($to->endOfMonth())) {
            return [...$filters, 'date_from' => $from->subMonth()->startOfMonth()->toDateString(),
                'date_to' => $from->subMonth()->endOfMonth()->toDateString()];
        }
        $days = $from->diffInDays($to) + 1;

        return [...$filters, 'date_to' => $from->subDay()->toDateString(),
            'date_from' => $from->subDays($days)->toDateString()];
    }

    public function compare(array $current, array $previous, array $fields): array
    {
        return collect($fields)->mapWithKeys(function ($field) use ($current, $previous) {
            $now = (string) ($current[$field] ?? '0');
            $before = (string) ($previous[$field] ?? '0');
            $difference = bcsub($now, $before, 4);

            return [$field => [
                'current' => $now, 'previous' => $before, 'difference' => $difference,
                'percentage' => bccomp($before, '0', 4) === 0 ? null : bcmul(bcdiv($difference, $before, 8), '100', 2),
            ]];
        })->all();
    }
}
