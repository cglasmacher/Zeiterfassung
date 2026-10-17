<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateZReportImport
{
    public function handle(Request $request, Closure $next)
    {
        $token = (string) config('cash.z_reports.import_token');
        abort_if(strlen($token) < 32, 503, 'Z-Bericht-Eingang noch nicht konfiguriert.');
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401, 'Ungültiger Import-Zugang.');

        return $next($request);
    }
}
