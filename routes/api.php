<?php

use Illuminate\Support\Facades\Route;
use TitanZero\Interaction\Http\Controllers\InteractionController;
use TitanZero\Interaction\Http\Controllers\LocalIntelligenceController;
use TitanZero\Interaction\Http\Controllers\OfflineCommandController;
use TitanZero\Interaction\Http\Controllers\WizardController;
use TitanZero\Interaction\Http\Controllers\TemplateController;
use TitanZero\Interaction\Http\Controllers\CognitiveEventController;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api/interaction')
    ->name('api.interaction.')
    ->group(function (): void {
        Route::get('/{interactionId}', [InteractionController::class, 'show'])->where('interactionId', '^(?!wizards$|local-intelligence$).+')->name('show');
        Route::post('/{runId}/process', [InteractionController::class, 'process'])->whereNumber('runId')->name('process');

        Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::get('/templates/{templateId}', [TemplateController::class, 'show'])->name('templates.show');

        Route::get('/wizards', [WizardController::class, 'index'])->name('wizards.index');
        Route::post('/wizards/{wizardId}/start', [WizardController::class, 'start'])->name('wizards.start');
        Route::get('/wizard-sessions/{sessionId}', [WizardController::class, 'show'])->name('wizards.show');
        Route::post('/wizard-sessions/{sessionId}/steps', [WizardController::class, 'submitStep'])->name('wizards.steps.submit');

        Route::post('/local-intelligence/process', [LocalIntelligenceController::class, 'process'])->name('local-intelligence.process');
        Route::post('/offline-commands', [OfflineCommandController::class, 'store'])->name('offline-commands.store');

        Route::get('/cognitive-events/{correlationId}', [CognitiveEventController::class, 'timeline'])->whereUuid('correlationId')->name('cognitive-events.timeline');
        Route::post('/cognitive-events/corrections', [CognitiveEventController::class, 'correction'])->name('cognitive-events.corrections');
        Route::post('/cognitive-events/outcomes', [CognitiveEventController::class, 'outcome'])->name('cognitive-events.outcomes');
    });
