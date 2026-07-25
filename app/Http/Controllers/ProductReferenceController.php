<?php

namespace App\Http\Controllers;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductReferenceController extends Controller
{
    public function index(string $section, TenantContext $tenant): View
    {
        abort_unless(in_array($section, ['categories', 'brands'], true), 404);
        $model = $section === 'categories' ? ProductCategory::class : ProductBrand::class;
        $records = $model::query()->where('company_id', $tenant->companyId())->orderBy('name')->get();
        $parents = $section === 'categories' ? $records->where('is_active', true) : collect();

        return view('inventory.references', compact('section', 'records', 'parents'));
    }

    public function store(Request $request, string $section, TenantContext $tenant): RedirectResponse
    {
        abort_unless(in_array($section, ['categories', 'brands'], true), 404);
        abort_unless(auth()->user()->hasPermission($section === 'categories' ? 'product_categories.manage' : 'product_brands.manage'), 403);
        $table = $section === 'categories' ? 'product_categories' : 'product_brands';
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique($table)->where('company_id', $tenant->companyId())],
            'name' => $section === 'brands'
                ? ['required', 'string', 'max:255', Rule::unique('product_brands')->where('company_id', $tenant->companyId())]
                : ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);
        if ($section === 'categories' && ! empty($data['parent_id'])
            && ! ProductCategory::whereKey($data['parent_id'])->where('company_id', $tenant->companyId())->exists()) {
            throw new BusinessRuleException('Parent category is outside the current company.');
        }
        $model = $section === 'categories' ? new ProductCategory($data) : new ProductBrand($data);
        $model->forceFill(['company_id' => $tenant->companyId(), 'is_active' => true]);
        if ($model instanceof ProductCategory) {
            $model->forceFill(['created_by' => auth()->id()]);
        }
        $model->save();

        return back()->with('success', 'تم حفظ البيانات.');
    }
}
