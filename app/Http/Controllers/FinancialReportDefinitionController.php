<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Events\CashFlowMappingUpdated;
use App\Events\FinancialReportDefinitionCreated;
use App\Http\Requests\FinancialReportDefinitionRequest;
use App\Http\Requests\FinancialReportMappingRequest;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\CashFlowMapping;
use App\Models\FinancialReportDefinition;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinancialReportDefinitionController extends Controller
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', FinancialReportDefinition::class);
        $companyId = $this->tenant->companyId();

        return view('accounting.reports.definitions', [
            'definitions' => FinancialReportDefinition::query()->where('company_id', $companyId)->with('sections')->get(),
            'mappings' => CashFlowMapping::query()->where('company_id', $companyId)->get(),
            'accounts' => Account::query()->where('company_id', $companyId)->where('is_posting', true)->orderBy('account_code')->get(),
            'groups' => AccountGroup::query()->where('company_id', $companyId)->orderBy('code')->get(),
        ]);
    }

    public function store(FinancialReportDefinitionRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', FinancialReportDefinition::class);
        DB::transaction(function () use ($request) {
            $definition = new FinancialReportDefinition($request->validated());
            $definition->forceFill([
                'company_id' => $this->tenant->companyId(), 'is_system' => false,
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('financial_report_definition.created', $definition);
            DB::afterCommit(fn () => event(new FinancialReportDefinitionCreated($definition->id)));
        });

        return back()->with('success', 'Report definition created.');
    }

    public function mapping(FinancialReportMappingRequest $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('accounting.financial_reports.manage_mappings'), 403);
        $data = $request->validated();
        foreach (['account_id' => Account::class, 'account_group_id' => AccountGroup::class] as $field => $class) {
            if (! empty($data[$field])) {
                $class::query()->where('company_id', $this->tenant->companyId())->findOrFail($data[$field]);
            }
        }
        DB::transaction(function () use ($data) {
            $mapping = new CashFlowMapping($data);
            $mapping->forceFill([
                'company_id' => $this->tenant->companyId(), 'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('cash_flow_mapping.created', $mapping);
            DB::afterCommit(fn () => event(new CashFlowMappingUpdated($mapping->id)));
        });

        return back()->with('success', 'Cash-flow mapping created.');
    }
}
