<?php

namespace App\Services\OpenAI;

use App\Contracts\AI\StructuredDecisionProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OpenAIClient implements StructuredDecisionProvider
{
    /**
     * @param  array{max_tokens?: int|null, temperature?: float|null}  $options
     * @return array{json: array<string, mixed>, raw: string|null, mocked: bool, error: string|null, metadata: array<string, mixed>}
     */
    public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        if ($this->shouldMock()) {
            return [
                'json' => [],
                'raw' => null,
                'mocked' => true,
                'error' => null,
                'metadata' => $this->metadata(mocked: true),
            ];
        }

        $startedAt = microtime(true);

        try {
            $payload = [
                'model' => config('ai.openai.model', 'gpt-5.6'),
                'input' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'text' => ['format' => ['type' => 'json_object']],
            ];

            if (is_numeric($options['max_tokens'] ?? null) && (int) $options['max_tokens'] > 0) {
                $payload['max_output_tokens'] = (int) $options['max_tokens'];
            }

            $response = Http::withToken((string) config('ai.openai.api_key'))
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('ai.openai.timeout', 20))
                ->post($this->endpoint(), $payload)
                ->throw();

            $body = $response->json();
            $raw = $this->responseText($body);
            $json = json_decode($raw, true);
            $json = is_array($json) ? $json : [];

            return [
                'json' => $json,
                'raw' => $raw,
                'mocked' => false,
                'error' => $json === [] ? 'OpenAI returned empty or invalid JSON.' : null,
                'metadata' => $this->metadata(
                    mocked: false,
                    httpStatus: $response->status(),
                    latencyMs: $this->latencyMs($startedAt),
                    usage: is_array(data_get($body, 'usage')) ? data_get($body, 'usage') : [],
                ),
            ];
        } catch (RequestException $exception) {
            return [
                'json' => [],
                'raw' => $exception->response?->body(),
                'mocked' => false,
                'error' => 'OpenAI request failed with HTTP '.$exception->response?->status().'.',
                'metadata' => $this->metadata(
                    mocked: false,
                    httpStatus: $exception->response?->status(),
                    latencyMs: $this->latencyMs($startedAt),
                ),
            ];
        } catch (\Throwable) {
            return [
                'json' => [],
                'raw' => null,
                'mocked' => false,
                'error' => 'OpenAI request failed safely.',
                'metadata' => $this->metadata(mocked: false, latencyMs: $this->latencyMs($startedAt)),
            ];
        }
    }

    public function isConfigured(): bool
    {
        return filled(config('ai.openai.api_key'));
    }

    private function shouldMock(): bool
    {
        return filter_var(config('ai.openai.mock_mode', true), FILTER_VALIDATE_BOOLEAN) || ! $this->isConfigured();
    }

    private function endpoint(): string
    {
        return rtrim((string) config('ai.openai.base_url'), '/').'/responses';
    }

    /** @param array<string, mixed> $body */
    private function responseText(array $body): string
    {
        $text = data_get($body, 'output_text');
        if (is_string($text)) {
            return $text;
        }

        $content = data_get($body, 'output.0.content.0.text');

        return is_string($content) ? $content : '';
    }

    /** @param array<string, mixed> $usage */
    private function metadata(bool $mocked, ?int $httpStatus = null, ?int $latencyMs = null, array $usage = []): array
    {
        return [
            'provider' => 'openai',
            'model' => config('ai.openai.model', 'gpt-5.6'),
            'mock_mode' => $mocked,
            'server_side_configured' => $this->isConfigured(),
            'http_status' => $httpStatus,
            'latency_ms' => $latencyMs,
            'input_tokens' => data_get($usage, 'input_tokens'),
            'output_tokens' => data_get($usage, 'output_tokens'),
            'total_tokens' => data_get($usage, 'total_tokens'),
        ];
    }

    private function latencyMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
