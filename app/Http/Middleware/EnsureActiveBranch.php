<?php

namespace App\Http\Middleware;

use App\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;

class EnsureActiveBranch
{
    public function handle(Request $request, Closure $next)
    {
        if (! app(TenantContext::class)->branch()) {
            abort(403, 'لا يوجد فرع نشط متاح لهذا المستخدم.');
        }

        return $next($request);
    }
}
