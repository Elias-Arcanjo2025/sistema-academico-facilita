<?php

use App\Http\Controllers\Api\Control\ControlApiController;
use App\Http\Middleware\AuthenticateControlInstallation;
use App\Http\Middleware\AuthenticateHubPush;
use Illuminate\Support\Facades\Route;

Route::middleware([AuthenticateControlInstallation::class, 'throttle:60,1'])
    ->group(function () {
        Route::post('/heartbeat', [ControlApiController::class, 'heartbeat'])->name('control.api.heartbeat');
        Route::post('/alunos/sync', [ControlApiController::class, 'syncAlunos'])->name('control.api.alunos.sync');
        Route::get('/license', [ControlApiController::class, 'license'])->name('control.api.license');
    });

// Pedido do hub → escola (push sync)
Route::middleware([AuthenticateHubPush::class, 'throttle:10,1'])
    ->post('/run-sync', [ControlApiController::class, 'runSync'])
    ->name('control.api.run-sync');
