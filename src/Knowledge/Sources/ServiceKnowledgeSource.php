<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Knowledge\Sources;

use TitanZero\Interaction\Contracts\KnowledgeSourceInterface;

class ServiceKnowledgeSource implements KnowledgeSourceInterface
{
    public function id(): string
    {
        return 'services';
    }

    public function supports(string $type): bool
    {
        return $type === 'services';
    }

    public function query(string $query, array $context = []): mixed
    {
        // Fetch available services
        return \App\Models\Service::where('is_active', true)
            ->where('name', 'LIKE', "%$query%")
            ->get()
            ->toArray();
    }
}