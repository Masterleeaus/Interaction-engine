<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Context;

use TitanZero\Interaction\Contracts\ContextProviderInterface;

class ContextBuilder
{
    private array $providers = [];

    public function registerProvider(ContextProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    public function build(array $state): array
    {
        $context = [
            'user' => $this->getUser((int) $state['user_id']),
            'answers' => $this->getAnswers($state),
            'interaction' => [
                'id' => $state['interaction_id'],
                'definition_version' => $state['definition_version'] ?? '1.0.0',
            ],
            'device' => $this->getDeviceInfo(),
            'timestamp' => gmdate(DATE_ATOM),
        ];
        foreach ($this->providers as $provider) {
            $context = array_replace_recursive($context, $provider->provide($state));
        }
        return $context;
    }

    private function getUser(int $userId): array
    {
        if (function_exists('auth')) {
            $user = auth()->user();
            if ($user && (int) $user->getAuthIdentifier() === $userId) {
                return method_exists($user, 'toArray') ? $user->toArray() : ['id' => $userId];
            }
        }
        $class = '\\App\\Models\\User';
        if (class_exists($class) && method_exists($class, 'find')) {
            $user = $class::find($userId);
            if ($user) {
                return method_exists($user, 'toArray') ? $user->toArray() : ['id' => $userId];
            }
        }
        return ['id' => $userId];
    }

    private function getAnswers(array $state): array
    {
        $answers = [];
        foreach ($state['answers'] ?? [] as $answer) {
            if (is_object($answer) && isset($answer->questionKey)) {
                $answers[$answer->questionKey] = $answer->value;
            } elseif (is_array($answer) && isset($answer['question_key'])) {
                $answers[$answer['question_key']] = $answer['value'] ?? null;
            }
        }
        return $answers;
    }

    private function getDeviceInfo(): array
    {
        if (!function_exists('request') || !app()->bound('request')) {
            return ['platform' => 'unknown', 'ip' => 'unknown'];
        }
        return [
            'platform' => request()->userAgent() ?? 'unknown',
            'ip' => request()->ip() ?? 'unknown',
        ];
    }
}
