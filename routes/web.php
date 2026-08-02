<?php

use Illuminate\Support\Facades\Route;
use TitanZero\Interaction\Http\Controllers\InteractionController;

Route::middleware(['web', 'auth'])
    ->prefix('interaction')
    ->name('interaction.')
    ->group(function (): void {
        Route::get('/{interactionId}', [InteractionController::class, 'show'])->name('show');
        Route::post('/{runId}/process', [InteractionController::class, 'process'])->name('process');
    });
