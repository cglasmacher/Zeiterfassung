<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsolateStreamHost
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->getHost() === config('infosystem.stream_host')) {
            abort_unless($request->is('/') || $request->is('display/*') || $request->is('up'), 404);
        }

        return $next($request);
    }
}
