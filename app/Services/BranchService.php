<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchService
{
    public function create(int $companyId, array $data): Branch
    {
        return DB::transaction(function () use ($companyId, $data) {
            abort_unless(Company::query()->whereKey($companyId)->where('is_active', true)->exists(), 422, 'الشركة غير نشطة.');
            Branch::query()->where('company_id', $companyId)->lockForUpdate()->get();
            $isMain = (bool) ($data['is_main'] ?? false)
                || ! Branch::query()->where('company_id', $companyId)->where('is_active', true)->exists();

            if ($isMain) {
                Branch::query()->where('company_id', $companyId)->update(['is_main' => false]);
            }

            $branch = Branch::query()->create([...$data, 'company_id' => $companyId, 'is_main' => $isMain]);
            $branch->settings()->create([]);

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
