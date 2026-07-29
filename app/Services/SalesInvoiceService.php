<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\SalesInvoiceCreated;
use App\Models\BranchServicePackage;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesInvoiceService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private SalesInvoicePricingService $pricing,
        private ServicePricingService $servicePricing,
        private AuditService $audit
    ) {
    }

    public function createDirect(array $data, array $items): SalesInvoice
    {
        return DB::transaction(function () use ($data, $items) {
            $customer = Customer::whereKey($data['customer_id'])->where('company_id', $this->tenant->companyId())->firstOrFail();
            $vehicle = empty($data['vehicle_id']) ? null : Vehicle::whereKey($data['vehicle_id'])
                ->where('company_id', $customer->company_id)->where('customer_id', $customer->id)->firstOrFail();
            $currency = $this->tenant->company()->currency;
            $branch = $this->tenant->branch();
            $snapshots = collect($items)->map(function (array $item) use ($branch, $vehicle, $data) {
                $type = $item['item_type'];
                $product = $type === 'product' ? Product::whereKey($item['product_id'])
                    ->where('company_id', $this->tenant->companyId())->where('is_sellable', true)
                    ->whereNotIn('tracking_type', ['roll', 'scrap'])->firstOrFail() : null;
                $warehouse = $product ? Warehouse::whereKey($item['warehouse_id'])
                    ->where('company_id', $this->tenant->companyId())->where('branch_id', $this->tenant->branchId())
                    ->where('is_active', true)->where('is_system', false)->firstOrFail() : null;
                if (! in_array($type, ['service', 'package', 'product', 'custom'], true)) {
                    throw new BusinessRuleException('Unsupported direct-sale item.');
                }
                $service = $type === 'service'
                    ? Service::query()->whereKey($item['service_id'])->where('company_id', $this->tenant->companyId())
                        ->where('is_active', true)->with('defaultTax')->firstOrFail()
                    : null;
                $package = $type === 'package'
                    ? ServicePackage::query()->whereKey($item['service_package_id'])
                        ->where('company_id', $this->tenant->companyId())->where('is_active', true)
                        ->with('items.service.defaultTax')->firstOrFail()
                    : null;
                $resolvedService = $service ? $this->servicePricing->resolvePrice(
                    $service,
                    $branch,
                    $vehicle?->size,
                    $vehicle?->type,
                    (string) $item['quantity'],
                    $data['invoice_date'] ?? now()
                ) : null;
                $packagePrice = $package ? $this->packagePrice(
                    $package,
                    $branch->id,
                    $vehicle?->vehicle_size_id,
                    $data['invoice_date'] ?? now()
                ) : null;
                $tax = $product?->defaultTax ?? $service?->defaultTax;
                $packageTax = $package
                    ? $package->items->pluck('service.defaultTax')->filter()
                        ->unique(fn ($value) => $value->id.'|'.$value->rate)
                    : collect();
                if ($packageTax->count() === 1) {
                    $tax = $packageTax->first();
                }

                return array_merge($item, [
                    'service_id' => $service?->id,
                    'service_package_id' => $package?->id,
                    'product_id' => $product?->id, 'warehouse_id' => $warehouse?->id,
                    'description' => trim($item['description'] ?? '') ?: ($product?->name ?? $service?->name ?? $package?->name),
                    'unit_id' => $item['unit_id'] ?? $product?->sale_unit_id,
                    'unit_price' => $item['unit_price'] ?? $product?->default_sale_price
                        ?? $resolvedService['unit_price'] ?? $packagePrice?->price,
                    'tax_id' => $tax?->id ?? ($item['tax_id'] ?? null),
                    'tax_rate' => $tax?->rate ?? ($item['tax_rate'] ?? 0),
                ]);
            })->all();
            $calculated = $this->pricing->calculate($snapshots, $data, $currency->decimal_places);
            $invoice = new SalesInvoice($data);
            $invoice->forceFill($this->header($customer, $vehicle, $currency->id, 'direct_sale', $calculated, $data))->save();
            foreach ($calculated['items'] as $row) {
                $item = $invoice->items()->make();
                $item->forceFill($row)->save();
            }
            $this->audit->record('sales_invoice.created', $invoice, ['type' => 'direct_sale']);
            DB::afterCommit(fn () => event(new SalesInvoiceCreated($invoice->id)));

            return $invoice->load('items');
        });
    }

    private function packagePrice(ServicePackage $package, int $branchId, ?int $vehicleSizeId, mixed $date): BranchServicePackage
    {
        $date = Carbon::parse($date)->toDateString();

        $price = BranchServicePackage::query()
            ->where('branch_id', $branchId)
            ->where('service_package_id', $package->id)
            ->where('is_available', true)
            ->where(fn ($query) => $vehicleSizeId
                ? $query->whereNull('vehicle_size_id')->orWhere('vehicle_size_id', $vehicleSizeId)
                : $query->whereNull('vehicle_size_id'))
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByRaw('vehicle_size_id IS NOT NULL DESC')
            ->latest('effective_from')
            ->first();

        if (! $price) {
            throw new BusinessRuleException('Package is not available for this branch and vehicle.');
        }

        return $price;
    }

    public function header(Customer $customer, ?Vehicle $vehicle, int $currencyId, string $type, array $totals, array $data): array
    {
        $date = $data['invoice_date'] ?? now()->toDateString();
        if (! empty($data['due_date']) && $data['due_date'] < $date) {
            throw new BusinessRuleException('Due date cannot precede invoice date.');
        }

        return [
            'company_id' => $customer->company_id, 'branch_id' => $this->tenant->branchId(),
            'invoice_number' => $this->numbers->next('sales_invoice', $customer->company_id, $this->tenant->branchId(), $date),
            'invoice_type' => $type, 'customer_id' => $customer->id, 'vehicle_id' => $vehicle?->id,
            'currency_id' => $currencyId, 'status' => 'draft', 'invoice_date' => $date,
            'due_date' => $data['due_date'] ?? null, 'price_includes_tax' => false,
            'subtotal' => $totals['subtotal'], 'discount_type' => $data['discount_type'] ?? null,
            'discount_value' => $data['discount_value'] ?? 0, 'discount_amount' => $totals['discount_amount'],
            'tax_amount' => $totals['tax_amount'], 'rounding_amount' => $totals['rounding_amount'],
            'total' => $totals['total'], 'balance_due' => $totals['total'],
            'customer_name_snapshot' => $customer->name, 'customer_tax_number_snapshot' => $customer->tax_number,
            'customer_phone_snapshot' => $customer->phone,
            'customer_address_snapshot' => $customer->addresses()->where('is_default', true)->value('address_line'),
            'vehicle_snapshot' => $vehicle ? ['plate_number' => $vehicle->plate_number, 'vin' => $vehicle->vin, 'color' => $vehicle->color] : null,
            'terms_snapshot' => $data['terms_snapshot'] ?? null, 'customer_notes' => $data['customer_notes'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null, 'created_by' => $this->tenant->user()->id,
        ];
    }
}
