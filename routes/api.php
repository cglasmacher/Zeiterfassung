<?php

use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ShiftTypeController;
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

Route::prefix('employees')->group(function () {
    Route::get('/', [EmployeeController::class, 'index']);
    Route::post('/', [EmployeeController::class, 'store']);
    Route::put('/{id}', [EmployeeController::class, 'update']);
    Route::delete('/{id}', [EmployeeController::class, 'destroy']);
});

Route::prefix('departments')->group(function () {
    Route::get('/', [DepartmentController::class, 'index']);
    Route::post('/', [DepartmentController::class, 'store']);
    Route::put('/{id}', [DepartmentController::class, 'update']);
    Route::delete('/{id}', [DepartmentController::class, 'destroy']);
});

Route::prefix('shift-types')->group(function () {
    Route::get('/', [ShiftTypeController::class, 'index']);
    Route::post('/', [ShiftTypeController::class, 'store']);
    Route::put('/{id}', [ShiftTypeController::class, 'update']);
    Route::delete('/{id}', [ShiftTypeController::class, 'destroy']);
});