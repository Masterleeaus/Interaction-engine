<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Wizard\Offline;

final class LocalCommandOutbox
{
    private array $commands = [];
    private string $key;

    public function __construct(string $secret)
    {
        if ($secret === '') {
            throw new \InvalidArgumentException('Outbox secret cannot be empty.');
        }
        $this->key = hash('sha256', $secret, true);
    }

    public function enqueue(array $command): array
    {
        $plain = json_encode($command, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            throw new \RuntimeException('The PHP Sodium extension is required for encrypted offline commands.');
        }
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, '', $nonce, $this->key);
        $algorithm = 'xchacha20poly1305';
        $envelope = [
            'id' => (string) ($command['id'] ?? bin2hex(random_bytes(16))),
            'algorithm' => $algorithm,
            'nonce' => base64_encode($nonce),
            'ciphertext' => base64_encode($ciphertext),
            'queued_at' => gmdate(DATE_ATOM),
            'status' => 'pending',
        ];
        $envelope['signature'] = $this->signature($envelope);
        $this->commands[$envelope['id']] = $envelope;
        return $envelope;
    }

    public function pending(): array
    {
        return array_values(array_filter($this->commands, static fn(array $item): bool => $item['status'] === 'pending'));
    }

    public function verify(array $envelope): bool
    {
        return isset($envelope['signature']) && hash_equals($this->signature($envelope), (string) $envelope['signature']);
    }

    public function decrypt(array $envelope): array
    {
        if (!$this->verify($envelope)) {
            throw new \RuntimeException('Outbox command integrity check failed.');
        }
        $nonce = base64_decode((string) $envelope['nonce'], true);
        $ciphertext = base64_decode((string) $envelope['ciphertext'], true);
        if ($nonce === false || $ciphertext === false) {
            throw new \RuntimeException('Outbox command encoding is invalid.');
        }
        if (($envelope['algorithm'] ?? null) !== 'xchacha20poly1305') {
            throw new \RuntimeException('Unsupported outbox encryption algorithm.');
        }
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, '', $nonce, $this->key);
        if ($plain === false) {
            throw new \RuntimeException('Outbox command decryption failed.');
        }
        return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
    }

    public function markSynced(string $id): void
    {
        if (isset($this->commands[$id])) {
            $this->commands[$id]['status'] = 'synced';
            $this->commands[$id]['synced_at'] = gmdate(DATE_ATOM);
            $this->commands[$id]['signature'] = $this->signature($this->commands[$id]);
        }
    }

    private function signature(array $envelope): string
    {
        unset($envelope['signature']);
        ksort($envelope);
        return hash_hmac('sha256', json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $this->key);
    }
}
