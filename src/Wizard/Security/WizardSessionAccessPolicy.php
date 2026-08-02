<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Wizard\Security;

use TitanZero\Interaction\Wizard\WizardSession;

final class WizardSessionAccessPolicy
{
    public function mayAccess(WizardSession $session, array $actorContext): bool
    {
        $sessionTenant = (string) ($session->context['tenant_id'] ?? '');
        $sessionUser = (string) ($session->context['user_id'] ?? '');
        $actorTenant = (string) ($actorContext['tenant_id'] ?? '');
        $actorUser = (string) ($actorContext['user_id'] ?? '');

        if ($sessionTenant === '' || $sessionUser === '' || $actorTenant === '' || $actorUser === '') {
            return false;
        }

        return hash_equals($sessionTenant, $actorTenant)
            && hash_equals($sessionUser, $actorUser);
    }
}
