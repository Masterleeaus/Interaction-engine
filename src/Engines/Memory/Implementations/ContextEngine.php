<?php

declare(strict_types=1);

namespace TitanZero\Engines\Memory\Implementations;

use TitanZero\Engines\Memory\Contracts\ContextEngineInterface;
use Illuminate\Support\Facades\Cache;

class ContextEngine implements ContextEngineInterface
{
    public function build(int $userId, array $state): array
    {
        $context = Cache::get('context_' . $userId, []);
        $context = array_merge($context, $state);
        Cache::put('context_' . $userId, $context, 3600);
        return $context;
    }

    public function getContext(int $userId): array
    {
        return Cache::get('context_' . $userId, []);
    }

    public function updateContext(int $userId, array $data): void
    {
        $context = $this->getContext($userId);
        $context = array_merge($context, $data);
        Cache::put('context_' . $userId, $context, 3600);
    }

    public function clearContext(int $userId): void
    {
        Cache::forget('context_' . $userId);
    }
}
