<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\PostingProfileActionRequest;
use App\Http\Requests\PostingProfileRequest;
use App\Models\Account;
use App\Models\PostingProfile;
use App\Services\PostingProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PostingProfileController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', PostingProfile::class);

        return view('accounting.posting-profiles.index', [
            'profiles' => PostingProfile::where('company_id', $tenant->companyId())->with('lines')->latest()->get(),
            'accounts' => Account::where('company_id', $tenant->companyId())->where('is_active', true)->where('is_posting', true)->get(),
        ]);
    }

    public function store(PostingProfileRequest $request, PostingProfileService $service): RedirectResponse
    {
        $this->authorize('create', PostingProfile::class);
        $data = $request->safe()->except('lines');
        $service->create($data, $request->validated('lines'));

        return back()->with('success', 'تم إنشاء قالب الترحيل.');
    }

    public function action(PostingProfileActionRequest $request, PostingProfile $postingProfile, string $action, PostingProfileService $service): RedirectResponse
    {
        $this->authorize($action, $postingProfile);
        $action === 'activate' ? $service->activate($postingProfile) : $service->supersede($postingProfile);

        return back()->with('success', 'تم تحديث قالب الترحيل.');
    }
}
