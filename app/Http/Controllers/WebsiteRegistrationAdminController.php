<?php

namespace App\Http\Controllers;

use App\Models\WebsiteRegistration;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WebsiteRegistrationAdminController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSystemAdmin($request);

        return view('registration-requests.index', [
            'registrationRequests' => WebsiteRegistration::query()
                ->latest()
                ->paginate(30),
            'branches' => collect(config('website.branches', []))->keyBy('id'),
        ]);
    }

    public function show(Request $request, int $registrationRequest): View
    {
        $this->authorizeSystemAdmin($request);

        return view('registration-requests.show', [
            'registrationRequest' => WebsiteRegistration::query()->findOrFail($registrationRequest),
            'branches' => collect(config('website.branches', []))->keyBy('id'),
        ]);
    }

    private function authorizeSystemAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('system_admin'), 403);
    }
}
