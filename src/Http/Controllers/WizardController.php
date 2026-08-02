<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use TitanZero\Interaction\Wizard\Renderer\HybridRenderer;
use TitanZero\Interaction\Wizard\Storage\WizardSessionStoreInterface;
use TitanZero\Interaction\Wizard\UniversalWizardEngine;
use TitanZero\Interaction\Wizard\Context\WizardExecutionContextFactory;
use TitanZero\Interaction\Wizard\Security\WizardSessionAccessPolicy;
use TitanZero\Interaction\Wizard\WizardRegistry;

final class WizardController
{
    public function __construct(
        private readonly WizardRegistry $registry,
        private readonly UniversalWizardEngine $engine,
        private readonly WizardSessionStoreInterface $sessions,
        private readonly HybridRenderer $renderer,
        private readonly WizardExecutionContextFactory $contexts,
        private readonly WizardSessionAccessPolicy $access,
    ) {}

    public function index(): JsonResponse
    {
        $wizards = array_map(static fn($wizard): array => [
            'id' => $wizard->id,
            'version' => $wizard->version,
            'name' => $wizard->name,
            'capability' => $wizard->capability,
            'permissions' => $wizard->permissions,
            'metadata' => $wizard->metadata,
            'offline' => $wizard->offline,
            'step_count' => $wizard->stepCount(),
        ], array_values($this->registry->all()));

        return response()->json(['wizards' => $wizards]);
    }

    public function start(Request $request, string $wizardId): JsonResponse
    {
        if (!$this->registry->has($wizardId)) {
            return response()->json(['message' => "Wizard '{$wizardId}' was not found."], 404);
        }

        $context = $this->contexts->build(
            $request->user(),
            $request->all(),
            $request->headers->all(),
        );
        $session = $this->engine->start($wizardId, $context);
        $this->sessions->put($session);

        return response()->json($this->renderer->render($session), 201);
    }

    public function show(Request $request, string $sessionId): JsonResponse
    {
        $session = $this->sessions->get($sessionId);
        if ($session === null) {
            return response()->json(['message' => 'Wizard session was not found or has expired.'], 404);
        }
        $actor = $this->contexts->build($request->user(), [], $request->headers->all());
        if (!$this->access->mayAccess($session, $actor)) {
            return response()->json(['message' => 'Wizard session access was denied.'], 403);
        }
        return response()->json($this->renderer->render($session));
    }

    public function submitStep(Request $request, string $sessionId): JsonResponse
    {
        $validated = $request->validate(['data' => ['required', 'array']]);
        $session = $this->sessions->get($sessionId);
        if ($session === null) {
            return response()->json(['message' => 'Wizard session was not found or has expired.'], 404);
        }
        $actor = $this->contexts->build($request->user(), [], $request->headers->all());
        if (!$this->access->mayAccess($session, $actor)) {
            return response()->json(['message' => 'Wizard session access was denied.'], 403);
        }

        $result = $this->engine->submitStep($session, (array) $validated['data']);
        $this->sessions->put($result->session);
        $payload = $this->renderer->render($result->session);
        $payload['errors'] = $result->errors;
        $payload['guidance'] = $result->guidance;
        $payload['complete'] = $result->complete;
        $payload['command'] = $result->command;

        return response()->json($payload, $result->errors === [] ? 200 : 422);
    }
}
