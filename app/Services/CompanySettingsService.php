<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Tax;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanySettingsService
{
    public function __construct(private FinancialHistoryInspector $history)
    {
    }

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

        return DB::transaction(function () use ($company, $data) {
            $lockedCompany = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            if (isset($data['currency_id'])
                && (int) $data['currency_id'] !== (int) $lockedCompany->currency_id
                && $this->hasPostedFinancialMovements($lockedCompany)) {
                throw ValidationException::withMessages([
                    'currency_id' => 'لا يمكن تغيير العملة الأساسية بعد وجود حركات مالية مرحلة.',
                ]);
            }
            $lockedCompany->update($data);

            return $lockedCompany;
        });
    }

    private function hasPostedFinancialMovements(Company $company): bool
    {
        return $this->history->hasPostedFinancialMovements($company);
    }
}
