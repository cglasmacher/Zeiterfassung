<?php

use App\Http\Controllers\ReservationBookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/reservierungen', fn () => Inertia::render('Reservations/Book'))->name('reservations.book');
Route::get('/reservierungen/verwaltung', function (Request $request) {
    abort_unless($request->user()?->access_role === 'admin', 403);

    return Inertia::render('Reservations/Management');
})->name('reservations.management');
Route::prefix('api/reservations')->controller(ReservationBookController::class)->group(function () {
    Route::get('/book', 'index');
    Route::get('/mails', [\App\Http\Controllers\ReservationMailController::class, 'index']);
    Route::put('/mails/settings', [\App\Http\Controllers\ReservationMailController::class, 'settings']);
    Route::post('/mails/{id}/retry', [\App\Http\Controllers\ReservationMailController::class, 'retry'])->whereUuid('id');
    Route::get('/widget/settings', [\App\Http\Controllers\ReservationWidgetController::class, 'settings']);
    Route::put('/widget/settings', [\App\Http\Controllers\ReservationWidgetController::class, 'updateSettings']);
    Route::post('/resmio/sync', 'syncResmio')->middleware('throttle:6,1');
    Route::get('/resmio/resources', 'resmioResources');
    Route::put('/resmio/resources/{resource}', 'mapResmioResource')->whereNumber('resource');
    Route::get('/availability', 'availability');
    Route::post('/bookings', 'store');
    Route::put('/bookings/{booking}', 'update');
    Route::post('/bookings/{booking}/promote', 'promote');
    Route::get('/bookings/{booking}/events', 'events');
    Route::get('/bookings/{booking}/table-options', 'tableOptions');
    Route::put('/settings', 'settings');
    Route::post('/combinations', 'storeCombination');
    Route::post('/combinations/import', 'importCombinations');
    Route::delete('/combinations/{combination}', 'deleteCombination');
    Route::post('/blocks', 'storeBlock');
    Route::delete('/blocks/{block}', 'deleteBlock');
});
