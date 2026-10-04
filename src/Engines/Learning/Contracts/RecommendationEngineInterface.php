<?php

declare(strict_types=1);

namespace TitanZero\Engines\Learning\Contracts;

interface RecommendationEngineInterface
{
    public function setCatalog(array $items): void;
    public function recordInteraction(int $userId, string $itemId, int $count = 1): void;
    public function recommend(int $userId, array $context): array;
    public function getSimilarItems(string $itemId): array;
    public function getTrending(): array;
}
