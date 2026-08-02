<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Contracts;

use TitanZero\Interaction\DTO\Question;

interface ValidationEngineInterface
{
    public function validate(Question $question, mixed $value): array;
}