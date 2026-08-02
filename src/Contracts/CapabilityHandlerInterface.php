<?php

namespace TitanZero\Interaction\Contracts;

interface CapabilityHandlerInterface
{
    public function handle(string $capability, array $payload): void;
}