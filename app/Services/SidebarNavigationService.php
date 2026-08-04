<?php

namespace App\Services;

use App\Models\AccountingSetting;
use App\Models\PaymentMethodAccountMapping;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class SidebarNavigationService
{
    private const REPORT_MODULES = [
        'reports.financial.view' => 'financial',
        'reports.sales.view' => 'sales',
        'reports.purchases.view' => 'purchases',
        'reports.inventory.view' => 'inventory',
        'reports.receivables.view' => 'receivables',
        'reports.payables.view' => 'payables',
        'reports.treasury.view' => 'treasury',
        'reports.employee_finance.view' => 'employee-finance',
        'reports.approvals.view' => 'approvals',
        'reports.audit.view' => 'audit',
    ];

    public function __construct(
        private Request $request,
        private CompanySetupProgressService $setupProgress,
        private ModuleRegistry $modules,
        private UserDashboardProfileResolver $profiles
    ) {
    }

    public function for(User $user): array
    {
        $permissions = $this->permissionSet($user);
        $profile = $this->profiles->profile($user);
        $seenUrls = [];
        $sections = [];

        foreach (config('sidebar', []) as $section) {
            if (! $this->supportsProfile($section, $profile)
                || (isset($section['module']) && ! $this->modules->enabled($section['module']))) {
                continue;
            }
            $items = [];
            foreach ($section['items'] as $item) {
                $resolved = $this->resolveItem($item, $permissions, $profile);
                if (! $resolved || isset($seenUrls[$resolved['url']])) {
                    continue;
                }
                $seenUrls[$resolved['url']] = true;
                $items[] = $resolved;
            }

            if ($items !== []) {
                $section['items'] = $items;
                $section['label'] = $section['labels'][$profile] ?? $section['label'];
                $section['active'] = collect($items)->contains('active', true);
                $sections[] = $section;
            }
        }

        $setup = $this->setupFor($user, $permissions, $profile);
        $financialAlert = $this->financialAlertFor($user, $permissions, $profile);

        return compact('sections', 'setup', 'financialAlert');
    }

    private function resolveItem(array $item, array $permissions, string $profile): ?array
    {
        if (! Route::has($item['route'])
            || ! $this->modules->enabledForRoute($item['route'], $this->request)
            || (isset($item['module']) && ! $this->modules->enabled($item['module']))
            || (($item['system_admin_only'] ?? false) && $profile !== 'system_admin')
            || ! $this->supportsProfile($item, $profile)
            || ! $this->isAllowed($item, $permissions)) {
            return null;
        }

        $params = $item['params'] ?? [];
        if ($item['report_center'] ?? false) {
            $module = collect(self::REPORT_MODULES)->first(
                fn (string $slug, string $permission) => isset($permissions[$permission])
            );
            if (! $module) {
                return null;
            }
            $params = [$module];
        }

        $item['url'] = route($item['route'], $params);
        $item['label'] = $item['labels'][$profile] ?? $item['label'];
        $item['active'] = $this->isActive($item);

        return $item;
    }

    private function isAllowed(array $item, array $permissions): bool
    {
        if ($item['report_center'] ?? false) {
            return collect(array_keys(self::REPORT_MODULES))->contains(
                fn (string $permission) => isset($permissions[$permission])
            );
        }
        if (isset($item['permission'])) {
            return isset($permissions[$item['permission']]);
        }
        if (isset($item['permissions_any'])) {
            return collect($item['permissions_any'])->contains(
                fn (string $permission) => isset($permissions[$permission])
            );
        }

        return true;
    }

    private function isActive(array $item): bool
    {
        if (! $this->request->routeIs(...($item['active'] ?? [$item['route']]))) {
            return false;
        }
        if (isset($item['active_param'])) {
            [$name, $value] = $item['active_param'];
            $routeValue = $this->request->route($name);

            if ($routeValue === null) {
                return true;
            }

            return (string) $routeValue === $value;
        }

        return true;
    }

    private function permissionSet(User $user): array
    {
        return $user->roles()
            ->where('roles.is_active', true)
            ->with('permissions:id,name')
            ->get()
            ->flatMap->permissions
            ->pluck('name')
            ->unique()
            ->flip()
            ->all();
    }

    private function setupFor(User $user, array $permissions, string $profile): ?array
    {
        $company = $user->company;
        if (! in_array($profile, ['manager', 'system_admin'], true)
            || ! $company
            || ! isset($permissions['companies.view'], $permissions['branches.view'], $permissions['users.view'])) {
            return null;
        }

        $setup = $this->setupProgress->for($company);
        $setup['steps'] = collect($setup['steps'])
            ->filter(fn (array $step) => isset($permissions[$step['permission']]) && Route::has($step['route']))
            ->map(function (array $step) {
                $step['url'] = route($step['route'], $step['params']);

                return $step;
            })->values()->all();

        return $setup;
    }

    private function financialAlertFor(User $user, array $permissions, string $profile): ?array
    {
        if ($profile !== 'accountant' || ! $user->company) {
            return null;
        }

        $setup = $this->setupProgress->for($user->company);
        $routes = [
            'accounting.fiscal-years.index',
            'reference.index',
            'accounting.opening-balances.index',
        ];
        $items = collect($setup['steps'])
            ->filter(fn (array $step) => ! $step['complete']
                && in_array($step['route'], $routes, true)
                && isset($permissions[$step['permission']])
                && Route::has($step['route']))
            ->map(fn (array $step) => [
                'label' => $step['label'],
                'url' => route($step['route'], $step['params']),
            ]);

        if (isset($permissions['accounting.settings.view'])
            && ! AccountingSetting::query()->where('company_id', $user->company_id)->exists()) {
            $items->push([
                'label' => 'إعدادات المحاسبة',
                'url' => route('accounting.settings.edit'),
            ]);
        }

        if ((isset($permissions['accounting.mappings.payment_methods'])
                || isset($permissions['accounting.mappings.products']))
            && ! PaymentMethodAccountMapping::query()->where('company_id', $user->company_id)->exists()) {
            $items->push([
                'label' => 'ربط الحسابات',
                'url' => route('accounting.mappings.index'),
            ]);
        }

        return $items->isEmpty() ? null : ['items' => $items->unique('url')->values()->all()];
    }

    private function supportsProfile(array $entry, string $profile): bool
    {
        return $profile === 'generic'
            || ! isset($entry['profiles'])
            || in_array($profile, $entry['profiles'], true);
    }
}
