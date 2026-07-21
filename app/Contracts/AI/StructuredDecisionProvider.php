<?php

namespace App\Contracts\AI;

interface StructuredDecisionProvider
{
    /**
     * @param  array{max_tokens?: int|null, temperature?: float|null}  $options
     * @return array{json: array<string, mixed>, raw: string|null, mocked: bool, error: string|null, metadata: array<string, mixed>}
     */
    public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array;
}
