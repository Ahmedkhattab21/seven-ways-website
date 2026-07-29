<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\OpeningBalanceDocument;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tax;
use App\Models\User;
use App\Models\Warehouse;

class CompanySetupProgressService
{
    public function for(Company $company): array
    {
        $companyId = $company->getKey();
        $steps = [
            $this->step('بيانات الشركة', 'company.edit', 'companies.view',
                filled($company->name) && filled($company->country_code) && filled($company->timezone)),
            $this->step('الفروع', 'branches.index', 'branches.view',
                Branch::query()->where('company_id', $companyId)->where('is_active', true)->exists()),
            $this->step('العملة الافتراضية', 'reference.index', 'settings.view',
                $company->currency_id !== null && $company->currency()->where('is_active', true)->exists(), ['currencies']),
            $this->step('الضرائب', 'reference.index', 'taxes.view',
                Tax::query()->where('company_id', $companyId)->where('is_active', true)->exists(), ['taxes']),
            $this->step('طرق الدفع', 'reference.index', 'payment_methods.view',
                PaymentMethod::query()->where('company_id', $companyId)->where('is_active', true)->exists(), ['payment-methods']),
            $this->step('السنة المالية', 'accounting.fiscal-years.index', 'accounting.fiscal_years.view',
                FiscalYear::query()->where('company_id', $companyId)->where('is_current', true)->exists()),
            $this->step('تسلسل المستندات', 'reference.index', 'document_sequences.view',
                DocumentSequence::query()->where('company_id', $companyId)->where('is_active', true)->exists(), ['document-sequences']),
            $this->step('المستودعات', 'warehouses.index', 'warehouses.view',
                Warehouse::query()->where('company_id', $companyId)->where('is_active', true)->exists()),
            $this->step('المنتجات والخدمات', 'products.index', 'products.view',
                Product::query()->where('company_id', $companyId)->where('is_active', true)->exists()
                || Service::query()->where('company_id', $companyId)->where('is_active', true)->exists()),
            $this->step('المستخدمون والأدوار', 'users.index', 'users.view',
                User::query()->where('company_id', $companyId)->where('status', 'active')->exists()
                && $company->roles()->where('is_active', true)->exists()),
            $this->step('الأرصدة الافتتاحية', 'accounting.opening-balances.index', 'accounting.opening_balances.view',
                OpeningBalanceDocument::query()->where('company_id', $companyId)->whereIn('status', ['posted', 'reversed'])->exists()),
        ];

        $completed = collect($steps)->where('complete', true)->count();

        return [
            'steps' => $steps,
            'completed' => $completed,
            'total' => count($steps),
            'complete' => $completed === count($steps),
        ];
    }

    private function step(
        string $label,
        string $route,
        string $permission,
        bool $complete,
        array $params = []
    ): array {
        return compact('label', 'route', 'permission', 'complete', 'params');
    }
}
