<?php

namespace App\Http\Middleware\V2;

use Closure;
use Illuminate\Http\Request;

/** The dashboard speaks Arabic or English: ?lang=xx switches and is remembered apart from the website's language. */
class AdminLocale
{
    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->query('lang'), ['ar', 'en'], true)) {
            $request->session()->put('v2_admin_locale', $request->query('lang'));
        }
        app()->setLocale($request->session()->get('v2_admin_locale', 'en'));

        return $next($request);
    }
}
