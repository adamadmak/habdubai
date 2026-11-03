<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The website says which language its visitor reads (X-Locale: am, ti or en),
 * so validation messages and owner messages come back in it.
 */
class UseRequestLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = strtolower((string) $request->header('X-Locale', ''));

        // Always set, so a long-running worker never carries one visitor's language to the next.
        app()->setLocale(in_array($locale, ['am', 'ti', 'en'], true) ? $locale : (string) config('app.fallback_locale', 'en'));

        return $next($request);
    }
}
