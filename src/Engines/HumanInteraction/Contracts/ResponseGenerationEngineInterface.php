<?php

declare(strict_types=1);

namespace TitanZero\Engines\HumanInteraction\Contracts;

interface ResponseGenerationEngineInterface
{
    public function generate(array $context): string;
    public function generateWithTemplate(string $template, array $data): string;
    public function getResponseType(): string;
}
