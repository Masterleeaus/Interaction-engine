<?php

namespace TitanZero\Interaction\Contracts;

use TitanZero\Interaction\DTO\Question;
use TitanZero\Interaction\DTO\Answer;

interface QuestionResolverInterface
{
    public function resolve(Question $question, array $context): Answer;
}