<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromRequest
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->header('X-Locale')
            ?? $request->query('lang')
            ?? $request->getPreferredLanguage(['de', 'en'])
            ?? config('app.locale');

        $allowed = ['de', 'en'];
        app()->setLocale(in_array($locale, $allowed, true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
