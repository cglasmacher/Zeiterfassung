<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateAccountingImport
{
    public function handle(Request $request, Closure $next)
    {
        $token = (string) config('accounting.import_token');

        abort_if(strlen($token) < 32, 503, 'Buchhaltungsimport noch nicht konfiguriert.');
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401, 'Ungültiger Buchhaltungs-Importzugang.');

        return $next($request);
    }
}
