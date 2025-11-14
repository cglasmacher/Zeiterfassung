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
    Route::get('/planner', fn() => Inertia::render('TimeTracking/ShiftPlannerPositions'))->name('timetracking.planner');
    Route::get('/manual-entry', fn() => Inertia::render('TimeTracking/ManualTimeEntry'))->name('timetracking.manual');
    Route::get('/exports', fn() => Inertia::render('TimeTracking/Exports'))->name('timetracking.exports');
    Route::get('/settings', fn() => Inertia::render('TimeTracking/Settings'))->name('timetracking.settings');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
