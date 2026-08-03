<?php

namespace App\Console\Commands;

use App\Core\Tenancy\TenantContext;
use App\Models\SalesCreditNote;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesCreditNoteService;
use Illuminate\Console\Command;

class ReconcileSalesCreditReturn extends Command
{
    protected $signature = 'sales:reconcile-credit-return {creditNoteId} {--warehouse=} {--force}';

    protected $description = 'Safely reconcile stock for an already issued product credit note';

    public function handle(TenantContext $tenant, SalesCreditNoteService $service): int
    {
        $identifier = $this->argument('creditNoteId');
        $note = SalesCreditNote::query()
            ->where(fn ($query) => $query->whereKey($identifier)->orWhere('credit_note_number', $identifier))
            ->with(['invoice.branch', 'items.invoiceItem'])
            ->firstOrFail();
        if ($note->status !== 'issued') {
            $this->error('يمكن إصلاح المرتجع المخزني لإشعار صادر فقط.');

            return self::FAILURE;
        }
        $warehouse = Warehouse::query()
            ->whereKey($this->option('warehouse'))
            ->where('company_id', $note->company_id)
            ->where('branch_id', $note->branch_id)
            ->where('is_active', true)
            ->where('is_system', false)
            ->where('warehouse_type', '!=', 'transit')
            ->first();
        if (! $warehouse) {
            $this->error('حدد مخزن استلام نشطًا يتبع نفس فرع الإشعار باستخدام --warehouse.');

            return self::FAILURE;
        }

        $productItems = $note->items->filter(fn ($item) => $item->invoiceItem?->item_type === 'product');
        if ($productItems->isEmpty()) {
            $this->error('الإشعار لا يحتوي على بنود منتجات قابلة للإرجاع.');

            return self::FAILURE;
        }
        $this->table(['البند', 'الكمية', 'المخزن'], $productItems->map(fn ($item) => [
            $item->description,
            $item->quantity,
            "{$warehouse->code} — {$warehouse->name}",
        ]));
        if (! $this->option('force')) {
            $this->warn('Dry run فقط. أعد التنفيذ مع --force بعد مراجعة البنود.');

            return self::SUCCESS;
        }

        $actor = User::query()->find($note->issued_by ?? $note->created_by);
        if (! $actor || ! $actor->isActive()) {
            $this->error('تعذر تحديد مستخدم نشط لتنفيذ الإصلاح وتسجيله في سجل التدقيق.');

            return self::FAILURE;
        }
        $tenant->initialize($actor);
        $tenant->switchTo($note->invoice->branch);
        $processed = $service->reconcileIssuedReturn($note, $warehouse);
        $this->info($processed > 0
            ? "تم إنشاء {$processed} حركة مرتجع مخزني بنجاح."
            : 'لا توجد حركات ناقصة؛ لم يتم إنشاء أي حركة مكررة.');

        return self::SUCCESS;
    }
}
