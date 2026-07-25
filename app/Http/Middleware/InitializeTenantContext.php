<?php

namespace App\Http\Middleware;

use App\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;

class InitializeTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            app(TenantContext::class)->initialize($request->user());
        }

        return $next($request);
    }
}
