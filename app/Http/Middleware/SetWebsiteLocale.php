<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetWebsiteLocale
{
    public function handle(Request $request, Closure $next)
    {
        $requestedLocale = $request->query('lang');

        if (is_string($requestedLocale) && in_array($requestedLocale, ['ar', 'en'], true)) {
            $request->session()->put('website_locale', $requestedLocale);
        }

        $locale = $request->session()->get('website_locale', config('app.locale', 'ar'));

        if (! in_array($locale, ['ar', 'en'], true)) {
            $locale = 'ar';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
