<?php

declare(strict_types=1);

namespace TitanZero\Engines\Learning\Contracts;

interface RecommendationEngineInterface
{
    public function recommend(int $userId, array $context): array;
    public function getSimilarItems(string $itemId): array;
    public function getTrending(): array;
}
