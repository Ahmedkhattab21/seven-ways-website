<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\ReferenceDataRequest;
use App\Models\Currency;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\PaymentMethod;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Models\VehicleSize;
use App\Models\VehicleType;
use App\Services\DocumentNumberService;
use App\Services\FiscalYearService;
use App\Services\TaxService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReferenceDataController extends Controller
{
    public function index(Request $request, string $section, TenantContext $tenant): View|RedirectResponse
    {
        if ($section === 'fiscal-years') {
            return redirect()->route('accounting.fiscal-years.index');
        }

        $config = $this->config($section);
        $this->authorizeViewSection($section);
        $query = $this->query($section, $config['model'], $tenant);
        $this->applySearch($query, $request->string('search')->toString(), $config['search']);
        if ($request->filled('status') && in_array($request->input('status'), ['active', 'inactive'], true)) {
            $query->where('is_active', $request->input('status') === 'active');
        }
        if ($section === 'vehicle-models') {
            $query->with('brand');
        }
        if ($section === 'document-sequences') {
            $query->with('branch');
        }

        $items = $query->orderBy($config['order'])->paginate(20)->withQueryString();
        $sequenceWarnings = $section === 'document-sequences'
            ? $this->documentSequenceWarnings($items->getCollection())
            : collect();

        return view('reference.index', compact('items', 'section', 'config', 'sequenceWarnings'));
    }

    public function create(string $section, TenantContext $tenant): View|RedirectResponse
    {
        if ($section === 'fiscal-years') {
            return redirect()->route('accounting.fiscal-years.index');
        }

        $config = $this->config($section);
        $this->authorizeManageSection($section);
        $model = new $config['model'];

        return view('reference.form', [
            'item' => $model,
            'section' => $section,
            'config' => $config,
            ...$this->formOptions($section, $tenant),
        ]);
    }

    public function store(
        ReferenceDataRequest $request,
        string $section,
        TenantContext $tenant,
        TaxService $taxes,
        FiscalYearService $fiscalYears
    ): RedirectResponse {
        if ($section === 'fiscal-years') {
            return redirect()->route('accounting.fiscal-years.index');
        }

        $config = $this->config($section);
        $model = new $config['model'];
        $this->save($model, $section, $this->data($request, $section), $tenant, $taxes, $fiscalYears);

        return redirect()->route('reference.index', $section)->with('status', 'تمت إضافة السجل.');
    }

    public function edit(string $section, int $reference, TenantContext $tenant): View|RedirectResponse
    {
        if ($section === 'fiscal-years') {
            return redirect()->route('accounting.fiscal-years.index');
        }

        $config = $this->config($section);
        $item = $config['model']::query()->findOrFail($reference);
        $this->authorize('update', $item);

        return view('reference.form', [
            'item' => $item,
            'section' => $section,
            'config' => $config,
            ...$this->formOptions($section, $tenant),
        ]);
    }

    public function update(
        ReferenceDataRequest $request,
        string $section,
        int $reference,
        TenantContext $tenant,
        TaxService $taxes,
        FiscalYearService $fiscalYears
    ): RedirectResponse {
        if ($section === 'fiscal-years') {
            return redirect()->route('accounting.fiscal-years.index');
        }

        $config = $this->config($section);
        $model = $config['model']::query()->findOrFail($reference);
        $this->authorize('update', $model);
        $this->save($model, $section, $this->data($request, $section), $tenant, $taxes, $fiscalYears);

        return redirect()->route('reference.index', $section)->with('status', 'تم تحديث السجل.');
    }

    private function save(
        Model $model,
        string $section,
        array $data,
        TenantContext $tenant,
        TaxService $taxes,
        FiscalYearService $fiscalYears
    ): void {
        if ($section === 'taxes') {
            $taxes->save($model, $tenant->companyId(), $data);

            return;
        }
        if ($section === 'fiscal-years') {
            $fiscalYears->save($model, $tenant->companyId(), $tenant->user(), $data);

            return;
        }

        try {
            DB::transaction(function () use ($model, $section, $data, $tenant) {
                if (in_array($section, ['units', 'payment-methods', 'vehicle-sizes', 'vehicle-types', 'document-sequences'], true)) {
                    $data['company_id'] = $tenant->companyId();
                }
                if ($section === 'document-sequences') {
                    $this->assertBranchAccess($data['branch_id'] ?? null, $tenant);
                    $this->assertNoActiveDocumentSequenceDuplicate($model, $data, $tenant->companyId());
                    $periodKey = match ($data['reset_period']) {
                        'yearly' => now()->format('Y'),
                        'monthly' => now()->format('Ym'),
                        default => null,
                    };
                    $data['period_key'] = $periodKey;
                    $data['scope_key'] = DocumentNumberService::scopeKey(
                        $tenant->companyId(),
                        $data['branch_id'] ?? null,
                        $data['document_type'],
                        $periodKey
                    );
                }
                $model->forceFill($data)->save();
            });
        } catch (QueryException $exception) {
            if ($section === 'document-sequences'
                && $exception->getCode() === '23000'
                && str_contains($exception->getMessage(), 'document_sequences_scope_key_unique')) {
                throw ValidationException::withMessages([
                    'document_type' => 'يوجد تسلسل فعال بالفعل لهذا النوع في الفرع المحدد.',
                ]);
            }

            throw $exception;
        }
    }

    private function data(ReferenceDataRequest $request, string $section): array
    {
        $data = $request->validated();
        $booleanFields = match ($section) {
            'taxes' => ['is_default', 'is_inclusive', 'is_active'],
            'payment-methods' => ['requires_reference', 'is_cash', 'is_active'],
            'fiscal-years' => ['is_current'],
            default => ['is_active'],
        };
        foreach ($booleanFields as $field) {
            $data[$field] = $request->boolean($field);
        }

        return $data;
    }

    private function query(string $section, string $model, TenantContext $tenant): Builder
    {
        $query = $model::query();
        if (in_array($section, ['taxes', 'payment-methods', 'fiscal-years', 'document-sequences'], true)) {
            return $query->where('company_id', $tenant->companyId());
        }
        if (in_array($section, ['units', 'vehicle-sizes', 'vehicle-types'], true)) {
            return $query->where(fn ($inner) => $inner->whereNull('company_id')->orWhere('company_id', $tenant->companyId()));
        }

        return $query;
    }

    private function applySearch(Builder $query, string $search, array $fields): void
    {
        if ($search === '') {
            return;
        }
        $query->where(function ($inner) use ($search, $fields) {
            foreach ($fields as $field) {
                $inner->orWhere($field, 'like', "%{$search}%");
            }
        });
    }

    private function formOptions(string $section, TenantContext $tenant): array
    {
        return [
            'brands' => $section === 'vehicle-models' ? VehicleBrand::query()->where('is_active', true)->orderBy('name_ar')->get() : collect(),
            'branches' => $section === 'document-sequences' ? $tenant->accessibleBranches() : collect(),
            'documentTypes' => $section === 'document-sequences' ? config('document_sequences.types', []) : [],
        ];
    }

    private function assertBranchAccess(?int $branchId, TenantContext $tenant): void
    {
        if ($branchId && ! $tenant->accessibleBranches()->contains('id', $branchId)) {
            throw ValidationException::withMessages(['branch_id' => 'الفرع خارج السياق المسموح.']);
        }
    }

    private function assertNoActiveDocumentSequenceDuplicate(Model $model, array $data, int $companyId): void
    {
        if (! ($data['is_active'] ?? false)) {
            return;
        }

        $duplicate = DocumentSequence::query()
            ->where('company_id', $companyId)
            ->where('document_type', $data['document_type'])
            ->where('is_active', true)
            ->when(
                $data['branch_id'] ?? null,
                fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId),
                fn (Builder $query) => $query->whereNull('branch_id')
            )
            ->when($model->exists, fn (Builder $query) => $query->whereKeyNot($model->getKey()))
            ->lockForUpdate()
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'document_type' => 'يوجد تسلسل فعال بالفعل لهذا النوع في الفرع المحدد.',
            ]);
        }
    }

    private function documentSequenceWarnings($items)
    {
        $types = config('document_sequences.types', []);

        return $items->flatMap(function (DocumentSequence $sequence) use ($types) {
            $warnings = [];
            if (! isset($types[$sequence->document_type])) {
                $warnings[] = "التسلسل #{$sequence->id} يستخدم نوع مستند قديم أو غير معروف: {$sequence->document_type}.";
            }
            if ($sequence->branch_id && ! $sequence->branch) {
                $warnings[] = "التسلسل #{$sequence->id} مرتبط بفرع غير موجود (#{$sequence->branch_id}).";
            }
            if (! in_array($sequence->reset_period, ['never', 'yearly', 'monthly'], true)) {
                $warnings[] = "التسلسل #{$sequence->id} يستخدم فترة تصفير غير مدعومة: {$sequence->reset_period}.";
            }

            return $warnings;
        })->unique()->values();
    }

    private function authorizeManageSection(string $section): void
    {
        if (in_array($section, ['currencies', 'vehicle-brands', 'vehicle-models'], true)) {
            abort_unless(auth()->user()->hasRole('system_admin'), 403);
        }
        $permission = $this->config($section)['manage_permission'];
        abort_unless(auth()->user()->hasRole('system_admin') || auth()->user()->hasPermission($permission), 403);
    }

    private function authorizeViewSection(string $section): void
    {
        $permission = $this->config($section)['view_permission'];
        abort_unless(auth()->user()->hasRole('system_admin') || auth()->user()->hasPermission($permission), 403);
    }

    private function config(string $section): array
    {
        $configs = [
            'currencies' => ['model' => Currency::class, 'title' => 'العملات', 'search' => ['code', 'name_ar', 'name_en'], 'order' => 'code', 'view_permission' => 'settings.view', 'manage_permission' => 'settings.manage'],
            'taxes' => ['model' => Tax::class, 'title' => 'الضرائب', 'search' => ['code', 'name'], 'order' => 'code', 'view_permission' => 'taxes.view', 'manage_permission' => 'taxes.manage'],
            'units' => ['model' => Unit::class, 'title' => 'الوحدات', 'search' => ['code', 'name', 'symbol'], 'order' => 'code', 'view_permission' => 'units.view', 'manage_permission' => 'units.manage'],
            'payment-methods' => ['model' => PaymentMethod::class, 'title' => 'طرق الدفع', 'search' => ['code', 'name'], 'order' => 'sort_order', 'view_permission' => 'payment_methods.view', 'manage_permission' => 'payment_methods.manage'],
            'vehicle-brands' => ['model' => VehicleBrand::class, 'title' => 'ماركات السيارات', 'search' => ['name_ar', 'name_en'], 'order' => 'name_ar', 'view_permission' => 'vehicle_references.view', 'manage_permission' => 'vehicle_references.manage'],
            'vehicle-models' => ['model' => VehicleModel::class, 'title' => 'موديلات السيارات', 'search' => ['name_ar', 'name_en'], 'order' => 'name_ar', 'view_permission' => 'vehicle_references.view', 'manage_permission' => 'vehicle_references.manage'],
            'vehicle-sizes' => ['model' => VehicleSize::class, 'title' => 'أحجام السيارات', 'search' => ['code', 'name'], 'order' => 'sort_order', 'view_permission' => 'vehicle_references.view', 'manage_permission' => 'vehicle_references.manage'],
            'vehicle-types' => ['model' => VehicleType::class, 'title' => 'أنواع السيارات', 'search' => ['code', 'name'], 'order' => 'sort_order', 'view_permission' => 'vehicle_references.view', 'manage_permission' => 'vehicle_references.manage'],
            'fiscal-years' => ['model' => FiscalYear::class, 'title' => 'السنوات المالية', 'search' => ['name'], 'order' => 'start_date', 'view_permission' => 'fiscal_years.view', 'manage_permission' => 'fiscal_years.manage'],
            'document-sequences' => ['model' => DocumentSequence::class, 'title' => 'تسلسل أرقام المستندات', 'search' => ['document_type', 'prefix'], 'order' => 'document_type', 'view_permission' => 'document_sequences.view', 'manage_permission' => 'document_sequences.manage'],
        ];

        abort_unless(isset($configs[$section]), 404);

        return $configs[$section];
    }
}
