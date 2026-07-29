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
        $sequenceStatus = $this->documentSequenceStatus($companyId);
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
            $this->step(
                'تسلسل المستندات',
                'reference.index',
                'document_sequences.view',
                $sequenceStatus['complete'],
                ['document-sequences'],
                $sequenceStatus
            ),
            $this->step('المستودعات', 'warehouses.index', 'warehouses.view',
                Warehouse::query()->where('company_id', $companyId)->where('is_active', true)->exists()),
            $this->step('المنتجات والخدمات', 'catalog.index', 'products.view',
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

    private function documentSequenceStatus(int $companyId): array
    {
        $types = collect(config('document_sequences.types', []))
            ->filter(fn (array $definition) => $definition['setup_required'] ?? false);
        $branches = Branch::query()->where('company_id', $companyId)->where('is_active', true)
            ->orderBy('id')->get(['id', 'code', 'name']);
        $branchTypes = $types->filter(fn (array $definition) => $definition['scope'] === 'branch');
        $companyTypes = $types->filter(fn (array $definition) => $definition['scope'] === 'company');
        $existing = DocumentSequence::query()->where('company_id', $companyId)->where('is_active', true)
            ->whereIn('document_type', $types->keys())->get(['branch_id', 'document_type'])
            ->mapWithKeys(fn (DocumentSequence $sequence) => [
                ($sequence->branch_id ?: 0).':'.$sequence->document_type => true,
            ]);
        $items = collect();

        foreach ($branches as $branch) {
            foreach ($branchTypes as $type => $definition) {
                $items->push([
                    'type' => $type,
                    'type_label' => $definition['label'],
                    'branch_id' => $branch->id,
                    'branch_code' => $branch->code,
                    'branch_name' => $branch->name,
                    'complete' => $existing->has($branch->id.':'.$type),
                ]);
            }
        }
        foreach ($companyTypes as $type => $definition) {
            $items->push([
                'type' => $type,
                'type_label' => $definition['label'],
                'branch_id' => null,
                'branch_code' => null,
                'branch_name' => 'كل الشركة',
                'complete' => $existing->has('0:'.$type),
            ]);
        }

        return [
            'complete' => $items->isNotEmpty() && $items->every('complete'),
            'completed_items' => $items->where('complete', true)->values()->all(),
            'missing_items' => $items->where('complete', false)->values()->all(),
            'completed_count' => $items->where('complete', true)->count(),
            'required_count' => $items->count(),
        ];
    }

    private function step(
        string $label,
        string $route,
        string $permission,
        bool $complete,
        array $params = [],
        array $details = []
    ): array {
        return compact('label', 'route', 'permission', 'complete', 'params', 'details');
    }
}
