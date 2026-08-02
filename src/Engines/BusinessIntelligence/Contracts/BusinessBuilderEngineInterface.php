<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Contracts;

interface BusinessBuilderEngineInterface
{
    public function generate(string $vertical): array;
    public function customize(array $parameters): array;
    public function launch(string $vertical): void;
}
