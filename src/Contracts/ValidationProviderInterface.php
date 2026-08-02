<?php

namespace TitanZero\Interaction\Contracts;

interface ValidationProviderInterface
{
    public function validate(mixed $value, array $rules): array;
}