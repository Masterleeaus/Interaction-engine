<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use TitanZero\Interaction\Offline\SyncEngine;
use TitanZero\Interaction\Offline\OfflineDetector;

class SyncController
{
    private SyncEngine $syncEngine;
    private OfflineDetector $offlineDetector;

    public function __construct(SyncEngine $syncEngine, OfflineDetector $offlineDetector)
    {
        $this->syncEngine = $syncEngine;
        $this->offlineDetector = $offlineDetector;
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'status' => $this->offlineDetector->isOffline() ? 'offline' : 'online',
            'pending' => $this->syncEngine->getStatus()['pending'],
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        if ($this->offlineDetector->isOffline() && !$request->input('force', false)) {
            return response()->json(['error' => 'Device is offline. Use force=true to override.'], 400);
        }

        $results = $this->syncEngine->sync();
        return response()->json($results);
    }
}