<?php

namespace App\Http\Middleware\V2;

use Closure;
use Illuminate\Http\Request;

/**
 * The dashboard speaks Arabic or English — the user's choice (the language button in the top bar),
 * remembered in the session. The review tool and the issued PDF stay in English.
 */
class AdminLocale
{
    public function handle(Request $request, Closure $next)
    {
        $loc = $request->session()->get('admin_locale', config('v2.admin_locale', 'ar'));
        app()->setLocale(in_array($loc, ['ar', 'en'], true) ? $loc : 'en');

        return $next($request);
    }
}
