<?php

namespace App\Http\Controllers;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Http\Requests\AccountingPostingPreviewRequest;
use App\Http\Requests\AccountingPostingRequest;
use App\Http\Requests\JournalEntryReversalRequest;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\GoodsReceipt;
use App\Models\PurchaseReturn;
use App\Models\SalesCreditNote;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Services\AccountingPostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountingPostingController extends Controller
{
    private const SOURCES = [
        'sales-invoice' => SalesInvoice::class,
        'sales-credit-note' => SalesCreditNote::class,
        'customer-payment' => CustomerPayment::class,
        'customer-refund' => CustomerRefund::class,
        'supplier-invoice' => SupplierInvoice::class,
        'supplier-credit-note' => SupplierCreditNote::class,
        'supplier-payment' => SupplierPayment::class,
        'goods-receipt' => GoodsReceipt::class,
        'purchase-return' => PurchaseReturn::class,
        'stock-movement' => StockMovement::class,
        'stock-transfer' => StockTransfer::class,
    ];

    public function __construct(private TenantContext $tenant, private AccountingPostingService $service)
    {
    }

    public function index(): View
    {
        abort_unless($this->tenant->user()->hasPermission('accounting.posting.execute'), 403);

        return view('accounting.posting.index', ['sourceTypes' => array_keys(self::SOURCES)]);
    }

    public function preview(AccountingPostingPreviewRequest $request, string $sourceType, string $sourceUuid): JsonResponse
    {
        abort_unless($this->tenant->user()->hasPermission('accounting.posting.preview'), 403);

        return response()->json($this->service->preview($this->source($sourceType, $sourceUuid), $request->validated()));
    }

    public function post(AccountingPostingRequest $request, string $sourceType, string $sourceUuid): RedirectResponse
    {
        abort_unless($this->tenant->user()->hasPermission('accounting.posting.execute'), 403);
        $entry = $this->service->post($this->source($sourceType, $sourceUuid), $request->validated());

        return $entry
            ? redirect()->route('accounting.journals.show', $entry)->with('success', 'Source posted.')
            : back()->with('success', 'No accounting journal is required for this source.');
    }

    public function reverse(JournalEntryReversalRequest $request, string $sourceType, string $sourceUuid): RedirectResponse
    {
        abort_unless($this->tenant->user()->hasPermission('accounting.posting.reverse'), 403);
        $entry = $this->service->reverse(
            $this->source($sourceType, $sourceUuid), (string) $request->string('reason'), $request->input('posting_date')
        );

        return redirect()->route('accounting.journals.show', $entry)->with('success', 'Source accounting reversed.');
    }

    private function source(string $sourceType, string $uuid): Model
    {
        $class = self::SOURCES[$sourceType] ?? throw new BusinessRuleException('Unsupported accounting source type.');

        return $class::query()->where('company_id', $this->tenant->companyId())->where('uuid', $uuid)->firstOrFail();
    }
}
