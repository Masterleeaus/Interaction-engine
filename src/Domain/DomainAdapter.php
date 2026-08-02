<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Domain;

use TitanZero\Interaction\Contracts\DomainAdapterInterface;

abstract class DomainAdapter implements DomainAdapterInterface
{
    // Implement common functionality here
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
}