<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\DashboardLandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, DashboardLandingService $landing): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $request->user()->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        if ($request->user()->hasRole('company_owner')
            && $request->user()->company?->branches()->where('is_active', true)->doesntExist()) {
            return redirect()->route('branches.create');
        }

        return redirect()->to($landing->intendedOrDefault($request, $request->user()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'تم تسجيل الخروج بنجاح.');
    }
}
