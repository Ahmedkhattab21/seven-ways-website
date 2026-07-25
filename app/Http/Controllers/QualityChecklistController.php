<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\QualityChecklistTemplateRequest;
use App\Models\QualityChecklistTemplate;
use App\Models\Service;
use App\Services\QualityChecklistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QualityChecklistController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', QualityChecklistTemplate::class);

        return view('quality.templates.index', [
            'templates' => QualityChecklistTemplate::query()
                ->where('company_id', $tenant->companyId())->withCount('items')
                ->orderBy('scope_key')->orderByDesc('version')->paginate(30),
        ]);
    }

    public function create(TenantContext $tenant): View
    {
        $this->authorize('create', QualityChecklistTemplate::class);

        return view('quality.templates.form', [
            'services' => Service::where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(QualityChecklistTemplateRequest $request, QualityChecklistService $service): RedirectResponse
    {
        $template = $service->createVersion($request->safe()->except('items'), $request->validated('items'));

        return redirect()->route('quality-templates.index')->with('success', "Checklist version {$template->version} created.");
    }

    public function toggle(QualityChecklistTemplate $qualityTemplate, QualityChecklistService $service): RedirectResponse
    {
        $this->authorize('update', $qualityTemplate);
        $service->setActive($qualityTemplate, ! $qualityTemplate->is_active);

        return back()->with('success', 'Checklist status updated.');
    }
}
