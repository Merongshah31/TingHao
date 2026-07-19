<?php

namespace App\Services\Qwen;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class QwenClient
{
    /**
     * @param  array{max_tokens?: int|null, temperature?: float|null}  $options
     * @return array{json: array<string, mixed>, raw: string|null, mocked: bool, error: string|null, metadata: array<string, mixed>}
     */
    public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        if ($this->shouldMock()) {
            $maxTokens = $options['max_tokens'] ?? null;
            $temperature = $options['temperature'] ?? config('qwen.temperature', 0.2);

            return [
                'json' => [],
                'raw' => null,
                'mocked' => true,
                'error' => null,
                'metadata' => $this->metadata(
                    mocked: true,
                    maxTokens: is_numeric($maxTokens) ? (int) $maxTokens : null,
                    temperature: (float) $temperature,
                ),
            ];
        }

        $startedAt = microtime(true);
        $maxTokens = $options['max_tokens'] ?? null;
        $temperature = $options['temperature'] ?? config('qwen.temperature', 0.2);

        try {
            $payload = [
                'model' => config('qwen.model', 'qwen-plus'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => (float) $temperature,
            ];

            if (is_numeric($maxTokens) && (int) $maxTokens > 0) {
                $payload['max_tokens'] = (int) $maxTokens;
            }

            $response = Http::withToken((string) config('qwen.api_key'))
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->post($this->endpoint(), $payload)
                ->throw();

            $body = $response->json();
            $raw = (string) data_get($body, 'choices.0.message.content', '');
            $json = $this->decodeJson($raw);

            return [
                'json' => $json,
                'raw' => $raw,
                'mocked' => false,
                'error' => $json === [] ? 'Qwen returned empty or invalid JSON.' : null,
                'metadata' => $this->metadata(
                    mocked: false,
                    httpStatus: $response->status(),
                    latencyMs: $this->latencyMs($startedAt),
                    usage: is_array(data_get($body, 'usage')) ? data_get($body, 'usage') : [],
                    maxTokens: $payload['max_tokens'] ?? null,
                    temperature: (float) $temperature,
                ),
            ];
        } catch (RequestException $exception) {
            return [
                'json' => [],
                'raw' => $exception->response?->body(),
                'mocked' => false,
                'error' => 'Qwen request failed with HTTP '.$exception->response?->status().'.',
                'metadata' => $this->metadata(
                    mocked: false,
                    httpStatus: $exception->response?->status(),
                    latencyMs: $this->latencyMs($startedAt),
                    maxTokens: is_numeric($maxTokens) ? (int) $maxTokens : null,
                    temperature: (float) $temperature,
                ),
            ];
        } catch (\Throwable $exception) {
            return [
                'json' => [],
                'raw' => null,
                'mocked' => false,
                'error' => 'Qwen request failed safely.',
                'metadata' => $this->metadata(
                    mocked: false,
                    latencyMs: $this->latencyMs($startedAt),
                    maxTokens: is_numeric($maxTokens) ? (int) $maxTokens : null,
                    temperature: (float) $temperature,
                ),
            ];
        }
    }

    public function isConfigured(): bool
    {
        return filled(config('qwen.api_key'));
    }

    public function isMockMode(): bool
    {
        return $this->shouldMock();
    }

    private function shouldMock(): bool
    {
        return filter_var(config('qwen.mock_mode', true), FILTER_VALIDATE_BOOLEAN) || ! $this->isConfigured();
    }

    private function endpoint(): string
    {
        return rtrim((string) config('qwen.base_url'), '/').'/chat/completions';
    }

    /**
     * @param  array<string, mixed>  $usage
     * @return array<string, mixed>
     */
    private function metadata(
        bool $mocked,
        ?int $httpStatus = null,
        ?int $latencyMs = null,
        array $usage = [],
        ?int $maxTokens = null,
        ?float $temperature = null,
    ): array {
        return [
            'model' => config('qwen.model', 'qwen-plus'),
            'mock_mode' => $mocked,
            'server_side_configured' => $this->isConfigured(),
            'http_status' => $httpStatus,
            'latency_ms' => $latencyMs,
            'input_tokens' => data_get($usage, 'prompt_tokens'),
            'output_tokens' => data_get($usage, 'completion_tokens'),
            'total_tokens' => data_get($usage, 'total_tokens'),
            'max_tokens' => $maxTokens,
            'temperature' => $temperature ?? (float) config('qwen.temperature', 0.2),
        ];
    }

    private function latencyMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $raw): array
    {
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $betweenBraces = Str::between($raw, '{', '}');
        if ($betweenBraces === '') {
            return [];
        }

        $decoded = json_decode('{'.$betweenBraces.'}', true);

        return is_array($decoded) ? $decoded : [];
    }
}
