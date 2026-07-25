<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\RolePermissionsRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $roles = Role::query()->withCount(['permissions', 'users'])
            ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))
            ->orderBy('display_name')->get();

        return view('roles.index', compact('roles'));
    }

    public function create(): View
    {
        return view('roles.form', ['role' => new Role(), 'permissions' => Permission::query()->orderBy('name')->get()]);
    }

    public function store(Request $request, TenantContext $tenant): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('roles.manage') || $request->user()->hasRole('system_admin'), 403);
        $data = $request->validate([
            'name' => ['required', 'alpha_dash', 'max:80', Rule::unique('roles')->where('company_id', $tenant->companyId())],
            'display_name' => ['required', 'string', 'max:255'],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);
        $role = Role::query()->create([
            'company_id' => $tenant->companyId(), 'name' => $data['name'],
            'display_name' => $data['display_name'], 'scope' => 'company', 'is_active' => true,
        ]);
        $role->permissions()->sync($data['permission_ids'] ?? []);

        return redirect()->route('roles.index')->with('status', 'تم إنشاء الدور.');
    }

    public function edit(Role $role, TenantContext $tenant): View
    {
        abort_unless($role->company_id === $tenant->companyId(), 403);
        $this->authorize('update', $role);
        $role->load('permissions');

        return view('roles.form', ['role' => $role, 'permissions' => Permission::query()->orderBy('name')->get()]);
    }

    public function update(RolePermissionsRequest $request, Role $role, TenantContext $tenant): RedirectResponse
    {
        abort_unless($role->company_id === $tenant->companyId(), 403);
        $role->permissions()->sync($request->input('permission_ids', []));

        return redirect()->route('roles.index')->with('status', 'تم تحديث صلاحيات الدور.');
    }
}
