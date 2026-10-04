<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Implementations;

use TitanZero\Engines\BusinessIntelligence\Contracts\MonitoringEngineInterface;
class MonitoringEngine implements MonitoringEngineInterface
{
    private array $alerts = [];
    private array $metrics = [];

    public function getStatus(): array
    {
        $breaches = [];
        foreach ($this->alerts as $name => $threshold) {
            if (isset($this->metrics[$name]) && $this->metrics[$name]['value'] >= $threshold) {
                $breaches[] = ['metric' => $name, 'value' => $this->metrics[$name]['value'], 'threshold' => $threshold];
            }
        }
        return [
            'status' => $this->metrics === [] ? 'unknown' : ($breaches === [] ? 'operational' : 'degraded'),
            'metric_count' => count($this->metrics),
            'alert_breaches' => $breaches,
            'checked_at' => gmdate(DATE_ATOM),
        ];
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function setAlert(string $metric, float $threshold): void
    {
        if (trim($metric) === '' || $threshold < 0) {
            throw new \InvalidArgumentException('Alert name and a non-negative threshold are required.');
        }
        $this->alerts[$metric] = $threshold;
    }

    public function recordMetric(string $metric, float $value): void
    {
        if (trim($metric) === '' || !is_finite($value)) {
            throw new \InvalidArgumentException('Metric name and a finite value are required.');
        }
        $this->metrics[$metric] = ['value' => $value, 'recorded_at' => gmdate(DATE_ATOM)];
    }

    public function getAlerts(): array
    {
        return $this->alerts;
    }
}
