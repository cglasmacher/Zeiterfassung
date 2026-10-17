<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Hauptgruppe Zeiterfassung
Route::middleware(['auth'])->prefix('timetracking')->group(function () {
    Route::get('/', fn() => Inertia::render('TimeTracking/Dashboard'))->name('timetracking.dashboard');
    Route::get('/daily', fn() => Inertia::render('TimeTracking/DailyOverview'))->name('timetracking.daily');
    Route::get('/monthly', fn() => Inertia::render('TimeTracking/MonthlyOverview'))->name('timetracking.monthly');
    Route::get('/planner', fn() => Inertia::render('TimeTracking/ShiftPlanner'))->name('timetracking.planner');
    Route::get('/manual-entry', fn() => Inertia::render('TimeTracking/ManualTimeEntry'))->name('timetracking.manual');
    Route::get('/time-manipulation', fn() => Inertia::render('TimeTracking/TimeManipulation'))->name('timetracking.manipulation');
    Route::get('/exports', fn() => Inertia::render('TimeTracking/Exports'))->name('timetracking.exports');
    Route::get('/settings', fn() => Inertia::render('TimeTracking/Settings'))->name('timetracking.settings');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Session authentication and CSRF protection also apply to the cash JSON endpoints.
Route::middleware('auth')->group(function () {
    Route::get('/timetracking/handovers', fn () => Inertia::render('TimeTracking/Cash/Accounting', ['mode' => 'handover']));
    Route::get('/timetracking/cash-days', fn () => Inertia::render('TimeTracking/Cash/Accounting', ['mode' => 'day']));
    Route::prefix('api/cash')->controller(\App\Http\Controllers\CashAccountingController::class)->group(function () {
        Route::get('/days', 'index');
        Route::post('/days', 'store');
        Route::get('/days/{id}', 'show');
        Route::post('/days/{id}/handovers', 'handover');
        Route::put('/days/{id}', 'save');
        Route::post('/days/{id}/finalize', 'finalize');
        Route::get('/days/{id}/pdf', 'pdf');
        Route::post('/days/{id}/deliver', 'deliver');
        Route::post('/vectron/sync', 'vectronSync');
        Route::get('/vectron/summary', 'vectronSummary');
    });
});

Route::middleware('auth')->prefix('api/settings')->group(function () {
    Route::get('/', [\App\Http\Controllers\SettingsController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\SettingsController::class, 'update']);
});
