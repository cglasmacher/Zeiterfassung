<?php

namespace App\Http\Middleware;

use App\Support\WorkspaceAccess;
use Closure;
use Illuminate\Http\Request;

class AuthorizeWorkspace
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && in_array('auth', $request->route()?->gatherMiddleware() ?? [], true)) {
            abort_unless(WorkspaceAccess::allows($request->user(), $request), 403, 'Für diesen Bereich fehlt die Berechtigung.');
        }
        $response = $next($request);
        if ($request->user()?->access_role === 'operations' && $request->is('api/shifts', 'api/shifts/*') && $response instanceof \Illuminate\Http\JsonResponse) {
            $response->setData($this->planningData($response->getData(true)));
        }

        return $response;
    }

    private function planningData(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach (['hourly_rate', 'rfid_tag', 'email', 'phone', 'cash_payment'] as $key) {
            unset($value[$key]);
        }

        return array_map(fn ($entry) => $this->planningData($entry), $value);
    }
}
