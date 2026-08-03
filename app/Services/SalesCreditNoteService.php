<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\CreditNoteIssued;
use App\Models\SalesCreditNote;
use App\Models\SalesCreditNoteItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesProductReturn;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesCreditNoteService
{
    private const FINAL_STATUSES = ['issued', 'partially_applied', 'applied', 'refunded'];

    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private MoneyRoundingService $rounding,
        private AuditService $audit,
        private SalesInvoiceBalanceService $balances,
        private SalesInvoiceInventoryService $inventory
    ) {
    }

    public function create(SalesInvoice $invoice, array $data, array $items): SalesCreditNote
    {
        return DB::transaction(function () use ($invoice, $data, $items) {
            $invoice = SalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_unless($invoice->company_id === $this->tenant->companyId() && $this->tenant->user()->canAccessBranch($invoice->branch), 403);
            if (! in_array($invoice->status, ['issued', 'partially_paid', 'paid', 'overdue'], true)) {
                throw new BusinessRuleException('لا يمكن إصدار إشعار دائن إلا لفاتورة نهائية.');
            }

            $note = new SalesCreditNote($data);
            $note->forceFill([
                'company_id' => $invoice->company_id,
                'branch_id' => $invoice->branch_id,
                'credit_note_number' => $this->numbers->next('sales_credit_note', $invoice->company_id, $invoice->branch_id, $data['credit_note_date']),
                'sales_invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'currency_id' => $invoice->currency_id,
                'status' => 'draft',
                'created_by' => $this->tenant->user()->id,
            ])->save();

            $subtotal = $tax = $total = '0.0000';
            foreach ($items as $input) {
                $source = SalesInvoiceItem::query()
                    ->where('sales_invoice_id', $invoice->id)
                    ->whereKey($input['sales_invoice_item_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $quantity = (string) $input['quantity'];
                $alreadyCredited = $this->issuedCreditQuantity($source->id);
                if (bccomp($quantity, '0', 6) !== 1 || bccomp(bcadd($alreadyCredited, $quantity, 6), (string) $source->quantity, 6) === 1) {
                    throw new BusinessRuleException('الكمية المطلوبة تتجاوز الكمية المتبقية المسموح بإصدار إشعار دائن لها.');
                }

                $returnToStock = filter_var($input['return_to_stock'] ?? false, FILTER_VALIDATE_BOOL);
                $warehouse = null;
                if ($returnToStock) {
                    if ($source->item_type !== 'product' || ! $source->product_id || ! $source->issued_movement_id) {
                        throw new BusinessRuleException('لا يمكن إنشاء مرتجع مخزني إلا لبند منتج تم صرفه من المخزون.');
                    }
                    $warehouse = $this->eligibleWarehouse((int) ($input['warehouse_id'] ?? 0), $invoice);
                    $remainingPhysical = bcsub((string) $source->quantity, (string) $source->returned_quantity, 6);
                    if (bccomp($quantity, $remainingPhysical, 6) === 1) {
                        throw new BusinessRuleException('الكمية المرتجعة تتجاوز الكمية المتبقية التي يمكن إعادتها للمخزون.');
                    }
                }

                $net = $this->rounding->round(bcmul($quantity, (string) $source->unit_price, 8), 2);
                $itemTax = $this->rounding->round(bcdiv(bcmul($net, (string) $source->tax_rate, 8), '100', 8), 2);
                $itemTotal = $this->rounding->round(bcadd($net, $itemTax, 8), 2);
                $creditItem = $note->items()->make();
                $creditItem->forceFill([
                    'sales_invoice_item_id' => $source->id,
                    'description' => $source->description,
                    'quantity' => $quantity,
                    'unit_price' => $source->unit_price,
                    'net_amount' => $net,
                    'tax_rate' => $source->tax_rate,
                    'tax_amount' => $itemTax,
                    'total' => $itemTotal,
                ])->save();

                if ($warehouse) {
                    $return = new SalesProductReturn([
                        'idempotency_key' => $input['idempotency_key'] ?? (string) Str::uuid(),
                        'sales_invoice_item_id' => $source->id,
                        'warehouse_id' => $warehouse->id,
                        'quantity' => $quantity,
                        'reason' => $data['reason'],
                        'sales_credit_note_id' => $note->id,
                    ]);
                    $return->forceFill([
                        'company_id' => $invoice->company_id,
                        'created_by' => $this->tenant->user()->id,
                    ])->save();
                }

                $subtotal = bcadd($subtotal, $net, 4);
                $tax = bcadd($tax, $itemTax, 4);
                $total = bcadd($total, $itemTotal, 4);
            }

            if (bccomp($total, bcsub((string) $invoice->total, (string) $invoice->credited_amount, 4), 4) === 1) {
                throw new BusinessRuleException('قيمة الإشعار الدائن تتجاوز الرصيد المتبقي للفاتورة.');
            }
            $note->forceFill([
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total' => $total,
                'remaining_amount' => $total,
            ])->save();

            return $note->load(['items', 'productReturns']);
        });
    }

    public function approve(SalesCreditNote $note): SalesCreditNote
    {
        return $this->transition($note, 'draft', 'approved', 'approved');
    }

    public function issue(SalesCreditNote $note): SalesCreditNote
    {
        return DB::transaction(function () use ($note) {
            $note = SalesCreditNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            if ($note->status === 'issued') {
                return $note->load(['items', 'productReturns.stockMovement']);
            }
            if ($note->status !== 'approved') {
                throw new BusinessRuleException('لا يمكن إصدار إلا إشعار دائن معتمد.');
            }

            $invoice = SalesInvoice::query()->whereKey($note->sales_invoice_id)->lockForUpdate()->firstOrFail();
            abort_unless($invoice->company_id === $this->tenant->companyId() && $this->tenant->user()->canAccessBranch($invoice->branch), 403);
            $issuedTotal = (string) SalesCreditNote::query()
                ->where('sales_invoice_id', $invoice->id)
                ->where('id', '!=', $note->id)
                ->whereIn('status', self::FINAL_STATUSES)
                ->sum('total');
            if (bccomp(bcadd($issuedTotal, (string) $note->total, 4), (string) $invoice->total, 4) === 1) {
                throw new BusinessRuleException('قيمة الإشعار الدائن تتجاوز الرصيد المتبقي للفاتورة.');
            }

            $creditItems = SalesCreditNoteItem::query()
                ->where('sales_credit_note_id', $note->id)
                ->lockForUpdate()
                ->get();
            foreach ($creditItems as $creditItem) {
                $source = SalesInvoiceItem::query()->whereKey($creditItem->sales_invoice_item_id)->lockForUpdate()->firstOrFail();
                $alreadyIssued = $this->issuedCreditQuantity($source->id, $note->id);
                if (bccomp(bcadd($alreadyIssued, (string) $creditItem->quantity, 6), (string) $source->quantity, 6) === 1) {
                    throw new BusinessRuleException('الكمية المطلوبة تتجاوز الكمية المتبقية المسموح بإصدار إشعار دائن لها.');
                }

                $return = SalesProductReturn::query()
                    ->where('sales_credit_note_id', $note->id)
                    ->where('sales_invoice_item_id', $source->id)
                    ->lockForUpdate()
                    ->first();
                if (! $return || $return->stock_movement_id) {
                    continue;
                }

                $warehouse = $this->eligibleWarehouse($return->warehouse_id, $invoice);
                $movement = $this->inventory->return($source, (string) $return->quantity, $warehouse, [
                    'type' => 'sales_product_return',
                    'id' => $return->id,
                    'notes' => "credit_note:{$note->id}",
                ]);
                $return->forceFill([
                    'stock_movement_id' => $movement->id,
                    'processed_at' => now(),
                ])->save();
            }

            $note->forceFill([
                'status' => 'issued',
                'issued_by' => $this->tenant->user()->id,
                'issued_at' => now(),
            ])->save();
            $this->balances->rebuild($invoice);
            $this->audit->record('credit_note.issued', $note);
            DB::afterCommit(fn () => event(new CreditNoteIssued($note->id)));

            return $note->load(['items', 'productReturns.stockMovement']);
        });
    }

    public function reconcileIssuedReturn(SalesCreditNote $note, Warehouse $warehouse): int
    {
        return DB::transaction(function () use ($note, $warehouse) {
            $note = SalesCreditNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            if ($note->status !== 'issued') {
                throw new BusinessRuleException('يمكن إصلاح المرتجع المخزني لإشعار صادر فقط.');
            }
            $invoice = SalesInvoice::query()->whereKey($note->sales_invoice_id)->lockForUpdate()->firstOrFail();
            abort_unless($invoice->company_id === $this->tenant->companyId() && $this->tenant->user()->canAccessBranch($invoice->branch), 403);
            $warehouse = $this->eligibleWarehouse($warehouse->id, $invoice);
            $processed = 0;

            $creditItems = SalesCreditNoteItem::query()
                ->where('sales_credit_note_id', $note->id)
                ->lockForUpdate()
                ->get();
            foreach ($creditItems as $creditItem) {
                $source = SalesInvoiceItem::query()->whereKey($creditItem->sales_invoice_item_id)->lockForUpdate()->firstOrFail();
                if ($source->item_type !== 'product' || ! $source->issued_movement_id) {
                    continue;
                }
                $return = SalesProductReturn::query()
                    ->where('sales_credit_note_id', $note->id)
                    ->where('sales_invoice_item_id', $source->id)
                    ->lockForUpdate()
                    ->first();
                if ($return?->stock_movement_id) {
                    continue;
                }
                if (! $return && bccomp((string) $source->returned_quantity, '0', 6) === 1) {
                    throw new BusinessRuleException('يوجد مرتجع مخزني سابق غير مربوط بهذا الإشعار؛ يلزم مراجعته يدويًا قبل الإصلاح.');
                }
                $remaining = bcsub((string) $source->quantity, (string) $source->returned_quantity, 6);
                if (bccomp((string) $creditItem->quantity, $remaining, 6) === 1) {
                    throw new BusinessRuleException('كمية الإشعار تتجاوز الكمية المتاحة للإرجاع للمخزون.');
                }
                if (! $return) {
                    $return = new SalesProductReturn([
                        'idempotency_key' => (string) Str::uuid(),
                        'sales_invoice_item_id' => $source->id,
                        'warehouse_id' => $warehouse->id,
                        'quantity' => $creditItem->quantity,
                        'reason' => $note->reason,
                        'sales_credit_note_id' => $note->id,
                    ]);
                    $return->forceFill([
                        'company_id' => $invoice->company_id,
                        'created_by' => $this->tenant->user()->id,
                    ])->save();
                }
                $movement = $this->inventory->return($source, (string) $return->quantity, $warehouse, [
                    'type' => 'sales_product_return',
                    'id' => $return->id,
                    'notes' => "reconciled_credit_note:{$note->id}",
                ]);
                $return->forceFill(['stock_movement_id' => $movement->id, 'processed_at' => now()])->save();
                $processed++;
            }

            if ($processed > 0) {
                $this->audit->record('credit_note.stock_return_reconciled', $note, ['processed_items' => $processed]);
            }

            return $processed;
        });
    }

    private function transition(SalesCreditNote $note, string $from, string $to, string $action): SalesCreditNote
    {
        return DB::transaction(function () use ($note, $from, $to, $action) {
            $note = SalesCreditNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            abort_unless($note->company_id === $this->tenant->companyId() && $this->tenant->user()->canAccessBranch($note->invoice->branch), 403);
            if ($note->status !== $from) {
                throw new BusinessRuleException("يجب أن تكون حالة الإشعار {$from}.");
            }
            $note->forceFill([
                'status' => $to,
                "{$action}_by" => $this->tenant->user()->id,
                "{$action}_at" => now(),
            ])->save();

            return $note;
        });
    }

    private function issuedCreditQuantity(int $invoiceItemId, ?int $exceptNoteId = null): string
    {
        return (string) SalesCreditNoteItem::query()
            ->where('sales_invoice_item_id', $invoiceItemId)
            ->whereHas('creditNote', fn ($query) => $query
                ->when($exceptNoteId, fn ($noteQuery) => $noteQuery->where('id', '!=', $exceptNoteId))
                ->whereIn('status', self::FINAL_STATUSES))
            ->sum('quantity');
    }

    private function eligibleWarehouse(int $warehouseId, SalesInvoice $invoice): Warehouse
    {
        $warehouse = Warehouse::query()
            ->whereKey($warehouseId)
            ->where('company_id', $invoice->company_id)
            ->where('branch_id', $invoice->branch_id)
            ->where('is_active', true)
            ->where('is_system', false)
            ->where('warehouse_type', '!=', 'transit')
            ->first();
        if (! $warehouse) {
            throw new BusinessRuleException('مخزن الاستلام غير صالح أو لا يتبع فرع الفاتورة.', status: 422);
        }

        return $warehouse;
    }
}
