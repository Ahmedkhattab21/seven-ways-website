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
use App\Models\ServicePackage;
use App\Models\Tax;
use App\Models\User;
use App\Models\Warehouse;

class CompanySetupProgressService
{
    public function __construct(private ModuleRegistry $modules)
    {
    }

    public function for(Company $company): array
    {
        $companyId = $company->getKey();
        $sequenceStatus = $this->documentSequenceStatus($companyId);
        $rolesStatus = $this->rolesStatus($companyId);
        $openingStatus = $this->openingBalanceStatus($company);
        $steps = [
            $this->step(
                'بيانات الشركة',
                'company.edit',
                'companies.view',
                filled($company->name) && filled($company->country_code) && filled($company->timezone)
            ),
            $this->step(
                'الفروع',
                'branches.index',
                'branches.view',
                Branch::query()->where('company_id', $companyId)->where('is_active', true)->exists()
            ),
            $this->step(
                'العملة الافتراضية',
                'reference.index',
                'settings.view',
                $company->currency_id !== null && $company->currency()->where('is_active', true)->exists(),
                ['currencies']
            ),
            $this->step(
                'الضرائب',
                'reference.index',
                'taxes.view',
                $company->is_taxable === false
                    || Tax::query()->where('company_id', $companyId)->where('is_active', true)->exists(),
                ['taxes'],
                ['status_label' => $company->is_taxable === false ? 'الشركة غير خاضعة للضريبة' : null]
            ),
            $this->step(
                'طرق الدفع',
                'reference.index',
                'payment_methods.view',
                PaymentMethod::query()->where('company_id', $companyId)->where('is_active', true)->exists(),
                ['payment-methods']
            ),
            $this->step(
                'السنة المالية',
                'accounting.fiscal-years.index',
                'accounting.fiscal_years.view',
                FiscalYear::query()
                    ->where('company_id', $companyId)
                    ->where('is_current', true)
                    ->where('status', 'open')
                    ->whereHas('periods', fn ($query) => $query->where('status', 'open'))
                    ->exists()
            ),
            $this->step(
                'تسلسل المستندات',
                'reference.index',
                'document_sequences.view',
                $sequenceStatus['complete'],
                ['document-sequences'],
                $sequenceStatus
            ),
            $this->step(
                'المستخدمون والأدوار',
                'users.index',
                'users.view',
                $rolesStatus['complete'],
                [],
                $rolesStatus
            ),
        ];

        if ($this->modules->enabled('basic_inventory')) {
            $steps[] = $this->step(
                'المستودعات',
                'warehouses.index',
                'warehouses.view',
                Warehouse::query()->where('company_id', $companyId)->where('is_active', true)->exists()
            );
        }

        $steps[] = $this->step(
            'المنتجات والخدمات',
            'catalog.index',
            'products.view',
            Product::query()->where('company_id', $companyId)->where('is_active', true)->exists()
                || Service::query()->where('company_id', $companyId)->where('is_active', true)->exists()
                || ServicePackage::query()->where('company_id', $companyId)->where('is_active', true)->exists(),
            [],
            [
                'blocking' => false,
                'status_label' => 'مطلوبة قبل إنشاء أول عرض سعر أو فاتورة',
            ]
        );
        $steps[] = $this->step(
            'الأرصدة الافتتاحية',
            'accounting.opening-balances.index',
            'accounting.opening_balances.view',
            $openingStatus['complete'],
            [],
            $openingStatus
        );

        $completed = collect($steps)->where('complete', true)->count();

        return [
            'steps' => $steps,
            'completed' => $completed,
            'total' => count($steps),
            'percentage' => (int) round(($completed / max(count($steps), 1)) * 100),
            'complete' => $completed === count($steps),
            'ready' => collect($steps)->filter(
                fn (array $step) => $step['details']['blocking'] ?? true
            )->every('complete'),
        ];
    }

    private function rolesStatus(int $companyId): array
    {
        $activeUsers = User::query()->where('company_id', $companyId)->where('status', 'active');
        $hasManager = (clone $activeUsers)->whereHas(
            'roles',
            fn ($query) => $query->where('is_active', true)->whereIn('name', ['company_owner', 'general_manager'])
        )->exists();
        $hasAccountant = (clone $activeUsers)->whereHas(
            'roles',
            fn ($query) => $query->where('is_active', true)->where('name', 'accountant')->where('scope', 'company')
        )->exists();
        $branches = Branch::query()->where('company_id', $companyId)->where('is_active', true)
            ->with(['responsibleUser.roles', 'responsibleUser.accessibleBranches'])->get();
        $missingBranches = $branches->filter(function (Branch $branch) use ($companyId) {
            $responsible = $branch->responsibleUser;

            return ! $responsible
                || $responsible->company_id !== $companyId
                || ! $responsible->isActive()
                || ! $responsible->roles->contains(
                    fn ($role) => $role->is_active && $role->name === 'branch_manager'
                )
                || ! $responsible->accessibleBranches->contains($branch->id)
                || Branch::query()
                    ->where('responsible_user_id', $responsible->id)
                    ->whereKeyNot($branch->id)
                    ->exists();
        })->pluck('name')->values()->all();

        return [
            'complete' => $hasManager && $hasAccountant && $branches->isNotEmpty() && $missingBranches === [],
            'has_manager' => $hasManager,
            'has_accountant' => $hasAccountant,
            'missing_branch_responsibles' => $missingBranches,
        ];
    }

    private function openingBalanceStatus(Company $company): array
    {
        $decision = $company->opening_balances_decision ?? 'pending';
        $hasPosted = OpeningBalanceDocument::query()
            ->where('company_id', $company->getKey())
            ->where('status', 'posted')
            ->exists();
        $complete = $decision === 'start_from_zero' || ($decision === 'entered' && $hasPosted);
        $label = match ($decision) {
            'start_from_zero' => 'البدء من أرصدة صفرية',
            'entered' => $hasPosted ? 'تم إدخال وترحيل الأرصدة' : 'تم اختيار الإدخال ولم يكتمل الترحيل',
            default => 'لم يتم اتخاذ قرار بعد',
        };

        return [
            'complete' => $complete,
            'decision' => $decision,
            'status_label' => $label,
        ];
    }

    private function documentSequenceStatus(int $companyId): array
    {
        $types = collect(config('document_sequences.types', []))
            ->filter(fn (array $definition) => ($definition['setup_required'] ?? false)
                && (! isset($definition['module']) || $this->modules->enabled($definition['module'])));
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
