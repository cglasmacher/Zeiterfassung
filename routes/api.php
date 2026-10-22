<?php

use App\Http\Controllers\SummaryController;
use App\Http\Controllers\TimeEntryController;
use Illuminate\Support\Facades\Route;

// Device-facing clock endpoints retain their existing URLs and RFID authentication.
Route::post('/clock-in', [TimeEntryController::class, 'clockIn']);
Route::post('/clock-out', [TimeEntryController::class, 'clockOut']);
Route::get('/summary/current', [SummaryController::class, 'current']);

// Mobile employee app API. Kept separate from browser administration and RFID device APIs.
Route::prefix('app/v1')->group(function () {
    Route::post('/login', [\App\Http\Controllers\App\MobileAuthController::class, 'login'])
        ->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [\App\Http\Controllers\App\MobileAuthController::class, 'logout']);
        Route::get('/me', [\App\Http\Controllers\App\MobileAuthController::class, 'me']);
        Route::get('/dashboard', [\App\Http\Controllers\App\MobileDashboardController::class, 'dashboard']);
        Route::get('/shifts', [\App\Http\Controllers\App\MobileDashboardController::class, 'shifts']);
    });
});

// Dedicated read-only-document ingestion; unrelated to employee clock endpoints.
Route::post('/integrations/vectron/z-reports', [\App\Http\Controllers\ZReportController::class, 'store'])
    ->middleware([\App\Http\Middleware\AuthenticateZReportImport::class, 'throttle:30,1']);

Route::post('/integrations/accounting/documents', [\App\Http\Controllers\AccountingReviewController::class, 'store'])
    ->middleware([\App\Http\Middleware\AuthenticateAccountingImport::class, 'throttle:60,1']);

Route::prefix('integrations/cash-documents')
    ->middleware([\App\Http\Middleware\AuthenticateCashDocumentExport::class, 'throttle:120,1'])
    ->controller(\App\Http\Controllers\CashDocumentExportController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/{kind}/{id}/pdf', 'pdf')->whereIn('kind', ['handover', 'day'])->whereNumber('id');
        Route::post('/{kind}/{id}/ack', 'acknowledge')->whereIn('kind', ['handover', 'day'])->whereNumber('id');
    });

Route::post('/integrations/resmio/webhook', \App\Http\Controllers\Info\ResmioWebhookController::class)->middleware('throttle:120,1');
