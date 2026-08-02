<?php

declare(strict_types=1);

namespace TitanZero\Engines\Cognitive\Contracts;

interface ReasoningEngineInterface
{
    public function deduce(array $premises): array;
    public function induce(array $observations): array;
    public function abduce(array $observations): array;
    public function decide(array $options): array;
}
