<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DashboardLandingService
{
    private const LANDING_ROUTES = ['dashboard', 'dashboards.executive', 'accounting.dashboard'];

    public function __construct(private UserDashboardProfileResolver $profiles)
    {
    }

    public function destination(User $user): string
    {
        $route = $this->profiles->routeName($user);

        return $route && $this->profiles->canAccessRoute($user, $route) ? route($route) : URL::to('/');
    }

    public function intendedOrDefault(Request $request, User $user): string
    {
        $intended = $request->session()->pull('url.intended');
        if (! is_string($intended) || ! $this->isAllowedIntended($intended, $request, $user)) {
            return $this->destination($user);
        }

        return $intended;
    }

    private function isAllowedIntended(string $url, Request $request, User $user): bool
    {
        $parts = parse_url($url);
        if ($parts === false || (isset($parts['host']) && $parts['host'] !== $request->getHost())) {
            return false;
        }

        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        try {
            /** @var Route $route */
            $route = app('router')->getRoutes()->match(Request::create($path, 'GET'));
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return false;
        }

        $name = $route->getName();
        if (! $name || in_array($name, ['login', 'logout'], true)) {
            return false;
        }
        if (in_array($name, self::LANDING_ROUTES, true)) {
            return $this->profiles->canAccessRoute($user, $name);
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (str_starts_with($middleware, 'permission:')) {
                $permission = substr($middleware, 11);
                if (! $user->hasRole('system_admin') && ! $user->hasPermission($permission)) {
                    return false;
                }
            }
        }

        return true;
    }
}
