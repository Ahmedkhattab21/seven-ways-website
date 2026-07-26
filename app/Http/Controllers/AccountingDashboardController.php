<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AccountingDashboardController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(request()->user()->hasPermission('accounting.accounts.view'), 403);

        return view('accounting.dashboard');
    }
}
