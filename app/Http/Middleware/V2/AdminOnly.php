<?php

namespace App\Http\Middleware\V2;

use Closure;
use Illuminate\Http\Request;

/** Website content, settings, users and study types: admins only (engineers review studies). */
class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Admins only.');

        return $next($request);
    }
}
