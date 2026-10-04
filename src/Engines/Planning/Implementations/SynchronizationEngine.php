<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Implementations;

use TitanZero\Engines\Planning\Contracts\SynchronizationEngineInterface;
class SynchronizationEngine implements SynchronizationEngineInterface
{
    private array $syncs = [];
    private array $readers = [];
    private array $writers = [];

    public function __construct(private readonly \TitanZero\Interaction\Contracts\PolicyEngineInterface $policyEngine) {}

    public function registerSource(string $name, callable $reader): void
    {
        $this->assertEndpointName($name);
        $this->readers[$name] = $reader;
    }

    public function registerTarget(string $name, callable $writer): void
    {
        $this->assertEndpointName($name);
        $this->writers[$name] = $writer;
    }

    public function sync(string $source, string $target, array $trustedContext = []): void
    {
        $this->assertEndpointName($source);
        $this->assertEndpointName($target);
        $id = 'sync_' . bin2hex(random_bytes(10));
        $record = [
            'id' => $id,
            'source' => $source,
            'target' => $target,
            'status' => 'running',
            'started_at' => gmdate(DATE_ATOM),
        ];
        $this->syncs[$id] = $record;
        if (!isset($this->readers[$source], $this->writers[$target])) {
            $this->syncs[$id]['status'] = 'failed';
            $this->syncs[$id]['error'] = 'A registered source reader and target writer are required.';
            $this->syncs[$id]['completed_at'] = gmdate(DATE_ATOM);
            throw new \RuntimeException($this->syncs[$id]['error']);
        }

        $capability = "sync.{$source}.{$target}";
        $decision = $this->policyEngine->decide($capability, [
            'source' => $source,
            'target' => $target,
            '_context' => $trustedContext,
        ]);
        if (!$decision->allowed) {
            $this->syncs[$id]['status'] = 'denied';
            $this->syncs[$id]['error'] = implode('; ', $decision->reasons);
            $this->syncs[$id]['completed_at'] = gmdate(DATE_ATOM);
            throw new \RuntimeException('Synchronization denied: ' . $this->syncs[$id]['error']);
        }

        try {
            $data = ($this->readers[$source])();
            $serialized = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            ($this->writers[$target])($data);
            $this->syncs[$id] = array_merge($this->syncs[$id], [
                'status' => 'completed',
                'record_count' => is_countable($data) ? count($data) : 1,
                'sha256' => hash('sha256', $serialized),
                'completed_at' => gmdate(DATE_ATOM),
            ]);
        } catch (\Throwable $error) {
            $this->syncs[$id]['status'] = 'failed';
            $this->syncs[$id]['error'] = $error->getMessage();
            $this->syncs[$id]['completed_at'] = gmdate(DATE_ATOM);
            throw $error;
        }
    }

    public function getStatus(string $syncId): array
    {
        return $this->syncs[$syncId] ?? ['id' => $syncId, 'status' => 'not_found'];
    }

    public function listSyncs(): array
    {
        return array_values($this->syncs);
    }

    private function assertEndpointName(string $name): void
    {
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $name) !== 1) {
            throw new \InvalidArgumentException('Synchronization endpoint names may contain only letters, numbers, underscores and hyphens.');
        }
    }
}
