<?php

namespace App\Services\AI;

use App\Contracts\AI\StructuredDecisionProvider;
use App\Services\Qwen\QwenClient;

class QwenStructuredProvider implements StructuredDecisionProvider
{
    public function __construct(private readonly QwenClient $client) {}

    /**
     * @param  array{max_tokens?: int|null, temperature?: float|null}  $options
     * @return array{json: array<string, mixed>, raw: string|null, mocked: bool, error: string|null, metadata: array<string, mixed>}
     */
    public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        return $this->client->generateJson($systemPrompt, $userPrompt, $options);
    }
}
