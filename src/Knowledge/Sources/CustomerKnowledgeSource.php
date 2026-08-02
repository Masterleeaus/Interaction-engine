<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Knowledge\Sources;

use TitanZero\Interaction\Contracts\KnowledgeSourceInterface;

class CustomerKnowledgeSource implements KnowledgeSourceInterface
{
    public function id(): string
    {
        return 'customers';
    }

    public function supports(string $type): bool
    {
        return $type === 'customers' || $type === 'customer';
    }

    public function query(string $query, array $context = []): mixed
    {
        // Search for a customer by name, email, or phone
        $search = $query;
        $customers = \App\Models\Customer::where('name', 'LIKE', "%$search%")
            ->orWhere('email', $search)
            ->orWhere('phone', $search)
            ->limit(5)
            ->get();

        return $customers->toArray();
    }
}