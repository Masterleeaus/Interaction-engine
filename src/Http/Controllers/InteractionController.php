<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use TitanZero\Interaction\Runtime\InteractionRuntime;
use TitanZero\Interaction\Registry\InteractionRegistry;

class InteractionController
{
    private InteractionRuntime $runtime;
    private InteractionRegistry $registry;

    public function __construct(
        InteractionRuntime $runtime,
        InteractionRegistry $registry
    ) {
        $this->runtime = $runtime;
        $this->registry = $registry;
    }

    public function show(string $interactionId): View
    {
        $definition = $this->registry->get($interactionId);
        if (!$definition) {
            abort(404, "Interaction '{$interactionId}' not found.");
        }

        $user = auth()->user();
        $state = $this->runtime->start($interactionId, $user->id);
        $view = $this->runtime->getCurrentView($state['id']);

        return view('interaction::wrapper', [
            'content' => $view,
            'runId' => $state['id'],
        ]);
    }

    public function process(Request $request, int $runId): JsonResponse
    {
        $state = $this->runtime->load($runId);

        $data = $request->validate([
            'answers' => 'array',
            'action' => 'string|in:next,back,submit',
        ]);

        $result = $this->runtime->process($state, $data);

        if (isset($result['errors'])) {
            return response()->json(['errors' => $result['errors']], 422);
        }

        if (isset($result['complete']) && $result['complete']) {
            return response()->json([
                'complete' => true,
                'message' => 'Interaction completed successfully.',
            ]);
        }

        return response()->json([
            'state' => $result['state'],
            'nextView' => $this->runtime->getCurrentView($runId),
        ]);
    }
}