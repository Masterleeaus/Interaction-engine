<?php

declare(strict_types=1);

namespace TitanZero\Engines\HumanInteraction\Contracts;

interface PersonalityEngineInterface
{
    public function detect(array $context): array;
    public function getProfile(int $userId): array;
    public function adapt(array $profile): void;
}
