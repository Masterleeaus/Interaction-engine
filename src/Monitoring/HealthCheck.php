<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use TitanZero\Interaction\Registry\InteractionRegistry;
use TitanZero\Interaction\Wizard\WizardRegistry;

final class HealthCheck
{
    public function __construct(
        private readonly InteractionRegistry $interactions,
        private readonly WizardRegistry $wizards,
    ) {}

    public function run(): array
    {
        $checks = [
            $this->check('php', static fn(): array => [
                'healthy' => version_compare(PHP_VERSION, '8.2.0', '>='),
                'detail' => PHP_VERSION,
            ]),
            $this->check('sodium', static fn(): array => [
                'healthy' => extension_loaded('sodium'),
                'detail' => extension_loaded('sodium') ? 'loaded' : 'missing',
            ]),
            $this->check('database', static function (): array {
                DB::connection()->getPdo();
                return ['healthy' => true, 'detail' => DB::connection()->getDatabaseName()];
            }),
            $this->check('cache', static function (): array {
                $key = 'interaction:health:' . bin2hex(random_bytes(4));
                Cache::put($key, 'ok', 10);
                $healthy = Cache::get($key) === 'ok';
                Cache::forget($key);
                return ['healthy' => $healthy, 'detail' => $healthy ? 'read/write' : 'read/write failed'];
            }),
            $this->check('interaction_definitions', fn(): array => [
                'healthy' => count($this->interactions->all()) > 0,
                'detail' => count($this->interactions->all()) . ' loaded',
            ]),
            $this->check('wizard_definitions', fn(): array => [
                'healthy' => count($this->wizards->all()) >= 5,
                'detail' => count($this->wizards->all()) . ' loaded',
            ]),
            $this->check('outbox_secret', static function (): array {
                $secret = (string) config('interaction.wizard.outbox_secret', '');
                $weak = $secret === '' || $secret === 'change-me';
                return ['healthy' => !$weak, 'detail' => $weak ? 'not configured' : 'configured'];
            }),
        ];

        return [
            'healthy' => !in_array(false, array_column($checks, 'healthy'), true),
            'checked_at' => gmdate(DATE_ATOM),
            'checks' => $checks,
        ];
    }

    private function check(string $name, callable $check): array
    {
        try {
            $result = $check();
            return [
                'name' => $name,
                'healthy' => (bool) ($result['healthy'] ?? false),
                'detail' => (string) ($result['detail'] ?? ''),
            ];
        } catch (\Throwable $error) {
            return ['name' => $name, 'healthy' => false, 'detail' => $error->getMessage()];
        }
    }
}
