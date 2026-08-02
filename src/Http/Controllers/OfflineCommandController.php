<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use TitanZero\Interaction\Contracts\CommandBusInterface;

final class OfflineCommandController
{
    public function __construct(private readonly CommandBusInterface $commands) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'uuid'],
            'capability' => ['required', 'string', 'max:150'],
            'payload' => ['required', 'array'],
            'metadata' => ['required', 'array'],
            'metadata.tenant_id' => ['required', 'string', 'max:150'],
            'metadata.device_id' => ['required', 'string', 'max:255'],
            'metadata.delegated_scopes' => ['sometimes', 'array'],
            'metadata.authenticated_at' => ['sometimes', 'integer'],
            'metadata.approval' => ['sometimes', 'array'],
        ]);

        $user = $request->user();
        $userTenant = data_get($user, 'tenant_id') ?? data_get($user, 'team_id');
        $commandTenant = data_get($validated, 'metadata.tenant_id');
        if ($userTenant !== null && (string) $userTenant !== (string) $commandTenant) {
            return response()->json(['message' => 'The command tenant does not match the authenticated user.'], 403);
        }

        $key = 'interaction:offline-command:' . $validated['id'];
        if (!Cache::add($key, ['status' => 'processing'], now()->addDays(30))) {
            return response()->json(['id' => $validated['id'], 'status' => 'duplicate'], 202);
        }

        try {
            $payload = (array) $validated['payload'];
            $payload['_context'] = array_merge((array) ($payload['_context'] ?? []), [
                'tenant_id' => (string) $commandTenant,
                'device_id' => (string) data_get($validated, 'metadata.device_id'),
                'user_id' => $user !== null ? (string) data_get($user, 'id') : null,
                'actor_type' => 'device_replay',
                'roles' => array_values((array) (data_get($user, 'roles') ?? [])),
                'delegated_scopes' => array_values((array) data_get($validated, 'metadata.delegated_scopes', [])),
                'authenticated_at' => (int) data_get($validated, 'metadata.authenticated_at', 0),
                'offline_command_id' => (string) $validated['id'],
            ]);
            if (isset($validated['metadata']['approval']) && is_array($validated['metadata']['approval'])) {
                $payload['_approval'] = $validated['metadata']['approval'];
            }
            $this->commands->dispatch((string) $validated['capability'], $payload);
            Cache::put($key, ['status' => 'executed', 'executed_at' => now()->toIso8601String()], now()->addDays(30));
            return response()->json(['id' => $validated['id'], 'status' => 'executed'], 202);
        } catch (\Throwable $error) {
            Cache::forget($key);
            $status = str_contains(strtolower($error->getMessage()), 'conflict') ? 409 : 422;
            return response()->json([
                'id' => $validated['id'],
                'status' => $status === 409 ? 'conflict' : 'rejected',
                'message' => $error->getMessage(),
            ], $status);
        }
    }
}
