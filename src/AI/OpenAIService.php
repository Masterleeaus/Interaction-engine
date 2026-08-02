<?php

declare(strict_types=1);

namespace TitanZero\Interaction\AI;

use OpenAI\Client;

class OpenAIService implements AIServiceInterface
{
    private Client $client;
    private string $model;

    public function __construct(Client $client, string $model = 'gpt-4o-mini')
    {
        $this->client = $client;
        $this->model = $model;
    }

    public function generate(string $prompt, array $options = []): string
    {
        $response = $this->client->chat()->create([
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant that answers questions concisely.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $options['max_tokens'] ?? 150,
            'temperature' => $options['temperature'] ?? 0.3,
        ]);

        return $response->choices[0]->message->content ?? '';
    }
}