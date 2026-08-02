<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Wizard\Validation;

final class WizardValidationEngine
{
    public function validateStep(array $step, array $input, array $existing = []): array
    {
        $errors = [];
        $fields = $step['fields'] ?? [];
        foreach ($fields as $field) {
            $id = (string) ($field['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $value = $input[$id] ?? $existing[$id] ?? null;
            $required = (bool) ($field['required'] ?? $field['validation']['required'] ?? false);
            if ($required && ($value === null || $value === '' || $value === [])) {
                $errors[$id][] = 'This field is required.';
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            $type = $field['type'] ?? 'text';
            if ($type === 'number' && !is_numeric($value)) {
                $errors[$id][] = 'Must be a number.';
            }
            if ($type === 'email' && filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false) {
                $errors[$id][] = 'Must be a valid email address.';
            }
            $min = $field['min'] ?? $field['validation']['min'] ?? null;
            $max = $field['max'] ?? $field['validation']['max'] ?? null;
            if ($min !== null && ((is_numeric($value) && (float) $value < (float) $min) || (is_string($value) && strlen($value) < (int) $min))) {
                $errors[$id][] = "Must be at least {$min}.";
            }
            if ($max !== null && ((is_numeric($value) && (float) $value > (float) $max) || (is_string($value) && strlen($value) > (int) $max))) {
                $errors[$id][] = "Must not exceed {$max}.";
            }
            $allowed = $field['options'] ?? $field['validation']['in'] ?? null;
            if (is_array($allowed) && !in_array($value, $allowed, true)) {
                $errors[$id][] = 'Invalid selection.';
            }
            $regex = $field['regex'] ?? $field['validation']['regex'] ?? null;
            if (is_string($regex) && @preg_match($regex, (string) $value) !== 1) {
                $errors[$id][] = 'Invalid format.';
            }
        }
        return $errors;
    }
}
