<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Tax;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanySettingsService
{
    public function update(Company $company, array $data): Company
    {
        if (isset($data['currency_id'])
            && (int) $data['currency_id'] !== (int) $company->currency_id
            && ! Currency::query()->whereKey($data['currency_id'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['currency_id' => 'العملة الأساسية يجب أن تكون نشطة.']);
        }
        if (! empty($data['default_tax_id'])
            && ! Tax::query()
                ->whereKey($data['default_tax_id'])
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->exists()) {
            throw ValidationException::withMessages([
                'default_tax_id' => 'الضريبة المختارة لا تتبع الشركة الحالية أو غير نشطة.',
            ]);
        }
        if (isset($data['currency_id'])
            && (int) $data['currency_id'] !== (int) $company->currency_id
            && $this->hasPostedFinancialMovements($company)) {
            throw ValidationException::withMessages([
                'currency_id' => 'لا يمكن تغيير العملة الأساسية بعد وجود حركات مالية مرحلة.',
            ]);
        }

        return DB::transaction(function () use ($company, $data) {
            $company->update($data);

            return $company;
        });
    }

    private function hasPostedFinancialMovements(Company $company): bool
    {
        // Phase 4 has no commercial document tables yet. This hook will enforce
        // the restriction when posted financial movements are introduced.
        return false;
    }
}
