<?php

namespace App\Http\Middleware\V2;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** A deactivated office user is logged out on the next request. */
class ActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $request->user()->active === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('v2.admin.login')->withErrors(['email' => 'This account is deactivated.']);
        }

        return $next($request);
    }
}
