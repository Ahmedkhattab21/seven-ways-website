<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\GoodsReceipt;
use App\Models\OpeningBalanceDocument;
use App\Models\PurchaseReturn;
use App\Models\SalesCreditNote;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class UnpostedAccountingSourcesService
{
    private const SOURCES = [
        SalesInvoice::class => ['issued', 'partially_paid', 'paid', 'overdue', 'credited'],
        SalesCreditNote::class => ['issued'],
        CustomerPayment::class => ['approved', 'partially_allocated', 'allocated'],
        CustomerRefund::class => ['processed'],
        SupplierInvoice::class => ['posted', 'partially_paid', 'paid', 'credited'],
        SupplierCreditNote::class => ['posted'],
        SupplierPayment::class => ['processed', 'partially_allocated', 'allocated'],
        GoodsReceipt::class => ['posted'],
        PurchaseReturn::class => ['posted'],
        OpeningBalanceDocument::class => ['ready_for_posting'],
        StockMovement::class => [],
    ];

    public function __construct(private TenantContext $tenant)
    {
    }

    public function report(array $filters = []): Collection
    {
        $companyId = $this->tenant->companyId();
        $rows = collect();
        foreach (self::SOURCES as $class => $statuses) {
            $query = $class::query()->where('company_id', $companyId)
                ->when($statuses !== [], fn ($q) => $q->whereIn('status', $statuses))
                ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
                ->when($filters['branch_ids'] ?? null, fn ($q, $ids) => $q->whereIn('branch_id', $ids))
                ->whereNotExists(function ($sub) use ($class) {
                    $sub->selectRaw('1')->from('accounting_posting_links as apl')
                        ->whereColumn('apl.source_id', (new $class)->getTable().'.id')
                        ->where('apl.source_type', $class)->whereIn('apl.status', ['posted', 'not_required']);
                })->latest('id')->limit(100);
            $rows = $rows->concat($query->get()->map(fn (Model $model) => [
                'source_type' => $class, 'source_id' => $model->id, 'source_uuid' => $model->uuid,
                'source_number' => $this->number($model), 'date' => $this->date($model),
                'branch_id' => $model->branch_id ?? null, 'amount' => $model->total ?? $model->amount ?? $model->total_cost ?? null,
                'reason' => 'Eligible source has no posted accounting link.',
            ]));
        }

        return $rows->sortByDesc('date')->values();
    }

    public function count(array $filters = []): int
    {
        $companyId = $this->tenant->companyId();

        return collect(self::SOURCES)->sum(function (array $statuses, string $class) use ($companyId, $filters) {
            return $class::query()->where('company_id', $companyId)
                ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
                ->when($filters['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id))
                ->when($filters['branch_ids'] ?? null, fn ($query, $ids) => $query->whereIn('branch_id', $ids))
                ->whereNotExists(function ($sub) use ($class) {
                    $sub->selectRaw('1')->from('accounting_posting_links as apl')
                        ->whereColumn('apl.source_id', (new $class)->getTable().'.id')
                        ->where('apl.source_type', $class)->whereIn('apl.status', ['posted', 'not_required']);
                })->count();
        });
    }

    private function number(Model $model): string
    {
        foreach (['invoice_number', 'credit_note_number', 'payment_number', 'refund_number', 'internal_invoice_number',
            'goods_receipt_number', 'purchase_return_number', 'document_number', 'movement_number'] as $field) {
            if ($model->{$field} ?? null) {
                return $model->{$field};
            }
        }

        return (string) $model->id;
    }

    private function date(Model $model): ?string
    {
        foreach (['invoice_date', 'credit_note_date', 'payment_date', 'refund_date', 'receipt_date',
            'return_date', 'balance_date', 'occurred_at'] as $field) {
            if ($model->{$field} ?? null) {
                return $model->{$field}->toDateString();
            }
        }

        return null;
    }
}
