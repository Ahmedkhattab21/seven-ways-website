<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnifiedAuditController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        abort_unless($request->user()->hasPermission('audit.view'), 403);
        $query = AuditEvent::where('company_id', $tenant->companyId())
            ->where(fn ($q) => $q->whereNull('branch_id')
                ->orWhereIn('branch_id', $tenant->accessibleBranches()->pluck('id')));
        foreach (['module', 'action', 'branch_id', 'correlation_id', 'document_number'] as $field) {
            $query->when($request->filled($field), fn ($q) => $q->where($field, $request->input($field)));
        }

        return view('audit.index', ['events' => $query->latest('occurred_at')->paginate(100)]);
    }
}
