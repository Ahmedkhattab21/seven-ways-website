<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\SalesInvoiceCreated;
use App\Models\SalesInvoice;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class WorkOrderToInvoiceService
{
    public function __construct(
        private TenantContext $tenant,
        private SalesInvoicePricingService $pricing,
        private SalesInvoiceService $invoices,
        private AuditService $audit
    ) {
    }

    public function create(WorkOrder $order, array $data = []): SalesInvoice
    {
        return DB::transaction(function () use ($order, $data) {
            $order = WorkOrder::query()->whereKey($order->id)->lockForUpdate()->with(['services.service.defaultTax', 'customer', 'vehicle', 'quotation.currency', 'reworkOrders'])->firstOrFail();
            abort_unless($order->company_id === $this->tenant->companyId() && $this->tenant->user()->canAccessBranch($order->branch), 403);
            if ($order->status !== 'delivered') {
                throw new BusinessRuleException('Only a delivered work order can be invoiced.');
            }
            if (SalesInvoice::where('work_order_id', $order->id)->whereNotIn('status', ['draft', 'cancelled', 'void'])->exists()) {
                throw new BusinessRuleException('This work order already has a final invoice.');
            }
            $rows = $order->services->where('status', 'completed')->map(fn ($line) => [
                'item_type' => 'service', 'work_order_service_id' => $line->id, 'service_id' => $line->service_id,
                'service_package_id' => $line->service_package_id, 'description' => $line->description,
                'quantity' => $line->quantity, 'unit_price' => $line->unit_price_snapshot,
                'tax_id' => $line->service?->default_tax_id, 'tax_rate' => $line->service?->defaultTax?->rate ?? 0,
                'cost_snapshot' => $line->actual_total_cost,
            ])->values();
            $order->reworkOrders->where('is_customer_chargeable', true)->where('customer_charge_amount', '>', 0)->each(
                fn ($rework) => $rows->push([
                    'item_type' => 'custom', 'description' => "Rework {$rework->rework_number}",
                    'quantity' => 1, 'unit_price' => $rework->customer_charge_amount, 'tax_rate' => 0,
                    'metadata' => ['rework_order_id' => $rework->id],
                ])
            );
            $currency = $order->quotation?->currency ?? $this->tenant->company()->currency;
            $totals = $this->pricing->calculate($rows->all(), $data, $currency->decimal_places);
            $invoice = new SalesInvoice($data);
            $invoice->forceFill(array_merge(
                $this->invoices->header($order->customer, $order->vehicle, $currency->id, 'work_order', $totals, $data),
                ['work_order_id' => $order->id, 'quotation_id' => $order->quotation_id, 'branch_id' => $order->branch_id]
            ))->save();
            foreach ($totals['items'] as $row) {
                $item = $invoice->items()->make();
                $item->forceFill($row)->save();
            }
            $this->audit->record('sales_invoice.created', $invoice, ['work_order_id' => $order->id]);
            DB::afterCommit(fn () => event(new SalesInvoiceCreated($invoice->id)));

            return $invoice->load('items');
        });
    }
}
