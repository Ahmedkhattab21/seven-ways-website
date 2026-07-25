<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next, string $parameter = 'branch')
    {
        $branch = $request->route($parameter);
        abort_unless($branch && $request->user()?->canAccessBranch($branch), 403);

        return $next($request);
    }
}
