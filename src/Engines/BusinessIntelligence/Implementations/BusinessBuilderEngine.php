<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Implementations;

use TitanZero\Engines\BusinessIntelligence\Contracts\BusinessBuilderEngineInterface;
class BusinessBuilderEngine implements BusinessBuilderEngineInterface
{
    private array $templates = [
        'cleaning' => ['services' => ['Residential cleaning', 'Commercial cleaning', 'End-of-lease cleaning']],
        'plumbing' => ['services' => ['Leak repair', 'Drain service', 'Fixture installation']],
        'electrical' => ['services' => ['Electrical repair', 'Safety inspection', 'Lighting installation']],
        'landscaping' => ['services' => ['Garden maintenance', 'Lawn care', 'Landscape installation']],
    ];
    private array $drafts = [];
    private array $launches = [];

    public function generate(string $vertical): array
    {
        $key = $this->normalizeVertical($vertical);
        if (!isset($this->templates[$key])) {
            throw new \InvalidArgumentException("No business template is registered for '{$key}'.");
        }
        $draft = [
            'vertical' => $key,
            'business_name' => '',
            'services' => $this->templates[$key]['services'],
            'pricing' => [],
            'service_area' => [],
            'contact' => [],
            'status' => 'draft',
        ];
        $this->drafts[$key] = $draft;
        return $draft;
    }

    public function customize(array $parameters): array
    {
        $vertical = $this->normalizeVertical((string) ($parameters['vertical'] ?? 'cleaning'));
        $draft = $this->drafts[$vertical] ?? $this->generate($vertical);
        unset($parameters['vertical']);
        $this->drafts[$vertical] = $this->mergeConfiguration($draft, $parameters);
        return $this->drafts[$vertical];
    }

    public function launch(string $vertical): void
    {
        $key = $this->normalizeVertical($vertical);
        $draft = $this->drafts[$key] ?? null;
        if ($draft === null) {
            throw new \LogicException('Generate and customize the business profile before launch.');
        }
        $missing = [];
        if (trim((string) ($draft['business_name'] ?? '')) === '') {
            $missing[] = 'business_name';
        }
        if (empty($draft['services']) || !is_array($draft['services'])) {
            $missing[] = 'services';
        }
        if (empty($draft['service_area'])) {
            $missing[] = 'service_area';
        }
        if ($missing !== []) {
            throw new \LogicException('Business setup is incomplete: ' . implode(', ', $missing) . '.');
        }
        $launch = array_merge($draft, [
            'status' => 'ready_for_provisioning',
            'launch_id' => 'launch_' . bin2hex(random_bytes(8)),
            'ready_at' => gmdate(DATE_ATOM),
        ]);
        $this->drafts[$key] = $launch;
        $this->launches[$key] = $launch;
    }

    public function registerTemplate(string $vertical, array $template): void
    {
        $key = $this->normalizeVertical($vertical);
        if (empty($template['services']) || !is_array($template['services'])) {
            throw new \InvalidArgumentException('A vertical template must declare at least one service.');
        }
        $this->templates[$key] = ['services' => array_values($template['services'])];
    }

    public function getLaunches(): array
    {
        return array_values($this->launches);
    }

    private function normalizeVertical(string $vertical): string
    {
        $key = strtolower(trim($vertical));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';
        return trim($key, '_');
    }

    private function mergeConfiguration(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && is_array($base[$key] ?? null) && !array_is_list($value) && !array_is_list($base[$key])) {
                $base[$key] = $this->mergeConfiguration($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}
