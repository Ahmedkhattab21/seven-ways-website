<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class BranchService
{
    public function __construct(private BranchResponsibleUserService $responsibleUsers)
    {
    }

    public function create(int $companyId, array $data): Branch
    {
        return DB::transaction(function () use ($companyId, $data) {
            $responsibleAccount = $data['responsible_account'] ?? null;
            unset($data['responsible_account']);
            abort_unless(Company::query()->whereKey($companyId)->where('is_active', true)->exists(), 422, 'الشركة غير نشطة.');
            Branch::query()->where('company_id', $companyId)->lockForUpdate()->get();
            $hasActiveBranch = Branch::query()->where('company_id', $companyId)->where('is_active', true)->exists();
            $isMain = (bool) ($data['is_main'] ?? false) || ! $hasActiveBranch;

            if ($isMain) {
                Branch::query()->where('company_id', $companyId)->update(['is_main' => false]);
            }

            $branch = Branch::query()->create([...$data, 'company_id' => $companyId, 'is_main' => $isMain]);
            $branch->settings()->create([]);

            if ($responsibleAccount) {
                $role = Role::query()
                    ->where('name', 'branch_manager')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->orWhereNull('company_id'))
                    ->orderByRaw('company_id is null')
                    ->firstOrFail();
                $user = new User();
                $user->forceFill([
                    'company_id' => $companyId,
                    'branch_id' => $branch->id,
                    'name' => $responsibleAccount['name'],
                    'email' => $responsibleAccount['email'],
                    'password' => Hash::make($responsibleAccount['password']),
                    'status' => $responsibleAccount['status'],
                ])->save();
                $user->roles()->attach($role);
                $this->responsibleUsers->assign($branch, $user);
            }

            if (! $hasActiveBranch) {
                User::query()->where('company_id', $companyId)
                    ->whereNull('branch_id')
                    ->whereHas('roles', fn ($query) => $query->where('name', 'company_owner'))
                    ->get()
                    ->each(function (User $owner) use ($branch): void {
                        $owner->forceFill(['branch_id' => $branch->id])->save();
                        $owner->accessibleBranches()->syncWithoutDetaching([
                            $branch->id => [
                                'is_default' => true,
                                'can_view' => true,
                                'can_create' => true,
                                'can_update' => true,
                                'can_approve' => true,
                            ],
                        ]);
                    });
            }

            return $branch;
        });
    }

    public function update(Branch $branch, array $data): Branch
    {
        return DB::transaction(function () use ($branch, $data) {
            Branch::query()->where('company_id', $branch->company_id)->lockForUpdate()->get();
            if ($branch->is_main && ((! ($data['is_main'] ?? true)) || ! ($data['is_active'] ?? true))) {
                throw ValidationException::withMessages([
                    'branch' => 'عيّن فرعًا رئيسيًا آخر قبل إلغاء أو تعطيل الفرع الرئيسي.',
                ]);
            }
            if (($data['is_main'] ?? false) === true) {
                Branch::query()->where('company_id', $branch->company_id)->whereKeyNot($branch->id)->update(['is_main' => false]);
            }
            $branch->update($data);

            return $branch;
        });
    }

    public function makeMain(Branch $branch): void
    {
        DB::transaction(function () use ($branch) {
            Branch::query()->where('company_id', $branch->company_id)->lockForUpdate()->get();
            Branch::query()->where('company_id', $branch->company_id)->update(['is_main' => false]);
            $branch->update(['is_main' => true, 'is_active' => true]);
        });
    }

    public function disable(Branch $branch): void
    {
        if ($branch->is_main) {
            throw ValidationException::withMessages(['branch' => 'عيّن فرعًا رئيسيًا آخر قبل تعطيل الفرع الرئيسي.']);
        }

        $branch->update(['is_active' => false]);
    }
}
