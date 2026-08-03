<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServicePackage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class InvoiceWarrantySnapshotService
{
    public function applyToRow(array $row, mixed $invoiceDate): array
    {
        $source = match ($row['item_type'] ?? null) {
            'product' => ! empty($row['product_id']) ? Product::find($row['product_id']) : null,
            'service' => ! empty($row['service_id']) ? Service::find($row['service_id']) : null,
            'package' => ! empty($row['service_package_id'])
                ? ServicePackage::with('items.service')->find($row['service_package_id'])
                : null,
            default => null,
        };

        $snapshot = $this->build($source, $invoiceDate, $row['warranty'] ?? []);
        unset($row['warranty']);

        return array_merge($row, [
            'warranty_applies' => (bool) ($snapshot['applies'] ?? false),
            'warranty_snapshot' => $snapshot ?: null,
        ]);
    }

    public function build(?Model $source, mixed $invoiceDate, array $override = []): ?array
    {
        if (! $source) {
            return ! empty($override['applies'])
                ? $this->singleSnapshot(null, $invoiceDate, $override)
                : null;
        }

        if ($source instanceof ServicePackage) {
            $components = $source->items->map(function ($item) use ($invoiceDate) {
                $snapshot = $this->singleSnapshot($item->service, $invoiceDate);
                if (! $snapshot['applies']) {
                    return null;
                }

                return $snapshot + [
                    'service_id' => $item->service_id,
                    'service_name' => $item->service?->name,
                    'quantity' => (string) $item->quantity,
                ];
            })->filter()->values()->all();
            $package = $this->singleSnapshot($source, $invoiceDate, $override);
            if (! $package['applies'] && $components === []) {
                return null;
            }

            $package['applies'] = true;
            $package['components'] = $components;

            return $package;
        }

        $snapshot = $this->singleSnapshot($source, $invoiceDate, $override);

        return $snapshot['applies'] ? $snapshot : null;
    }

    private function singleSnapshot(?Model $source, mixed $invoiceDate, array $override = []): array
    {
        $legacyDuration = $source instanceof Product
            ? $source->warranty_months
            : ($source instanceof Service ? $source->default_warranty_months : null);
        $hasExplicitApplies = array_key_exists('applies', $override)
            && $override['applies'] !== null
            && $override['applies'] !== '';
        $applies = $hasExplicitApplies
            ? filter_var($override['applies'], FILTER_VALIDATE_BOOL)
            : (bool) ($source?->requires_warranty || $legacyDuration);
        $unit = $override['duration_unit']
            ?? $source?->default_warranty_duration_unit
            ?? ($legacyDuration ? 'months' : null);
        $value = $override['duration_value']
            ?? $source?->default_warranty_duration_value
            ?? $legacyDuration;
        $start = Carbon::parse($override['start_date'] ?? $invoiceDate)->startOfDay();
        if ($applies && ! in_array($unit, ['days', 'months', 'years', 'lifetime'], true)) {
            throw new BusinessRuleException('Warranty duration unit is invalid.');
        }
        if ($applies && $unit !== 'lifetime' && (! is_numeric($value) || (int) $value < 1)) {
            throw new BusinessRuleException('Warranty duration is required.');
        }

        return [
            'applies' => $applies,
            'product_name' => $source instanceof Product ? $source->name : null,
            'product_sku' => $source instanceof Product ? $source->sku : null,
            'manufacturer' => $source instanceof Product
                ? ($source->brand?->name ?: ($override['manufacturer'] ?? null))
                : ($override['manufacturer'] ?? null),
            'roll_name' => $override['roll_name'] ?? null,
            'film_type' => $override['film_type'] ?? $source?->default_warranty_film_type,
            'film_code' => $override['film_code'] ?? null,
            'application_area' => $override['application_area'] ?? $source?->default_warranty_application_area,
            'start_date' => $start->toDateString(),
            'duration_value' => $unit === 'lifetime' ? null : (int) $value,
            'duration_unit' => $unit,
            'end_date' => $this->endDate($start, $unit, $value),
            'terms' => $override['terms'] ?? $source?->default_warranty_terms,
            'notes' => $override['notes'] ?? $source?->default_warranty_notes,
        ];
    }

    private function endDate(Carbon $start, ?string $unit, mixed $value): ?string
    {
        return match ($unit) {
            'days' => $start->copy()->addDays((int) $value)->toDateString(),
            'months' => $start->copy()->addMonthsNoOverflow((int) $value)->toDateString(),
            'years' => $start->copy()->addYearsNoOverflow((int) $value)->toDateString(),
            default => null,
        };
    }
}
