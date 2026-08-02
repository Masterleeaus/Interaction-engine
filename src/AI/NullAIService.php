<?php

declare(strict_types=1);

namespace TitanZero\Interaction\AI;

final class NullAIService implements AIServiceInterface
{
    public function generate(string $prompt, array $options = []): string
    {
        throw new \RuntimeException('Cloud AI is disabled. Local intelligence remains available.');
    }
}
