<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchContextController extends Controller
{
    public function store(Request $request, TenantContext $tenant): RedirectResponse
    {
        $validated = $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id']]);
        $tenant->switchTo(Branch::query()->findOrFail($validated['branch_id']));

        return back()->with('status', 'تم تغيير الفرع الحالي.');
    }
}
