<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\SystemNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemNotificationController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        abort_unless($request->user()->hasPermission('notifications.view'), 403);
        $notifications = SystemNotification::where('company_id', $tenant->companyId())
            ->where('user_id', $request->user()->id)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest()->paginate(50);

        return view('notifications.index', compact('notifications'));
    }

    public function read(Request $request, SystemNotification $notification): RedirectResponse
    {
        abort_unless($notification->company_id === $request->user()->company_id
            && $notification->user_id === $request->user()->id, 403);
        $notification->forceFill(['read_at' => $notification->read_at ?: now()])->save();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('notifications.view'), 403);
        SystemNotification::where('company_id', $request->user()->company_id)
            ->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
