<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\ManagedUserRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchResponsibleUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $query = User::query()->with(['branch', 'roles'])->where('company_id', $tenant->companyId());
        if (! request()->user()->isCompanyAdministrator() && ! request()->user()->hasRole('system_admin')) {
            $branchIds = $tenant->accessibleBranches()->pluck('id');
            $query->where(fn ($inner) => $inner->whereIn('branch_id', $branchIds)
                ->orWhereHas('accessibleBranches', fn ($branches) => $branches->whereIn('branches.id', $branchIds)));
        }
        $users = $query->orderBy('name')->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create(TenantContext $tenant): View
    {
        return $this->form(new User(), $tenant);
    }

    public function store(
        ManagedUserRequest $request,
        TenantContext $tenant,
        BranchResponsibleUserService $responsibleUsers
    ): RedirectResponse {
        DB::transaction(function () use ($request, $tenant, $responsibleUsers) {
            $user = $this->save(new User(), $request, $tenant);

            if ($request->boolean('assign_as_responsible')) {
                $branch = Branch::query()
                    ->where('company_id', $tenant->companyId())
                    ->findOrFail($request->integer('responsible_branch_id'));
                $responsibleUsers->assign($branch, $user);
            }
        });

        if ($request->boolean('assign_as_responsible')) {
            return redirect()->route('branches.edit', $request->integer('responsible_branch_id'))
                ->with('status', 'تم إنشاء الحساب وتعيينه مسؤولًا لتشغيل الفرع.');
        }

        return redirect()->route('users.index')->with('status', 'تم إنشاء المستخدم.');
    }

    public function edit(User $user, TenantContext $tenant): View
    {
        abort_unless($user->company_id === $tenant->companyId(), 403);
        $this->authorize('update', $user);

        return $this->form($user, $tenant);
    }

    public function update(ManagedUserRequest $request, User $user, TenantContext $tenant): RedirectResponse
    {
        abort_unless($user->company_id === $tenant->companyId(), 403);
        $this->authorize('update', $user);
        $this->save($user, $request, $tenant);

        return redirect()->route('users.index')->with('status', 'تم تحديث المستخدم.');
    }

    public function disable(User $user, TenantContext $tenant): RedirectResponse
    {
        abort_unless($user->company_id === $tenant->companyId(), 403);
        $this->authorize('disable', $user);
        $user->forceFill(['status' => 'inactive'])->save();
        $user->tokens()->delete();

        return back()->with('status', 'تم تعطيل المستخدم.');
    }

    private function form(User $user, TenantContext $tenant): View
    {
        $branches = $tenant->accessibleBranches();
        $roles = Role::query()->where('is_active', true)
            ->whereNotIn('name', ['sales', 'cashier', 'receptionist', 'warehouse_keeper', 'technician', 'quality_controller'])
            ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))
            ->when(! request()->user()->isCompanyAdministrator() && ! request()->user()->hasRole('system_admin'),
                fn ($query) => $query->whereNotIn('name', ['system_admin', 'company_owner', 'general_manager']))
            ->orderBy('display_name')->get()
            ->sortBy(fn (Role $role) => $role->company_id === $tenant->companyId() ? 0 : 1)
            ->unique('name')->values();
        $user->loadMissing(['roles', 'accessibleBranches']);
        $canAssignResponsible = ! $user->exists
            && request()->user()->hasRole(['company_owner', 'general_manager', 'system_admin'])
            && request()->boolean('assign_as_responsible');
        $prefillBranchId = $canAssignResponsible ? request()->integer('branch_id') : null;
        if ($prefillBranchId && ! $branches->contains('id', $prefillBranchId)) {
            $prefillBranchId = null;
            $canAssignResponsible = false;
        }
        $prefillRoleId = $canAssignResponsible && request()->input('role') === 'branch_manager'
            ? $roles->firstWhere('name', 'branch_manager')?->id
            : null;
        $returnUrl = $canAssignResponsible && $prefillBranchId
            ? route('branches.edit', $prefillBranchId)
            : null;

        return view('users.form', compact(
            'user',
            'branches',
            'roles',
            'canAssignResponsible',
            'prefillBranchId',
            'prefillRoleId',
            'returnUrl',
        ));
    }

    private function save(User $user, ManagedUserRequest $request, TenantContext $tenant): User
    {
        return DB::transaction(function () use ($user, $request, $tenant) {
            $data = $request->safe()->except([
                'password',
                'password_confirmation',
                'branch_ids',
                'role_ids',
                'assign_as_responsible',
                'responsible_branch_id',
                'return_url',
            ]);
            $data['company_id'] = $tenant->companyId();
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->string('password'));
            }
            $user->forceFill($data)->save();
            $user->roles()->sync($request->input('role_ids'));
            $access = collect($request->input('branch_ids'))->mapWithKeys(fn ($branchId) => [
                $branchId => [
                    'is_default' => (int) $branchId === (int) $request->input('branch_id'),
                    'can_view' => true,
                ],
            ]);
            $user->accessibleBranches()->sync($access);

            return $user->refresh();
        });
    }
}
