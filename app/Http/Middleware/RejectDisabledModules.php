<?php

namespace App\Http\Middleware;

use App\Services\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectDisabledModules
{
    public function __construct(private ModuleRegistry $modules)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->modules->enabledForRoute($request->route()?->getName(), $request), 404);

        return $next($request);
    }
}
