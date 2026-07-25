<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\PaymentMethod;
use App\Models\Tax;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchSettingsService
{
    public function update(Branch $branch, array $data): BranchSetting
    {
        $this->assertCompanyReference($data['default_tax_id'] ?? null, Tax::class, $branch->company_id, 'default_tax_id');
        $this->assertCompanyReference(
            $data['default_payment_method_id'] ?? null,
            PaymentMethod::class,
            $branch->company_id,
            'default_payment_method_id'
        );

        return DB::transaction(function () use ($branch, $data) {
            $settings = $branch->settings()->lockForUpdate()->first() ?? new BranchSetting();
            $branch->settings()->save($settings->fill($data));

            return $settings;
        });
    }

    private function assertCompanyReference(?int $id, string $model, int $companyId, string $field): void
    {
        if ($id && ! $model::query()->whereKey($id)->where('company_id', $companyId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([$field => 'القيمة المختارة لا تتبع الشركة الحالية أو غير نشطة.']);
        }
    }
}
