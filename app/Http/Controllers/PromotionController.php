<?php

namespace App\Http\Controllers;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Http\Requests\PromotionRequest;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Services\AuditService;
use App\Services\DocumentNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $promotions = Promotion::where('company_id', $tenant->companyId())->withCount(['services', 'packages', 'branches'])
            ->latest()->paginate(20);

        return view('services.promotions.index', compact('promotions'));
    }

    public function create(TenantContext $tenant): View
    {
        return view('services.promotions.form', ['promotion' => new Promotion] + $this->references($tenant));
    }

    public function store(
        PromotionRequest $request,
        TenantContext $tenant,
        DocumentNumberService $numbers,
        AuditService $audit
    ): RedirectResponse {
        $promotion = $this->save($request->validated(), new Promotion, $tenant, $numbers, $audit);

        return redirect()->route('promotions.edit', $promotion)->with('success', 'تم إنشاء أساس العرض الترويجي.');
    }

    public function edit(Promotion $promotion, TenantContext $tenant): View
    {
        $this->authorize('update', $promotion);
        $promotion->load(['services', 'packages', 'branches']);

        return view('services.promotions.form', compact('promotion') + $this->references($tenant));
    }

    public function update(
        PromotionRequest $request,
        Promotion $promotion,
        TenantContext $tenant,
        DocumentNumberService $numbers,
        AuditService $audit
    ): RedirectResponse {
        $this->authorize('update', $promotion);
        $this->save($request->validated(), $promotion, $tenant, $numbers, $audit);

        return back()->with('success', 'تم تحديث أساس العرض الترويجي.');
    }

    private function save(
        array $data,
        Promotion $promotion,
        TenantContext $tenant,
        DocumentNumberService $numbers,
        AuditService $audit
    ): Promotion {
        foreach ([
            'service_ids' => [Service::class, 'services'],
            'package_ids' => [ServicePackage::class, 'packages'],
            'branch_ids' => [\App\Models\Branch::class, 'branches'],
        ] as $key => [$model, $relation]) {
            $ids = collect($data[$key] ?? [])->filter();
            if ($model::query()->whereIn('id', $ids)->where('company_id', $tenant->companyId())->count() !== $ids->count()) {
                throw new BusinessRuleException('Promotion links must belong to the current company.', status: 403);
            }
        }

        return DB::transaction(function () use ($data, $promotion, $tenant, $numbers, $audit) {
            $links = collect($data)->only(['service_ids', 'package_ids', 'branch_ids']);
            $attributes = collect($data)->except(['service_ids', 'package_ids', 'branch_ids'])->all();
            if (empty($attributes['code'])) {
                $attributes['code'] = $numbers->next('promotion', $tenant->companyId(), null);
            }
            $promotion->fill($attributes)->forceFill(['company_id' => $tenant->companyId()])->save();
            $promotion->services()->sync($links->get('service_ids', []));
            $promotion->packages()->sync($links->get('package_ids', []));
            $promotion->branches()->sync($links->get('branch_ids', []));
            $audit->record($promotion->wasRecentlyCreated ? 'promotion.created' : 'promotion.updated', $promotion);

            return $promotion;
        });
    }

    private function references(TenantContext $tenant): array
    {
        return [
            'services' => Service::where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('name')->get(),
            'packages' => ServicePackage::where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('name')->get(),
            'branches' => $tenant->accessibleBranches(),
        ];
    }
}
