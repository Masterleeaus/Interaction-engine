<?php

declare(strict_types=1);

namespace TitanZero\Interaction\AI;

interface AIServiceInterface
{
    public function generate(string $prompt, array $options = []): string;
}