<?php

use App\Http\Controllers\Api\V1\ReservaYaWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('reservaya/eventos', [ReservaYaWebhookController::class, 'store'])
        ->middleware(['reservaya.signature', 'throttle:120,1'])
        ->name('api.v1.reservaya.eventos');
});
