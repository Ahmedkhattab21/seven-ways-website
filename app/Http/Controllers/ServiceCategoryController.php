<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\ServiceCategoryRequest;
use App\Models\ServiceCategory;
use App\Services\ServiceCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServiceCategoryController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $categories = ServiceCategory::query()->where('company_id', $tenant->companyId())
            ->withCount(['children', 'services'])->with('parent')->orderBy('sort_order')->orderBy('name')->paginate(30);

        return view('services.categories.index', compact('categories'));
    }

    public function create(TenantContext $tenant): View
    {
        return view('services.categories.form', [
            'serviceCategory' => new ServiceCategory,
            'parents' => ServiceCategory::where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(ServiceCategoryRequest $request, ServiceCatalogService $catalog): RedirectResponse
    {
        $catalog->saveCategory($request->validated());

        return redirect()->route('service-categories.index')->with('success', 'تم إنشاء تصنيف الخدمة.');
    }

    public function edit(ServiceCategory $serviceCategory, TenantContext $tenant): View
    {
        $this->authorize('update', $serviceCategory);

        return view('services.categories.form', [
            'serviceCategory' => $serviceCategory,
            'parents' => ServiceCategory::where('company_id', $tenant->companyId())->where('id', '!=', $serviceCategory->id)
                ->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(
        ServiceCategoryRequest $request,
        ServiceCategory $serviceCategory,
        ServiceCatalogService $catalog
    ): RedirectResponse {
        $this->authorize('update', $serviceCategory);
        $catalog->saveCategory($request->validated(), $serviceCategory);

        return redirect()->route('service-categories.index')->with('success', 'تم تحديث تصنيف الخدمة.');
    }

    public function disable(ServiceCategory $serviceCategory, ServiceCatalogService $catalog): RedirectResponse
    {
        $this->authorize('disable', $serviceCategory);
        $catalog->disableCategory($serviceCategory);

        return back()->with('success', 'تم تعطيل تصنيف الخدمة.');
    }
}
