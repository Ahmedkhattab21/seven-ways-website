<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\QuotationCreated;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private QuotationPricingService $pricing,
        private AuditService $audit
    ) {
    }

    public function save(array $data, array $items, ?Quotation $quotation = null): Quotation
    {
        $branch = Branch::query()->whereKey($data['branch_id'])->where('company_id', $this->tenant->companyId())->firstOrFail();
        $customer = Customer::query()->whereKey($data['customer_id'])->where('company_id', $branch->company_id)->firstOrFail();
        $vehicle = Vehicle::query()->whereKey($data['vehicle_id'])->where('company_id', $branch->company_id)
            ->where('customer_id', $customer->id)->with(['size', 'type'])->firstOrFail();
        $currency = Currency::query()->whereKey($data['currency_id'])->where('is_active', true)->firstOrFail();
        if (! empty($data['price_includes_tax'])) {
            throw new BusinessRuleException('أسعار المنتجات الحالية غير شاملة للضريبة.');
        }
        if (! $this->tenant->user()?->canAccessBranch($branch)) {
            throw new BusinessRuleException('Branch is outside your access scope.', status: 403);
        }
        if ($quotation?->exists && ($quotation->status !== 'draft' || $quotation->company_id !== $branch->company_id)) {
            throw new BusinessRuleException('Only a draft quotation in the current company can be edited.');
        }
        if (! empty($data['lead_id'])) {
            $lead = Lead::query()->whereKey($data['lead_id'])->where('company_id', $branch->company_id)
                ->where('branch_id', $branch->id)->firstOrFail();
            if (! $lead->converted_customer_id || $lead->converted_customer_id !== $customer->id) {
                throw new BusinessRuleException('Convert the lead to the selected customer before creating its quotation.');
            }
        }
        $items = collect($items)->map(fn (array $item) => $item + [
            'item_type' => 'product',
            'price_date' => $data['quotation_date'],
        ])->all();
        $historicalItems = $quotation?->exists
            ? $quotation->items()->where('item_type', '!=', 'product')->get()
            : collect();
        $calculated = $this->pricing->calculate($branch, $customer, $vehicle, $items, [
            'discount_type' => $data['discount_type'] ?? null,
            'discount_value' => $data['discount_value'] ?? 0,
            'currency_decimals' => $currency->decimal_places,
        ]);
        $calculated = $this->withHistoricalSnapshots($calculated, $historicalItems, $currency->decimal_places);

        return DB::transaction(function () use ($data, $calculated, $branch, $customer, $vehicle, $quotation, $historicalItems) {
            $quotation ??= new Quotation;
            $new = ! $quotation->exists;
            $quotation->fill(collect($data)->except(['branch_id'])->all())->forceFill([
                'company_id' => $branch->company_id, 'branch_id' => $branch->id,
                'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id,
                'quotation_number' => $quotation->quotation_number ?: $this->numbers->next(
                    'quotation', $branch->company_id, $branch->id, $data['quotation_date']
                ),
                'version_number' => $quotation->version_number ?: 1, 'status' => $quotation->status ?: 'draft',
                'subtotal' => $calculated['subtotal'], 'discount_amount' => $calculated['discount_amount'],
                'tax_amount' => $calculated['tax_amount'], 'total' => $calculated['total'],
                'estimated_material_cost' => $calculated['estimated_material_cost'],
                'estimated_waste_cost' => $calculated['estimated_waste_cost'],
                'estimated_total_cost' => $calculated['estimated_total_cost'],
                'estimated_margin' => $calculated['estimated_margin'],
                'created_by' => $quotation->created_by ?: $this->tenant->user()?->id,
            ])->save();
            $quotation->items()->where('item_type', 'product')->delete();
            $productSortOffset = (int) ($historicalItems->max('sort_order') ?? -1) + 1;
            foreach ($calculated['items'] as $row) {
                $materials = $row['materials'];
                unset($row['materials']);
                $row['sort_order'] += $productSortOffset;
                $item = $quotation->items()->create($row);
                $item->materials()->createMany($materials);
            }
            if ($quotation->lead_id) {
                $quotation->lead()->update(['status' => 'proposal_requested', 'converted_customer_id' => $customer->id]);
            }
            $this->audit->record($new ? 'quotation.created' : 'quotation.updated', $quotation);
            if ($new) {
                DB::afterCommit(fn () => event(new QuotationCreated($quotation->id)));
            }

            return $quotation->fresh(['items.materials']);
        });
    }

    private function withHistoricalSnapshots(array $calculated, Collection $historicalItems, int $decimals): array
    {
        if ($historicalItems->isEmpty()) {
            return $calculated;
        }

        $sum = fn (string $field): string => $historicalItems->reduce(
            fn (string $carry, $item) => bcadd($carry, (string) ($item->{$field} ?? 0), 8),
            '0'
        );
        $historicalHeaderDiscount = $historicalItems->reduce(
            fn (string $carry, $item) => bcadd(
                $carry,
                (string) data_get($item->metadata, 'header_discount_allocation', 0),
                8
            ),
            '0'
        );
        $round = fn (string $value): string => number_format((float) $value, $decimals, '.', '');

        $calculated['subtotal'] = $round(bcadd($calculated['subtotal'], $sum('net_amount'), 8));
        $calculated['discount_amount'] = $round(bcadd(
            $calculated['discount_amount'],
            $historicalHeaderDiscount,
            8
        ));
        $calculated['tax_amount'] = $round(bcadd($calculated['tax_amount'], $sum('tax_amount'), 8));
        $calculated['total'] = $round(bcadd($calculated['total'], $sum('total'), 8));

        foreach (['estimated_material_cost', 'estimated_waste_cost', 'estimated_total_cost'] as $field) {
            $historical = $historicalItems->pluck($field)->filter(fn ($value) => $value !== null);
            if ($historical->isNotEmpty()) {
                $calculated[$field] = $round(bcadd((string) ($calculated[$field] ?? 0), $sum($field), 8));
            }
        }
        $calculated['estimated_margin'] = $calculated['estimated_total_cost'] === null
            ? null
            : $round(bcsub($calculated['total'], $calculated['estimated_total_cost'], 8));

        return $calculated;
    }
}
