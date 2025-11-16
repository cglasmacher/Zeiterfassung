<?php

use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ShiftTypeController;
use App\Http\Controllers\ManualTimeEntryController;
use App\Http\Controllers\TimeEntryManipulationController;
use App\Http\Controllers\LexwareExportController;
use App\Http\Controllers\DailyOverviewController;
use Illuminate\Support\Facades\Route;

Route::post('/clock-in', [TimeEntryController::class, 'clockIn']);
Route::post('/clock-out', [TimeEntryController::class, 'clockOut']);

Route::prefix('shifts')->group(function () {
    Route::get('/', [ShiftController::class, 'index']);
    Route::post('/', [ShiftController::class, 'store']);
    Route::put('/{id}', [ShiftController::class, 'update']);
    Route::delete('/{id}', [ShiftController::class, 'destroy']);
    Route::post('/copy-week', [ShiftController::class, 'copyWeek']);
    Route::post('/save-template', [ShiftController::class, 'saveTemplate']);
    Route::get('/templates', [ShiftController::class, 'getTemplates']);
    Route::post('/templates/{id}/load', [ShiftController::class, 'loadTemplate']);
    Route::delete('/templates/{id}', [ShiftController::class, 'deleteTemplate']);
    Route::post('/export-pdf', [ShiftController::class, 'exportPDF']);
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

Route::prefix('manual-time')->group(function () {
    Route::post('/clock-in', [ManualTimeEntryController::class, 'clockIn']);
    Route::post('/clock-out', [ManualTimeEntryController::class, 'clockOut']);
    Route::get('/open-entries', [ManualTimeEntryController::class, 'getOpenEntries']);
    Route::get('/today-entries', [ManualTimeEntryController::class, 'getTodayEntries']);
});

Route::prefix('time-manipulation')->group(function () {
    Route::get('/month-entries', [TimeEntryManipulationController::class, 'getMonthEntries']);
    Route::put('/entries/{id}', [TimeEntryManipulationController::class, 'updateEntry']);
    Route::post('/entries/{id}/split', [TimeEntryManipulationController::class, 'splitEntry']);
    Route::delete('/entries/{id}', [TimeEntryManipulationController::class, 'deleteEntry']);
    Route::get('/entries/{id}/audit', [TimeEntryManipulationController::class, 'getAuditLog']);
});

Route::prefix('lexware-export')->group(function () {
    Route::post('/preview', [LexwareExportController::class, 'preview']);
    Route::post('/export', [LexwareExportController::class, 'export']);
});

Route::prefix('daily-overview')->group(function () {
    Route::get('/', [DailyOverviewController::class, 'index']);
    Route::post('/entries/{id}/mark-paid', [DailyOverviewController::class, 'markAsPaid']);
    Route::post('/entries/{id}/unmark-paid', [DailyOverviewController::class, 'unmarkAsPaid']);
    Route::post('/shift-end-report', [DailyOverviewController::class, 'generateShiftEndReport']);
    Route::post('/recalculate-wages', [DailyOverviewController::class, 'recalculateWages']);
    Route::get('/debug-entry/{id}', [DailyOverviewController::class, 'debugEntry']);
    Route::get('/force-fix/{id}', [DailyOverviewController::class, 'forceFix']);
});