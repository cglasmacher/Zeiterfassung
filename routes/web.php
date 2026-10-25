<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::domain(config('infosystem.stream_host'))->get('/', fn () => redirect('/display/kitchen'));
require __DIR__.'/infosystem.php';
Route::get('/', fn (Request $request) => redirect()->route($request->user() ? $request->user()->landingRoute() : 'login'))->name('home');
Route::middleware('auth')->group(function () {
    require __DIR__.'/reservations.php';
    Route::get('/reservierungen/tischplan', function (Request $request) {
        abort_unless($request->user()?->access_role === 'admin', 403);
        return Inertia::render('Reservations/FloorPlan');
    })->name('reservations.floor-plan');
    Route::prefix('api/reservations/floor-plan')->controller(\App\Http\Controllers\ReservationFloorPlanController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/rooms', 'store');
        Route::put('/rooms/{room}', 'update');
    });
    Route::get('/dashboard',[DashboardController::class,'show'])->name('dashboard');
    Route::prefix('team')->group(function(){Route::get('/zeiten',fn()=>Inertia::render('TimeTracking/DailyOverview'))->name('timetracking.daily');Route::get('/monat',fn()=>Inertia::render('TimeTracking/MonthlyOverview'))->name('timetracking.monthly');Route::get('/erfassung',fn()=>Inertia::render('TimeTracking/ManualTimeEntry'))->name('timetracking.manual');Route::get('/korrekturen',fn()=>Inertia::render('TimeTracking/TimeManipulation'))->name('timetracking.manipulation');});
    Route::get('/dienstplan',fn()=>Inertia::render('TimeTracking/ShiftPlanner'))->name('timetracking.planner');
    Route::prefix('kasse')->group(function(){Route::get('/uebergaben',fn()=>Inertia::render('TimeTracking/Cash/Accounting',['mode'=>'handover']))->name('cash.handovers');Route::get('/tagesabschluss',fn()=>Inertia::render('TimeTracking/Cash/Accounting',['mode'=>'day']))->name('cash.days');Route::get('/gutscheinverkauf',fn()=>Inertia::render('TimeTracking/Cash/Vouchers'))->name('cash.vouchers');});
    Route::get('/haccp',fn()=>Inertia::render('Haccp/Dashboard'))->name('haccp.dashboard');
    Route::get('/haccp/qr/{token}',fn(string $token)=>Inertia::render('Haccp/DeviceQr',['qrToken'=>$token]))->where('token','[A-Za-z0-9]{40}')->name('haccp.device-qr');
    Route::get('/haccp/qr-etiketten',function(Request $request){abort_unless($request->user()?->access_role==='admin',403);return Inertia::render('Haccp/QrLabels');})->name('haccp.qr-labels');
    Route::get('/haccp/kontrollen',fn()=>Inertia::render('Haccp/Operations'))->name('haccp.operations');
    Route::get('/haccp/checklisten',fn()=>Inertia::render('Haccp/Checklists'))->name('haccp.checklists');
    Route::get('/haccp/abweichungen-wartungen',fn()=>Inertia::render('Haccp/Compliance'))->name('haccp.compliance');
    Route::get('/haccp/dokumentation',fn()=>Inertia::render('Haccp/Documentation'))->name('haccp.documentation');
    Route::get('/haccp/korrekturen',fn()=>Inertia::render('Haccp/Corrections'))->name('haccp.corrections');
    Route::get('/haccp/verwaltung',fn()=>Inertia::render('Haccp/Management'))->name('haccp.management');
    Route::get('/buchhaltung/belege',fn()=>Inertia::render('Accounting/Documents'))->name('accounting.documents');
    Route::get('/exporte/lohn',fn()=>Inertia::render('TimeTracking/Exports'))->name('timetracking.exports');
    Route::get('/einstellungen',function(Request $request){$tab=$request->query('tab','general');abort_unless(in_array($tab,['general','departments','shift-types','employees'],true),404);return Inertia::render('TimeTracking/Settings',['initialTab'=>$tab]);})->name('timetracking.settings');
    Route::get('/profile',[ProfileController::class,'edit'])->name('profile.edit');Route::patch('/profile',[ProfileController::class,'update'])->name('profile.update');Route::delete('/profile',[ProfileController::class,'destroy'])->name('profile.destroy');
    $legacyPages=['/timetracking'=>'dashboard','/timetracking/daily'=>'timetracking.daily','/timetracking/monthly'=>'timetracking.monthly','/timetracking/planner'=>'timetracking.planner','/timetracking/manual-entry'=>'timetracking.manual','/timetracking/time-manipulation'=>'timetracking.manipulation','/timetracking/exports'=>'timetracking.exports','/timetracking/settings'=>'timetracking.settings','/timetracking/handovers'=>'cash.handovers','/timetracking/cash-days'=>'cash.days','/time-tracking/settings'=>'timetracking.settings','/time-tracking/shift-planner'=>'timetracking.planner','/time-tracking/daily-overview'=>'timetracking.daily','/time-tracking/lexware-export'=>'timetracking.exports'];foreach($legacyPages as $path=>$destination)Route::get($path,fn()=>redirect()->route($destination));
    Route::prefix('api')->group(base_path('routes/admin-api.php'));
});
require __DIR__.'/auth.php';

