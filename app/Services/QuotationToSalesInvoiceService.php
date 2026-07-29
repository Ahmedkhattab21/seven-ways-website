<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Quotation;
use App\Models\SalesInvoice;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class QuotationToSalesInvoiceService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function convert(Quotation $quotation): SalesInvoice
    {
        return DB::transaction(function () use ($quotation) {
            $locked = Quotation::query()->whereKey($quotation->id)->lockForUpdate()
                ->with(['branch', 'customer.addresses', 'vehicle', 'items'])->firstOrFail();
            if ($locked->company_id !== $this->tenant->companyId()
                || ! $this->tenant->user()?->canAccessBranch($locked->branch)) {
                throw new BusinessRuleException('Quotation is outside your accessible branch.', status: 403);
            }

            $existing = SalesInvoice::query()->where('quotation_id', $locked->id)->first();
            if ($existing) {
                return $existing;
            }
            if (! in_array($locked->status, ['approved', 'sent', 'accepted'], true)) {
                throw new BusinessRuleException('Only an approved quotation can be converted.', status: 403);
            }

            $warehouse = Warehouse::query()->where('company_id', $locked->company_id)
                ->where('branch_id', $locked->branch_id)->where('is_active', true)
                ->where('is_system', false)->orderByDesc('is_main')->orderBy('id')->first();
            if ($locked->items->contains('item_type', 'product') && ! $warehouse) {
                throw new BusinessRuleException('An active sales warehouse is required for product items.');
            }

            $invoice = new SalesInvoice;
            $invoice->forceFill([
                'company_id' => $locked->company_id,
                'branch_id' => $locked->branch_id,
                'invoice_number' => $this->numbers->next(
                    'sales_invoice',
                    $locked->company_id,
                    $locked->branch_id,
                    now()->toDateString()
                ),
                'invoice_type' => 'quotation',
                'customer_id' => $locked->customer_id,
                'vehicle_id' => $locked->vehicle_id,
                'quotation_id' => $locked->id,
                'currency_id' => $locked->currency_id,
                'status' => 'draft',
                'invoice_date' => now()->toDateString(),
                'price_includes_tax' => $locked->price_includes_tax,
                'subtotal' => $locked->subtotal,
                'discount_type' => $locked->discount_type,
                'discount_value' => $locked->discount_value,
                'discount_amount' => $locked->discount_amount,
                'tax_amount' => $locked->tax_amount,
                'rounding_amount' => 0,
                'total' => $locked->total,
                'balance_due' => $locked->total,
                'customer_name_snapshot' => $locked->customer->name,
                'customer_tax_number_snapshot' => $locked->customer->tax_number,
                'customer_phone_snapshot' => $locked->customer->phone,
                'customer_address_snapshot' => $locked->customer->addresses->firstWhere('is_default', true)?->address_line,
                'vehicle_snapshot' => $locked->vehicle ? [
                    'plate_number' => $locked->vehicle->plate_number,
                    'vin' => $locked->vehicle->vin,
                    'color' => $locked->vehicle->color,
                ] : null,
                'terms_snapshot' => $locked->terms_and_conditions,
                'customer_notes' => $locked->customer_notes,
                'internal_notes' => $locked->internal_notes,
                'created_by' => $this->tenant->user()->id,
            ])->save();

            foreach ($locked->items as $source) {
                $item = $invoice->items()->make();
                $item->forceFill([
                    'item_type' => $source->item_type,
                    'quotation_item_id' => $source->id,
                    'service_id' => $source->service_id,
                    'service_package_id' => $source->service_package_id,
                    'product_id' => $source->product_id,
                    'warehouse_id' => $source->item_type === 'product' ? $warehouse?->id : null,
                    'description' => $source->description,
                    'quantity' => $source->quantity,
                    'unit_id' => $source->unit_id,
                    'unit_price' => $source->unit_price,
                    'gross_amount' => $source->gross_amount,
                    'discount_type' => $source->discount_type,
                    'discount_value' => $source->discount_value,
                    'discount_amount' => $source->discount_amount,
                    'net_amount' => $source->net_amount,
                    'tax_id' => $source->tax_id,
                    'tax_rate' => $source->tax_rate,
                    'tax_amount' => $source->tax_amount,
                    'total' => $source->total,
                    'cost_snapshot' => $source->estimated_total_cost,
                    'margin_snapshot' => $source->estimated_margin,
                    'promotion_id' => $source->promotion_id,
                    'sort_order' => $source->sort_order,
                    'metadata' => $source->metadata,
                ])->save();
            }

            $locked->forceFill(['status' => 'converted', 'converted_at' => now()])->save();
            $this->audit->record('quotation.converted_to_sales_invoice', $locked, ['sales_invoice_id' => $invoice->id]);

            return $invoice->load('items');
        });
    }
}
