<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use TitanZero\Interaction\LocalIntelligence\LocalBrain;

final class LocalIntelligenceController
{
    public function __construct(private readonly LocalBrain $brain) {}

    public function process(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'context' => ['sometimes', 'array'],
        ]);
        $user = $request->user();
        $context = array_replace((array) ($validated['context'] ?? []), [
            'user_id' => data_get($user, 'id'),
            'tenant_id' => data_get($user, 'tenant_id') ?? data_get($user, 'team_id'),
            'device_id' => $request->header('X-Device-ID'),
        ]);

        return response()->json($this->brain->process((string) $validated['message'], $context));
    }
}
