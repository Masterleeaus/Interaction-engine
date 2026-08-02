<?php

declare(strict_types=1);

namespace TitanZero\Engines\AIInfrastructure\Contracts;

interface EmbeddingEngineInterface
{
    public function embed(string $text): array;
    public function batchEmbed(array $texts): array;
    public function getEmbeddingSize(): int;
}
