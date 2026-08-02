<?php

namespace TitanZero\Interaction\Contracts;

interface NavigationStrategyInterface
{
    public function next(array $state, array $sections): ?int;
}