<?php

namespace App\Http\Controllers;

use App\Models\InventoryCount;
use App\Models\InventoryReservation;
use App\Models\InventoryRoll;
use App\Models\RollScrap;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockOpeningDocument;
use App\Services\InventoryCountService;
use App\Services\InventoryReservationService;
use App\Services\InventoryService;
use App\Services\RollConsumptionService;
use App\Services\RollScrapService;
use App\Services\StockAdjustmentService;
use App\Services\StockOpeningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InventoryActionController extends Controller
{
    public function consumeRoll(Request $request, InventoryRoll $roll, RollConsumptionService $service): RedirectResponse
    {
        $this->authorize('consume', $roll);
        $data = $request->validate([
            'length' => ['required', 'numeric', 'gt:0'], 'usable_area' => ['required', 'numeric', 'min:0'],
            'waste_area' => ['nullable', 'numeric', 'min:0'], 'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $service->consume($roll, $data['length'], $data['usable_area'], $data['waste_area'] ?? '0', ['reason' => $data['reason'] ?? null]);

        return back()->with('success', 'تم تسجيل استهلاك الرول.');
    }

    public function createScrap(Request $request, InventoryRoll $roll, RollScrapService $service): RedirectResponse
    {
        $this->authorize('consume', $roll);
        $data = $request->validate(['width' => ['required', 'numeric', 'gt:0'], 'length' => ['required', 'numeric', 'gt:0']]);
        $service->create($roll, $data['width'], $data['length']);

        return back()->with('success', 'تم إنشاء القصاصة.');
    }

    public function consumeScrap(RollScrap $scrap, RollScrapService $service): RedirectResponse
    {
        $this->authorize('manage', $scrap);
        $service->consume($scrap);

        return back()->with('success', 'تم استهلاك القصاصة.');
    }

    public function reverseMovement(StockMovement $movement, InventoryService $service): RedirectResponse
    {
        $this->authorize('reverse', $movement);
        $service->reverse($movement);

        return back()->with('success', 'تم عكس الحركة.');
    }

    public function postOpening(StockOpeningDocument $opening, StockOpeningService $service): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('inventory.post'), 403);
        $service->post($opening);

        return back()->with('success', 'تم ترحيل الرصيد الافتتاحي.');
    }

    public function postAdjustment(StockAdjustment $adjustment, StockAdjustmentService $service): RedirectResponse
    {
        $this->authorize('post', $adjustment);
        $service->post($adjustment);

        return back()->with('success', 'تم ترحيل التسوية.');
    }

    public function snapshotCount(InventoryCount $count, InventoryCountService $service): RedirectResponse
    {
        $this->authorize('snapshot', $count);
        $service->snapshot($count);

        return redirect()->route('inventory.counts.show', $count)
            ->with('success', 'تم بدء الجرد وأخذ لقطة الأرصدة بنجاح.');
    }

    public function saveCount(Request $request, InventoryCount $count, InventoryCountService $service): RedirectResponse
    {
        $this->authorize('count', $count);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.counted_quantity' => ['required', 'numeric', 'min:0'],
        ]);
        $service->record($count, $data['items']);

        return redirect()->route('inventory.counts.show', $count)
            ->with('success', 'تم حفظ الكميات المعدودة وإرسال الجرد للمراجعة.');
    }

    public function postCount(InventoryCount $count, InventoryCountService $service): RedirectResponse
    {
        $this->authorize('post', $count);
        $service->post($count);

        return back()->with('success', 'تم ترحيل الجرد.');
    }

    public function releaseReservation(InventoryReservation $reservation, InventoryReservationService $service): RedirectResponse
    {
        $this->authorize('manage', $reservation);
        $service->release($reservation);

        return back()->with('success', 'تم تحرير الحجز.');
    }
}
