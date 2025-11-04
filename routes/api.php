<?php

use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

Route::post('/clock-in', [TimeEntryController::class, 'clockIn']);
Route::post('/clock-out', [TimeEntryController::class, 'clockOut']);

Route::prefix('shifts')->group(function () {
    Route::get('/', [ShiftController::class, 'index']);
    Route::post('/', [ShiftController::class, 'store']);
    Route::put('/{id}', [ShiftController::class, 'update']);
    Route::delete('/{id}', [ShiftController::class, 'destroy']);
});

Route::prefix('summary')->group(function () {
    Route::get('/daily/{employeeId}/{date}', [SummaryController::class, 'daily']);
    Route::get('/monthly/{employeeId}/{year}/{month}', [SummaryController::class, 'monthly']);
    Route::get('/current', [SummaryController::class, 'current']);
});