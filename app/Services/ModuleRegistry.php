<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ModuleRegistry
{
    public function enabled(string $module): bool
    {
        return (bool) config("modules.{$module}.enabled", false);
    }

    public function enabledForRoute(?string $routeName, ?Request $request = null): bool
    {
        if (! $routeName) {
            return true;
        }

        foreach (config('modules', []) as $module => $settings) {
            if (($settings['enabled'] ?? false) || ! $this->matches($routeName, $settings, $request)) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function matches(string $routeName, array $settings, ?Request $request): bool
    {
        if (collect($settings['routes'] ?? [])->contains(
            fn (string $pattern) => Str::is($pattern, $routeName)
        )) {
            return true;
        }

        if ($routeName !== 'inventory.index' || ! $request) {
            return false;
        }

        $section = (string) ($request->route('section') ?? $request->query('section'));

        return in_array($section, $settings['inventory_sections'] ?? [], true);
    }
}
