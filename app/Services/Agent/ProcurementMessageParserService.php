<?php

namespace App\Services\Agent;

use App\Services\Qwen\QwenClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProcurementMessageParserService
{
    public function __construct(private readonly QwenClient $qwenClient)
    {
    }

    /**
     * @return array{parsed: array<string, mixed>, mocked: bool, raw: string|null, error: string|null, qwen_metadata: array<string, mixed>}
     */
    public function parse(string $message): array
    {
        $cacheKey = 'qwen.parse.'.hash('sha256', implode('|', [
            $this->normalizedCacheInput($message),
            (string) config('qwen.model', 'qwen-plus'),
            filter_var(config('qwen.mock_mode', true), FILTER_VALIDATE_BOOLEAN) ? 'mock' : 'live',
            filled(config('qwen.api_key')) ? 'configured' : 'not-configured',
        ]));
        $cacheMinutes = max(1, (int) config('qwen.cache_minutes', 30));

        return Cache::remember($cacheKey, now()->addMinutes($cacheMinutes), function () use ($message, $cacheKey, $cacheMinutes): array {
            $response = $this->qwenClient->generateJson($this->systemPrompt(), $message, [
                'max_tokens' => (int) config('qwen.max_tokens.parse', 350),
                'temperature' => (float) config('qwen.temperature', 0.2),
            ]);
            $parsed = $this->normalize($response['json'], $message);

            if ($response['mocked'] || $response['json'] === []) {
                $parsed = $this->fallbackParse($message);
            }

            return [
                'parsed' => $parsed,
                'mocked' => $response['mocked'],
                'raw' => $response['raw'],
                'error' => $response['error'],
                'qwen_metadata' => [
                    ...($response['metadata'] ?? []),
                    'cache_key' => $cacheKey,
                    'cache_minutes' => $cacheMinutes,
                ],
            ];
        });
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You parse messy bakery procurement messages for Ting Hao Inventory Management.
Extract procurement intent from the message. Return compact JSON only.
Do not include markdown, explanations, or raw chain-of-thought.
Return only JSON with this shape:
{
  "intent": "restock_request | supplier_confirmation | expiry_risk | general_stock_note",
  "ingredients": [{"name": "string", "quantity": number|null, "unit": "string|null"}],
  "supplier_name": "string|null",
  "urgency": "low | medium | high",
  "deadline": "string|null",
  "language": "ms | en | mixed",
  "summary": "Short summary",
  "decision_factors": ["Concise visible decision factor"],
  "risk_flags": ["Concise risk or approval flag"],
  "confidence": 0.0
}
Decision factors must be short, visible business reasons only.
PROMPT;
    }

    private function normalizedCacheInput(string $message): string
    {
        return Str::of($message)->lower()->squish()->toString();
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function normalize(array $json, string $message): array
    {
        $ingredients = collect($json['ingredients'] ?? [])
            ->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null))
            ->map(fn (array $item): array => [
                'name' => trim((string) $item['name']),
                'quantity' => is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : null,
                'unit' => filled($item['unit'] ?? null) ? trim((string) $item['unit']) : null,
            ])
            ->values()
            ->all();

        $intent = in_array($json['intent'] ?? null, ['restock_request', 'supplier_confirmation', 'expiry_risk', 'general_stock_note'], true)
            ? $json['intent']
            : $this->guessIntent($message);

        $urgency = in_array($json['urgency'] ?? null, ['low', 'medium', 'high'], true)
            ? $json['urgency']
            : $this->guessUrgency($message);

        return [
            'intent' => $intent,
            'ingredients' => $ingredients,
            'supplier_name' => filled($json['supplier_name'] ?? null) ? trim((string) $json['supplier_name']) : $this->guessSupplier($message),
            'urgency' => $urgency,
            'deadline' => filled($json['deadline'] ?? null) ? trim((string) $json['deadline']) : null,
            'language' => in_array($json['language'] ?? null, ['ms', 'en', 'mixed'], true) ? $json['language'] : $this->guessLanguage($message),
            'summary' => filled($json['summary'] ?? null) ? trim((string) $json['summary']) : $this->fallbackSummary($message, $intent),
            'decision_factors' => $this->normalizeStringList($json['decision_factors'] ?? null),
            'risk_flags' => $this->normalizeStringList($json['risk_flags'] ?? null),
            'confidence' => is_numeric($json['confidence'] ?? null) ? max(0, min(1, (float) $json['confidence'])) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackParse(string $message): array
    {
        $ingredientNames = [
            'gula' => 'sugar',
            'sugar' => 'sugar',
            'flour' => 'flour',
            'tepung' => 'flour',
            'milk' => 'milk',
            'susu' => 'milk',
            'butter' => 'butter',
            'yeast' => 'yeast',
        ];

        $ingredients = [];
        foreach ($ingredientNames as $needle => $normalized) {
            if (Str::contains(Str::lower($message), $needle)) {
                preg_match('/(\d+(?:\.\d+)?)\s*(kg|cartons?|packs?|pcs|litres?|l|g)?/i', $message, $quantityMatch);
                $ingredients[] = [
                    'name' => $normalized,
                    'quantity' => isset($quantityMatch[1]) ? (float) $quantityMatch[1] : null,
                    'unit' => $quantityMatch[2] ?? null,
                ];
            }
        }

        if ($ingredients === [] && preg_match('/plan\s+restock\s+for\s+(.+?)\.\s+/i', $message, $match)) {
            preg_match('/recommend\s+(\d+(?:\.\d+)?)\s*([a-zA-Z]+)/i', $message, $quantityMatch);
            $ingredients[] = [
                'name' => trim($match[1]),
                'quantity' => isset($quantityMatch[1]) ? (float) $quantityMatch[1] : null,
                'unit' => $quantityMatch[2] ?? null,
            ];
        }

        $ingredients = collect($ingredients)->unique('name')->values()->all();
        $intent = $this->guessIntent($message);

        return [
            'intent' => $intent,
            'ingredients' => $ingredients,
            'supplier_name' => $this->guessSupplier($message),
            'urgency' => $this->guessUrgency($message),
            'deadline' => Str::contains(Str::lower($message), 'friday') ? 'this Friday' : null,
            'language' => $this->guessLanguage($message),
            'summary' => $this->fallbackSummary($message, $intent),
            'decision_factors' => $this->fallbackDecisionFactors($message, $ingredients),
            'risk_flags' => $intent === 'restock_request' ? ['Admin approval required before order confirmation.'] : [],
            'confidence' => $ingredients === [] ? 0.55 : 0.82,
        ];
    }

    private function guessIntent(string $message): string
    {
        $lower = Str::lower($message);

        return match (true) {
            Str::contains($lower, ['confirmed', 'delivery', 'deliver']) => 'supplier_confirmation',
            Str::contains($lower, ['expiring', 'expired', 'expiry', 'luput']) => 'expiry_risk',
            Str::contains($lower, ['abis', 'low', 'order', 'restock', 'short']) => 'restock_request',
            default => 'general_stock_note',
        };
    }

    private function guessUrgency(string $message): string
    {
        $lower = Str::lower($message);

        return match (true) {
            Str::contains($lower, ['abis', 'urgent', 'empty', 'out', 'today']) => 'high',
            Str::contains($lower, ['low', 'weekend', 'soon', 'friday']) => 'medium',
            default => 'low',
        };
    }

    private function guessSupplier(string $message): ?string
    {
        if (preg_match('/supplier\s+([a-z0-9 &.-]+)/i', $message, $match)) {
            $name = preg_split('/\b(confirmed|confirm|delivery|deliver|dari|from|tak|can|this|friday|monday|tuesday|wednesday|thursday|saturday|sunday)\b/i', $match[1])[0] ?? $match[1];

            return 'Supplier '.trim($name, " \t\n\r\0\x0B.,?");
        }

        return null;
    }

    private function guessLanguage(string $message): string
    {
        $lower = Str::lower($message);
        $hasMalay = Str::contains($lower, ['gula', 'dah', 'abis', 'nak', 'tak', 'susu', 'tepung']);
        $hasEnglish = preg_match('/\b(the|and|are|low|supplier|confirmed|delivery|stock|order)\b/i', $message) === 1;

        return $hasMalay && $hasEnglish ? 'mixed' : ($hasMalay ? 'ms' : 'en');
    }

    private function fallbackSummary(string $message, string $intent): string
    {
        return 'Procurement note classified as '.str_replace('_', ' ', $intent).': '.Str::limit($message, 140);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn ($item): bool => is_string($item) && filled($item))
            ->map(fn (string $item): string => trim($item))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $ingredients
     * @return array<int, string>
     */
    private function fallbackDecisionFactors(string $message, array $ingredients): array
    {
        $factors = [];

        foreach ($ingredients as $ingredient) {
            $name = $ingredient['name'] ?? 'ingredient';
            $quantity = $ingredient['quantity'] ?? null;
            $unit = $ingredient['unit'] ?? null;
            $factors[] = $quantity
                ? "{$name} is mentioned with requested quantity {$quantity} {$unit}."
                : "{$name} is mentioned in the message.";
        }

        if ($supplier = $this->guessSupplier($message)) {
            $factors[] = "{$supplier} is mentioned as a supplier hint.";
        }

        return $factors === [] ? ['Message requires inventory review.'] : $factors;
    }
}
