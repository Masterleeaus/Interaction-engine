<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Repositories;

use TitanZero\Interaction\Models\InteractionAnswer;
use TitanZero\Interaction\DTO\Answer;

class EloquentInteractionAnswerRepository implements InteractionAnswerRepositoryInterface
{
    public function save(int $runId, Answer $answer): void
    {
        InteractionAnswer::updateOrCreate(
            ['run_id' => $runId, 'question_key' => $answer->questionKey],
            [
                'value' => json_encode($answer->value),
                'source' => $answer->source,
                'confidence' => $answer->confidence,
                'answered_at' => $answer->timestamp ?? now(),
                'edited_by_user' => $answer->editedByUser,
                'edited_by_ai' => $answer->editedByAI,
                'validation_state' => $answer->validationState,
            ]
        );
    }

    public function getAll(int $runId): array
    {
        $records = InteractionAnswer::where('run_id', $runId)->get();
        $answers = [];
        foreach ($records as $record) {
            $answers[] = new Answer(
                questionKey: $record->question_key,
                value: json_decode($record->value, true),
                source: $record->source,
                confidence: $record->confidence,
                timestamp: $record->answered_at ? new \DateTimeImmutable($record->answered_at) : null,
                editedByUser: (bool) $record->edited_by_user,
                editedByAI: (bool) $record->edited_by_ai,
                validationState: $record->validation_state,
            );
        }
        return $answers;
    }
}