<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class WorkspaceAccess
{
    private const PAGES = [
        'dashboard' => 'admin',
        'info.admin' => 'info',
        'haccp.dashboard' => 'haccp',
        'haccp.management' => 'admin',
        'haccp.documentation' => 'haccp',
        'haccp.maintenance' => 'haccp',
        'cash.handovers' => 'cash',
        'cash.days' => 'cash',
        'cash.vouchers' => 'vouchers',
        'timetracking.planner' => 'planner',
        'timetracking.daily' => 'admin',
        'timetracking.monthly' => 'admin',
        'timetracking.manual' => 'admin',
        'timetracking.manipulation' => 'admin',
        'accounting.documents' => 'admin',
        'timetracking.exports' => 'admin',
        'timetracking.settings' => 'admin',
    ];

    public static function can(User $user, string $module): bool
    {
        if ($module === 'info') {
            return $user->access_role === 'admin' && $user->is_info_admin;
        }
        if ($user->access_role === 'admin') {
            return true;
        }
        return $user->access_role === 'operations'
            && in_array($module, ['cash', 'vouchers', 'planner', 'haccp'], true);
    }

    public static function navigation(User $user): array
    {
        return array_keys(array_filter(self::PAGES, fn ($module) => self::can($user, $module)));
    }

    public static function allows(User $user, Request $request): bool
    {
        $name = $request->route()?->getName();
        if (isset(self::PAGES[$name])) {
            return self::can($user, self::PAGES[$name]);
        }
        if ($request->routeIs('profile.*', 'logout', 'password.*', 'verification.*')) {
            return true;
        }
        if ($user->access_role === 'admin') {
            return true;
        }
        if ($request->is('api/infosystem', 'api/infosystem/*')) {
            return self::can($user, 'info');
        }

        // HACCP operations users may execute assigned controls and read the operational records.
        // Master data, monthly closing, maintenance completion and deviation resolution stay admin-only.
        if ($request->is('api/haccp/roles*', 'api/haccp/devices*', 'api/haccp/templates*')) {
            return false;
        }
        if ($request->is('api/haccp/documentation/*/close')) {
            return false;
        }
        if ($request->is('api/haccp/maintenance-plans') && $request->isMethod('post')) {
            return false;
        }
        if ($request->is('api/haccp/maintenance-plans/*/complete')) {
            return false;
        }
        if ($request->is('api/haccp/deviations/*') && ! $request->isMethod('get')) {
            return false;
        }
        if ($request->is('api/haccp', 'api/haccp/*')) {
            return self::can($user, 'haccp');
        }

        if ($request->is('api/cash/vouchers', 'api/cash/vouchers/*')) {
            return self::can($user, 'vouchers');
        }
        if ($request->is('api/cash/*', 'timetracking/handovers', 'timetracking/cash-days')) {
            return self::can($user, 'cash');
        }
        if ($request->is('api/shifts', 'api/shifts/*', 'timetracking/planner', 'time-tracking/shift-planner')) {
            return self::can($user, 'planner');
        }
        return false;
    }
}
