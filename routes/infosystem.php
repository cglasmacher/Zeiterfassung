<?php

use App\Http\Controllers\Info\AdminController;
use App\Http\Controllers\Info\DisplayController;
use Illuminate\Support\Facades\Route;

Route::prefix('display/{slug}')->controller(DisplayController::class)->group(function () {
    Route::get('/connect', 'connect')->middleware('throttle:20,1');
    Route::get('/', 'show');
    Route::get('/data', 'data')->middleware('throttle:120,1');
    Route::get('/youtube/search', 'youtubeSearch')->middleware('throttle:12,1');
    Route::get('/events', 'events')->middleware('throttle:30,1');
    Route::get('/media/{item}', 'media');
});
Route::middleware(['auth', \App\Http\Middleware\InfoAdministrator::class])->group(function () {
    Route::get('/infosystem', fn () => \Inertia\Inertia::render('Info/Admin'))->name('info.admin');
    Route::prefix('api/infosystem')->controller(AdminController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/items', 'saveItem');
        Route::post('/items/{item}', 'saveItem');
        Route::delete('/items/{item}', 'deleteItem');
        Route::patch('/items/{item}/complete', 'complete');
        Route::post('/displays', 'saveDisplay');
        Route::put('/displays/{display}', 'saveDisplay');
        Route::post('/displays/{display}/rotate', 'rotate');
        Route::put('/integration', 'integration');
        Route::post('/sync', 'sync')->middleware('throttle:6,1');
    });
});
