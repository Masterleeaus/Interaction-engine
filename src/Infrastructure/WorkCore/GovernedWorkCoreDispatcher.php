<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Infrastructure\WorkCore;

use RuntimeException;

final class GovernedWorkCoreDispatcher
{
    /** @param array<string,mixed> $payload */
    public function dispatch(string $actionKey, array $payload): mixed
    {
        $dispatcherClass = 'App\\Domains\\WorkCore\\System\\Actions\\BusinessActionDispatcher';
        $requestClass = 'App\\Domains\\WorkCore\\System\\Actions\\ActionRequest';
        if (!class_exists($dispatcherClass) || !class_exists($requestClass)) {
            throw new RuntimeException('Canonical WorkCore governed action layer is unavailable.');
        }
        $tenantContract = 'App\\Domains\\WorkCore\\System\\Contracts\\TenantContextContract';
        if (!app()->bound($tenantContract)) {
            throw new RuntimeException('Active WorkCore tenant context is unavailable.');
        }
        $tenant = app($tenantContract);
        $companyId = (int) $tenant->companyId();
        $actorId = (int) $tenant->userId();
        if ($companyId < 1 || $actorId < 1) {
            throw new RuntimeException('A valid active company and actor are required.');
        }
        $confirmationId = request()?->header('X-Confirmation-Id');
        $idempotencyKey = request()?->header('Idempotency-Key')
            ?: hash('sha256', $actionKey.'|'.$companyId.'|'.$actorId.'|'.json_encode($payload, JSON_THROW_ON_ERROR));
        $request = new $requestClass($actionKey, $payload, $companyId, $actorId, $idempotencyKey, $confirmationId, 'interaction-engine');
        return app($dispatcherClass)->dispatch($request);
    }
}
