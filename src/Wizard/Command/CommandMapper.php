<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Wizard\Command;

use TitanZero\Interaction\Wizard\WizardSession;

final class CommandMapper
{
    public function map(WizardSession $session): array
    {
        $context = $session->context;
        $tenantId = (string) ($context['tenant_id'] ?? '');
        $correlationId = (string) ($context['correlation_id'] ?? $session->id);
        $causationId = (string) ($context['causation_id'] ?? $correlationId);
        $idempotencyKey = hash('sha256', implode('|', [
            $tenantId,
            $session->id,
            $session->definition->capability,
            $session->definition->version,
        ]));

        $payload = $session->data;
        $approvalEvidence = array_intersect_key($payload, array_flip(['approval_id', 'approved_by', 'approved_at']));
        if ($approvalEvidence !== []) {
            // User-entered approval fields are evidence for review, not authority.
            $payload['_approval_evidence'] = $approvalEvidence;
        }

        // Only an approval grant placed in trusted, server-built context can
        // reach PolicyEngine. Never mint a signature from wizard form values.
        $grant = $context['signed_approvals'][$session->definition->capability] ?? null;
        if (is_array($grant) && !empty($grant['signature'])) {
            $payload['_approval'] = $grant;
        }

        return [
            'id' => self::uuid(),
            'capability' => $session->definition->capability,
            'payload' => $payload,
            'metadata' => [
                'wizard_id' => $session->definition->id,
                'wizard_version' => $session->definition->version,
                'template_id' => (string) ($session->definition->metadata['template_id'] ?? $session->definition->id),
                'template_version' => (string) ($session->definition->metadata['template_version'] ?? $session->definition->version),
                'session_id' => $session->id,
                'tenant_id' => $tenantId,
                'user_id' => $context['user_id'] ?? null,
                'device_id' => (string) ($context['device_id'] ?? ''),
                'correlation_id' => $correlationId,
                'causation_id' => $causationId,
                'idempotency_key' => $idempotencyKey,
                'created_at' => gmdate(DATE_ATOM),
                'created_via' => 'universal_wizard',
            ],
        ];
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
