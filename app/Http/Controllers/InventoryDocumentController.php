<?php

namespace App\Http\Controllers;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockOpeningDocument;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryDocumentController extends Controller
{
    public function create(string $section, TenantContext $tenant): View
    {
        $this->assertSection($section);

        return view('inventory.document-form', [
            'section' => $section,
            'warehouses' => Warehouse::where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
                ->where('is_active', true)->where('is_system', false)->get(),
            'products' => Product::where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $section, TenantContext $tenant, DocumentNumberService $numbers): RedirectResponse
    {
        $this->assertSection($section);
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer'], 'date' => ['required', 'date'],
            'notes' => ['nullable', 'string'], 'product_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'numeric', 'gt:0'], 'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'roll_number' => ['nullable', 'string', 'max:255'], 'roll_width' => ['nullable', 'numeric', 'gt:0'],
            'roll_length' => ['nullable', 'numeric', 'gt:0'],
            'adjustment_type' => ['nullable', Rule::in(['increase', 'decrease', 'damage', 'expiry', 'correction'])],
            'reason' => ['nullable', 'string', 'max:255'], 'scope_type' => ['nullable', Rule::in(['full', 'category'])],
        ]);
        $warehouse = Warehouse::query()->whereKey($data['warehouse_id'])->where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
            ->where('is_active', true)->where('is_system', false)->firstOrFail();

        $document = DB::transaction(function () use ($data, $section, $tenant, $numbers, $warehouse) {
            $common = [
                'company_id' => $warehouse->company_id, 'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id, 'status' => 'draft', 'notes' => $data['notes'] ?? null,
                'created_by' => $tenant->user()->id,
            ];
            if ($section === 'openings') {
                $document = StockOpeningDocument::query()->forceCreate($common + [
                    'document_number' => $numbers->next('stock_opening', $warehouse->company_id, $warehouse->branch_id),
                    'opening_date' => $data['date'],
                ]);
                $this->createLine($document, $data);

                return $document;
            }
            if ($section === 'adjustments') {
                $product = Product::query()->whereKey($data['product_id'] ?? 0)
                    ->where('company_id', $warehouse->company_id)->firstOrFail();
                $document = StockAdjustment::query()->forceCreate($common + [
                    'document_number' => $numbers->next('stock_adjustment', $warehouse->company_id, $warehouse->branch_id),
                    'adjustment_date' => $data['date'], 'adjustment_type' => $data['adjustment_type'] ?? 'correction',
                    'reason' => $data['reason'] ?? 'Manual adjustment',
                ]);
                if (empty($data['product_id']) || empty($data['quantity'])) {
                    throw new BusinessRuleException('Adjustment requires a product and quantity.');
                }
                $document->items()->create([
                    'product_id' => $product->id, 'quantity' => $data['quantity'],
                    'unit_cost' => $data['unit_cost'] ?? null, 'notes' => $data['notes'] ?? null,
                ]);

                return $document;
            }

            return InventoryCount::query()->forceCreate($common + [
                'document_number' => $numbers->next('inventory_count', $warehouse->company_id, $warehouse->branch_id),
                'count_date' => $data['date'], 'scope_type' => $data['scope_type'] ?? 'full',
            ]);
        });

        return redirect()->route('inventory.index', $section)->with('success', "تم إنشاء المسودة {$document->document_number}.");
    }

    public function showCount(InventoryCount $count): View
    {
        $this->authorize('view', $count);

        return view('inventory.count', [
            'count' => $count->load(['branch', 'warehouse', 'items.product']),
        ]);
    }

    public function showOpening(StockOpeningDocument $opening, TenantContext $tenant): View
    {
        abort_unless($tenant->user()->hasPermission('inventory.opening'), 403);
        abort_unless(
            $opening->company_id === $tenant->companyId()
            && $tenant->accessibleBranches()->contains('id', $opening->branch_id),
            404
        );

        return view('inventory.opening', [
            'opening' => $opening->load(['warehouse', 'items.product']),
        ]);
    }

    private function createLine(StockOpeningDocument $document, array $data): void
    {
        if (empty($data['product_id'])) {
            throw new BusinessRuleException('Opening balance requires a product.');
        }
        $product = Product::query()->whereKey($data['product_id'])->where('company_id', $document->company_id)->firstOrFail();
        if ($product->tracking_type === 'roll' && (empty($data['roll_width']) || empty($data['roll_length']))) {
            throw new BusinessRuleException('Roll opening balance requires actual dimensions.');
        }
        $document->items()->create([
            'product_id' => $product->id, 'quantity' => $data['quantity'] ?? ($product->tracking_type === 'roll' ? 1 : 0),
            'unit_cost' => $data['unit_cost'] ?? 0, 'roll_number' => $data['roll_number'] ?? null,
            'roll_width' => $data['roll_width'] ?? null, 'roll_length' => $data['roll_length'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    private function assertSection(string $section): void
    {
        abort_unless(in_array($section, ['openings', 'adjustments', 'counts'], true), 404);
        $permission = match ($section) {
            'openings' => 'inventory.opening', 'adjustments' => 'inventory.adjust', 'counts' => 'inventory.count',
        };
        abort_unless(auth()->user()->hasPermission($permission), 403);
    }
}
