<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Services\BranchOperationalDashboardService;
use App\Services\UserDashboardProfileResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        UserDashboardProfileResolver $profiles,
        TenantContext $tenant,
        BranchOperationalDashboardService $dashboard
    ): View {
        abort_unless($profiles->canAccessRoute($request->user(), 'dashboard'), 403);

        if (! $tenant->branch()) {
            return view('dashboard.generic');
        }

        return view('dashboard.index', ['dashboard' => $dashboard->build()]);
    }
}
