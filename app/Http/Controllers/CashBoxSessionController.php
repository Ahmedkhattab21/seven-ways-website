<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\CashBoxCountRequest;
use App\Http\Requests\CashBoxSessionActionRequest;
use App\Http\Requests\CashBoxSessionRequest;
use App\Http\Requests\CashOverShortActionRequest;
use App\Models\CashBox;
use App\Models\CashBoxCount;
use App\Models\CashBoxSession;
use App\Models\CashOverShortAdjustment;
use App\Services\CashBoxCountService;
use App\Services\CashBoxSessionService;
use App\Services\CashOverShortService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CashBoxSessionController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.cash_sessions.view'), 403);

        return view('treasury.cash-sessions', [
            'sessions' => CashBoxSession::query()->where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
                ->with(['cashBox', 'custodian', 'counts.lines', 'counts.adjustment'])->latest('id')->paginate(30),
            'cashBoxes' => CashBox::query()->where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
                ->where('status', 'active')->get(),
            'custodians' => $tenant->company()->users()->where('status', 'active')->get(),
        ]);
    }

    public function store(CashBoxSessionRequest $request, CashBoxSessionService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.cash_sessions.open'), 403);
        $service->open($request->validated());

        return back()->with('success', 'تم فتح الجلسة بنجاح.');
    }

    public function action(
        CashBoxSessionActionRequest $request,
        CashBoxSession $cashBoxSession,
        string $action,
        CashBoxSessionService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.cash_sessions.'.str_replace('start_counting', 'count', $action)), 403);
        $service->action($cashBoxSession, $action, $request->validated('notes'));
        $messages = [
            'submit' => 'تم إرسال الجلسة للاعتماد.',
            'approve' => 'تم اعتماد الجلسة.',
            'close' => 'تم إغلاق الجلسة بنجاح.',
            'reopen' => 'تمت إعادة فتح الجلسة.',
            'cancel' => 'تم إلغاء الجلسة.',
        ];

        return back()->with('success', $messages[$action] ?? 'تم تحديث الجلسة.');
    }

    public function count(
        CashBoxCountRequest $request,
        CashBoxSession $cashBoxSession,
        CashBoxCountService $service
    ): RedirectResponse {
        $this->authorize('count', $cashBoxSession);
        $count = $service->create($cashBoxSession, $request->validated());
        $service->action($count, 'submit');

        return back()->with('success', 'تم تسجيل العد وإرساله للمراجعة.');
    }

    public function countAction(
        CashBoxSessionActionRequest $request,
        CashBoxCount $cashBoxCount,
        string $action,
        CashBoxCountService $service
    ): RedirectResponse {
        if ($action === 'approve') {
            $this->authorize('approve', $cashBoxCount);
        } elseif ($action === 'review') {
            $this->authorize('review', $cashBoxCount);
        } else {
            abort_unless($request->user()->hasPermission('treasury.cash_sessions.count'), 403);
        }
        $service->action($cashBoxCount, $action);
        $messages = [
            'submit' => 'تم إرسال العد للمراجعة.',
            'review' => 'تمت مراجعة العد بنجاح.',
            'approve' => $cashBoxCount->count_type === 'opening'
                ? 'تم اعتماد العد بنجاح وأصبحت الجلسة نشطة.'
                : 'تم اعتماد العد بنجاح.',
            'cancel' => 'تم إلغاء العد.',
        ];

        return back()->with('success', $messages[$action] ?? 'تم تحديث العد.');
    }

    public function adjustment(
        CashOverShortActionRequest $request,
        CashBoxCount $cashBoxCount,
        CashOverShortService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.cash_over_short.view'), 403);
        $service->create($cashBoxCount, $request->validated('description'));

        return back()->with('success', 'تم إنشاء فرق الخزينة للمراجعة.');
    }

    public function adjustmentAction(
        CashOverShortActionRequest $request,
        CashOverShortAdjustment $cashOverShortAdjustment,
        string $action,
        CashOverShortService $service
    ): RedirectResponse {
        $permission = $action === 'submit' ? 'view' : ($action === 'reverse' ? 'post' : $action);
        abort_unless($request->user()->hasPermission('treasury.cash_over_short.'.$permission), 403);
        $service->action($cashOverShortAdjustment, $action, $request->validated('reason'));

        return back()->with('success', 'تم تحديث فرق الخزينة.');
    }
}
