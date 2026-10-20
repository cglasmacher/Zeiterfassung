<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn (Request $request) => redirect()->route($request->user() ? 'dashboard' : 'login'))->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');

    Route::prefix('team')->group(function () {
        Route::get('/zeiten', fn () => Inertia::render('TimeTracking/DailyOverview'))->name('timetracking.daily');
        Route::get('/monat', fn () => Inertia::render('TimeTracking/MonthlyOverview'))->name('timetracking.monthly');
        Route::get('/erfassung', fn () => Inertia::render('TimeTracking/ManualTimeEntry'))->name('timetracking.manual');
        Route::get('/korrekturen', fn () => Inertia::render('TimeTracking/TimeManipulation'))->name('timetracking.manipulation');
    });
    Route::get('/dienstplan', fn () => Inertia::render('TimeTracking/ShiftPlanner'))->name('timetracking.planner');

    Route::prefix('kasse')->group(function () {
        Route::get('/uebergaben', fn () => Inertia::render('TimeTracking/Cash/Accounting', ['mode' => 'handover']))->name('cash.handovers');
        Route::get('/tagesabschluss', fn () => Inertia::render('TimeTracking/Cash/Accounting', ['mode' => 'day']))->name('cash.days');
        Route::get('/gutscheinverkauf', fn () => Inertia::render('TimeTracking/Cash/Vouchers'))->name('cash.vouchers');
    });

    Route::get('/buchhaltung/belege', fn () => Inertia::render('Accounting/Documents'))->name('accounting.documents');

    Route::get('/exporte/lohn', fn () => Inertia::render('TimeTracking/Exports'))->name('timetracking.exports');
    Route::get('/einstellungen', function (Request $request) {
        $tab = $request->query('tab', 'general');
        abort_unless(in_array($tab, ['general', 'departments', 'shift-types', 'employees'], true), 404);

        return Inertia::render('TimeTracking/Settings', ['initialTab' => $tab]);
    })->name('timetracking.settings');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Keep existing bookmarks and links working while using one canonical page per task.
    $legacyPages = [
        '/timetracking' => 'dashboard', '/timetracking/daily' => 'timetracking.daily',
        '/timetracking/monthly' => 'timetracking.monthly', '/timetracking/planner' => 'timetracking.planner',
        '/timetracking/manual-entry' => 'timetracking.manual', '/timetracking/time-manipulation' => 'timetracking.manipulation',
        '/timetracking/exports' => 'timetracking.exports', '/timetracking/settings' => 'timetracking.settings',
        '/timetracking/handovers' => 'cash.handovers', '/timetracking/cash-days' => 'cash.days',
        '/time-tracking/settings' => 'timetracking.settings', '/time-tracking/shift-planner' => 'timetracking.planner',
        '/time-tracking/daily-overview' => 'timetracking.daily', '/time-tracking/lexware-export' => 'timetracking.exports',
    ];
    foreach ($legacyPages as $path => $destination) {
        Route::get($path, fn () => redirect()->route($destination));
    }

    // Browser administration APIs use the same session and CSRF protection as their pages.
    Route::prefix('api')->group(base_path('routes/admin-api.php'));
});

require __DIR__.'/auth.php';
