<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Wizard\Storage;

use TitanZero\Interaction\Wizard\WizardSession;

interface WizardSessionStoreInterface
{
    public function put(WizardSession $session): void;

    public function get(string $sessionId): ?WizardSession;

    public function delete(string $sessionId): void;
}
