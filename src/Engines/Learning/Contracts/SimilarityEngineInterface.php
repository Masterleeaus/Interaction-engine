<?php

declare(strict_types=1);

namespace TitanZero\Engines\Learning\Contracts;

interface SimilarityEngineInterface
{
    public function cosineSimilarity(array $a, array $b): float;
    public function jaccardSimilarity(array $a, array $b): float;
    public function getMostSimilar(string $target, array $candidates): array;
}
