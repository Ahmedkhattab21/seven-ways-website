<?php

namespace App\Http\Middleware;

use App\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;

class EnsureCompanyAccess
{
    public function handle(Request $request, Closure $next, string $parameter = 'company')
    {
        $company = $request->route($parameter);
        abort_unless($company && (int) $company->getKey() === app(TenantContext::class)->companyId(), 403);

        return $next($request);
    }
}
