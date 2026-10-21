<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InfoAdministrator
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->is_info_admin, 403);

        return $next($request);
    }
}
