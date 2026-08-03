<?php

namespace App\Http\Controllers;

use App\Services\BranchOperationalDashboardService;
use App\Services\UserDashboardProfileResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        UserDashboardProfileResolver $profiles,
        BranchOperationalDashboardService $dashboard
    ): View|RedirectResponse {
        $route = $profiles->routeName($request->user());
        if ($route && $route !== 'dashboard') {
            return redirect()->route($route);
        }
        abort_unless($route === 'dashboard' && $request->user()->hasPermission('dashboard.view'), 403);

        return view('dashboard.index', ['dashboard' => $dashboard->build()]);
    }
}
