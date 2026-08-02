<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\InventoryCount;
use App\Models\InventoryReservation;
use App\Models\InventoryRoll;
use App\Models\RollScrap;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockOpeningDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __invoke(Request $request, string $section, TenantContext $tenant): View
    {
        abort_unless(array_key_exists($section, $this->sections()), 404);
        abort_unless($tenant->user()->hasPermission(
            $section === 'openings' ? 'inventory.opening' : 'inventory.view'
        ), 403);
        $branchIds = $tenant->accessibleBranches()->pluck('id');
        $model = $this->sections()[$section];
        $query = $model::query()->where('company_id', $tenant->companyId())->whereIn('branch_id', $branchIds);
        $this->eagerLoad($query, $section);
        if ($section === 'alerts') {
            $query->whereColumn('available_quantity', '<=', 'products.minimum_stock')->join('products', 'products.id', '=', 'stock_balances.product_id')->select('stock_balances.*');
        }
        $records = $query->latest($section === 'movements' ? 'occurred_at' : ($section === 'alerts' ? 'stock_balances.id' : 'id'))->paginate(25);
        $alertSummary = $section === 'alerts' ? [
            'low_products' => $records->total(),
            'low_rolls' => InventoryRoll::query()->where('inventory_rolls.company_id', $tenant->companyId())->whereIn('inventory_rolls.branch_id', $branchIds)
                ->join('film_product_profiles', 'film_product_profiles.product_id', '=', 'inventory_rolls.product_id')
                ->whereColumn('inventory_rolls.remaining_area', '<=', 'film_product_profiles.low_roll_threshold')->count(),
            'expired_or_restricted_rolls' => InventoryRoll::query()->where('company_id', $tenant->companyId())->whereIn('branch_id', $branchIds)
                ->where(fn ($q) => $q->whereDate('expiry_date', '<', today())->orWhereIn('status', ['damaged', 'quarantined']))->count(),
            'available_scraps' => RollScrap::query()->where('company_id', $tenant->companyId())->whereIn('branch_id', $branchIds)->where('status', 'available')->count(),
            'unposted_counts' => InventoryCount::query()->where('company_id', $tenant->companyId())->whereIn('branch_id', $branchIds)->where('status', '!=', 'posted')->count(),
        ] : [];

        return view('inventory.index', compact('records', 'section', 'alertSummary'));
    }

    private function sections(): array
    {
        return [
            'balances' => StockBalance::class, 'movements' => StockMovement::class,
            'rolls' => InventoryRoll::class, 'scraps' => RollScrap::class,
            'openings' => StockOpeningDocument::class, 'adjustments' => StockAdjustment::class,
            'counts' => InventoryCount::class, 'reservations' => InventoryReservation::class,
            'alerts' => StockBalance::class,
        ];
    }

    private function eagerLoad(Builder $query, string $section): void
    {
        match ($section) {
            'balances', 'alerts', 'movements', 'rolls', 'reservations' => $query->with(['product', 'warehouse']),
            'scraps' => $query->with(['sourceRoll', 'warehouse']),
            'openings', 'adjustments', 'counts' => $query->with('warehouse'),
        };
    }
}
