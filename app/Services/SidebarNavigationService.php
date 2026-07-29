<?php

namespace App\Services;

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
        private ModuleRegistry $modules
    ) {
    }

    public function for(User $user): array
    {
        $permissions = $this->permissionSet($user);
        $seenUrls = [];
        $sections = [];

        foreach (config('sidebar', []) as $section) {
            $items = [];
            foreach ($section['items'] as $item) {
                $resolved = $this->resolveItem($item, $permissions);
                if (! $resolved || isset($seenUrls[$resolved['url']])) {
                    continue;
                }
                $seenUrls[$resolved['url']] = true;
                $items[] = $resolved;
            }

            if ($items !== []) {
                $section['items'] = $items;
                $section['active'] = collect($items)->contains('active', true);
                $sections[] = $section;
            }
        }

        $setup = $this->setupFor($user, $permissions);

        return compact('sections', 'setup');
    }

    private function resolveItem(array $item, array $permissions): ?array
    {
        if (! Route::has($item['route'])
            || ! $this->modules->enabledForRoute($item['route'], $this->request)
            || (isset($item['module']) && ! $this->modules->enabled($item['module']))
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

            return (string) $this->request->route($name) === $value;
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

    private function setupFor(User $user, array $permissions): ?array
    {
        $company = $user->company;
        if (! $company
            || ! isset($permissions['companies.view'], $permissions['branches.view'], $permissions['users.view'])) {
            return null;
        }

        $setup = $this->setupProgress->for($company);
        if ($setup['complete']) {
            return null;
        }

        $setup['steps'] = collect($setup['steps'])
            ->filter(fn (array $step) => isset($permissions[$step['permission']]) && Route::has($step['route']))
            ->map(function (array $step) {
                $step['url'] = route($step['route'], $step['params']);

                return $step;
            })->values()->all();

        return $setup;
    }
}
