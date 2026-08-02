<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Contracts;

interface CapabilityRoutingEngineInterface
{
    public function route(string $capability, array $payload): mixed;
    public function registerHandler(string $capability, callable $handler): void;
    public function listCapabilities(): array;
}
