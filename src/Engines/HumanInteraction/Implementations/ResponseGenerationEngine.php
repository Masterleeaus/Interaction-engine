<?php

declare(strict_types=1);

namespace TitanZero\Engines\HumanInteraction\Implementations;

use TitanZero\Engines\HumanInteraction\Contracts\ResponseGenerationEngineInterface;

/**
 * Builds response text from the actual $context contents rather than a
 * fixed sentence. Fixed during the fix pass: generate() always returned
 * the identical 'Based on the context, I recommend...' string regardless
 * of $context.
 */
class ResponseGenerationEngine implements ResponseGenerationEngineInterface
{
    public function generate(array $context): string
    {
        if (empty($context)) {
            return "I don't have enough context to respond specifically yet.";
        }

        if (isset($context['recommendation'])) {
            return "Based on what you've shared, I'd suggest: {$context['recommendation']}.";
        }

        if (isset($context['question'])) {
            $answer = $context['answer'] ?? 'let me look into that further';
            return "Regarding \"{$context['question']}\" — {$answer}.";
        }

        $summaryParts = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $summaryParts[] = "{$key}: {$value}";
            }
        }
        $summary = implode(', ', $summaryParts);

        return $summary !== ''
            ? "Based on {$summary}, here's what I found relevant."
            : "I've reviewed the available information but don't have a specific recommendation yet.";
    }

    public function generateWithTemplate(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/', static function (array $match) use ($data): string {
            $value = $data;
            foreach (explode('.', $match[1]) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return $match[0];
                }
                $value = $value[$segment];
            }
            return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
        }, $template) ?? $template;
    }

    public function getResponseType(): string
    {
        return 'informative';
    }
}
