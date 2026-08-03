<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\SalesCreditNote;
use App\Models\SalesInvoiceItem;
use App\Models\SalesProductReturn;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class SalesProductReturnService
{
    public function __construct(
        private TenantContext $tenant,
        private SalesCreditNoteService $credits,
        private AuditService $audit
    ) {
    }

    public function return(
        SalesInvoiceItem $item,
        Warehouse $warehouse,
        string $quantity,
        string $reason,
        string $idempotencyKey
    ): SalesCreditNote {
        return DB::transaction(function () use ($item, $warehouse, $quantity, $reason, $idempotencyKey) {
            $item = SalesInvoiceItem::query()->whereKey($item->id)->lockForUpdate()->with(['invoice', 'product'])->firstOrFail();
            abort_unless($item->invoice->company_id === $this->tenant->companyId() && $this->tenant->user()->canAccessBranch($item->invoice->branch), 403);
            $existing = SalesProductReturn::query()
                ->where('company_id', $item->invoice->company_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                abort_unless($existing->sales_invoice_item_id === $item->id, 409);

                return $existing->creditNote;
            }
            $note = $this->credits->create($item->invoice, [
                'credit_note_date' => now()->toDateString(), 'reason_code' => 'product_return', 'reason' => $reason,
            ], [[
                'sales_invoice_item_id' => $item->id,
                'quantity' => $quantity,
                'return_to_stock' => true,
                'warehouse_id' => $warehouse->id,
                'idempotency_key' => $idempotencyKey,
            ]]);
            $this->audit->record('sales_product.return_requested', $note, ['invoice_item_id' => $item->id, 'quantity' => $quantity]);

            return $note;
        });
    }
}
