<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateCashDocumentExport
{
    public function handle(Request $request, Closure $next)
    {
        $token = (string) config('cash.archive.export_token');
        abort_if(strlen($token) < 32, 503, 'PDF-Archivexport ist noch nicht konfiguriert.');
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401, 'Ungültiger Archiv-Zugang.');

        return $next($request);
    }
}
