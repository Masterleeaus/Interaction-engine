<?php

declare(strict_types=1);

namespace TitanZero\Engines\HumanInteraction\Contracts;

interface EmotionEngineInterface
{
    public function detect(string $input): array;
    public function getCurrentEmotion(): string;
    public function listEmotions(): array;
}
