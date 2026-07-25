<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveCompany
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->company?->is_active, 403, 'الشركة غير نشطة.');

        return $next($request);
    }
}
