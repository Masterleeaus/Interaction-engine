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
        $request = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $options['system_prompt'] ?? 'You are a helpful assistant that answers questions concisely.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $options['max_tokens'] ?? 150,
            'temperature' => $options['temperature'] ?? 0.3,
        ];
        if (isset($options['response_format']) && is_array($options['response_format'])) {
            $request['response_format'] = $options['response_format'];
        }

        $response = $this->client->chat()->create($request);

        return $response->choices[0]->message->content ?? '';
    }
}
