<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Context\Providers;

use TitanZero\Interaction\Contracts\ContextProviderInterface;

class CustomerContextProvider implements ContextProviderInterface
{
    public function provide(array $state): array
    {
        $customerId = $this->answer($state, 'customer_id');
        if (!$customerId) {
            return ['customer' => null];
        }
        $models = [
            config('interaction.adapter_config.models.customer'),
            '\\App\\Extensions\\WorkCore\\System\\Modules\\CRM\\Models\\Customer',
            '\\App\\Models\\Customer',
        ];
        foreach (array_filter($models) as $model) {
            if (class_exists($model) && method_exists($model, 'find')) {
                $customer = $model::find($customerId);
                return ['customer' => $customer && method_exists($customer, 'toArray') ? $customer->toArray() : null];
            }
        }
        return ['customer' => ['id' => $customerId]];
    }

    private function answer(array $state, string $key): mixed
    {
        foreach ($state['answers'] ?? [] as $answer) {
            if (is_object($answer) && ($answer->questionKey ?? null) === $key) {
                return $answer->value;
            }
            if (is_array($answer) && ($answer['question_key'] ?? null) === $key) {
                return $answer['value'] ?? null;
            }
        }
        return $state['answers'][$key] ?? null;
    }
}
