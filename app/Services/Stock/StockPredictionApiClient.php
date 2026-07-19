<?php

namespace App\Services\Stock;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class StockPredictionApiClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function predict(array $payload): array
    {
        $apiUrl = rtrim((string) config('stock_prediction.api_url'), '/');

        if ($apiUrl === '') {
            return $this->unavailable('Prediction service URL is not configured');
        }

        try {
            $response = Http::timeout((int) config('stock_prediction.timeout', 8))
                ->acceptJson()
                ->post($apiUrl.'/predict-stock-action', $payload);

            if (! $response->successful()) {
                return $this->unavailable('Prediction service unavailable', [
                    'status' => $response->status(),
                ]);
            }

            $data = $response->json();

            if (! is_array($data)) {
                return $this->unavailable('Prediction service returned an invalid response');
            }

            return $this->applyBusinessRules($this->normalize($data), $payload);
        } catch (Throwable $exception) {
            Log::warning('Stock prediction service unavailable', [
                'message' => $exception->getMessage(),
            ]);

            return $this->unavailable();
        }
    }

    public function actionLabel(?string $action): string
    {
        return match ($action) {
            'add_stock_now' => 'Add Stock Now',
            'add_stock_soon' => 'Add Stock Soon',
            'buy_less' => 'Buy Less',
            'do_not_buy' => 'Do Not Buy',
            'use_before_expiry' => 'Use Before Expiry',
            'review_expired_stock' => 'Review Expired Stock',
            default => 'Monitor',
        };
    }

    public function reasonLabel(string $reasonCode): string
    {
        return Str::of($reasonCode)->replace('_', ' ')->title()->toString();
    }

    public function actionTone(?string $action): string
    {
        return match ($action) {
            'add_stock_now', 'review_expired_stock' => 'danger',
            'add_stock_soon', 'use_before_expiry' => 'warning',
            'buy_less' => 'info',
            'do_not_buy' => 'ok',
            default => 'neutral',
        };
    }

    /**
     * Reconcile cached or fresh FastAPI output with current inventory facts.
     *
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function applyBusinessRules(array $prediction, array $input): array
    {
        if (! ($prediction['available'] ?? false)) {
            return $prediction;
        }

        $currentQuantity = $this->number($input['current_quantity'] ?? null);
        $minimumStock = $this->number($input['minimum_stock'] ?? null);
        $expiryDays = is_numeric($input['expiry_days_remaining'] ?? null)
            ? (int) $input['expiry_days_remaining']
            : null;
        $isExpired = $expiryDays !== null && $expiryDays < 0 && $currentQuantity > 0;
        $isNearExpiry = $expiryDays !== null && $expiryDays >= 0 && $expiryDays <= 7 && $currentQuantity > 0;
        $isBelowMinimum = $minimumStock > 0 && $currentQuantity <= $minimumStock;
        $isHighStock = $minimumStock > 0 && $currentQuantity >= ($minimumStock * 3);
        $action = is_string($prediction['recommended_action'] ?? null)
            ? $prediction['recommended_action']
            : 'monitor';
        $displayAction = $action;

        if ($isExpired) {
            $action = 'do_not_buy';
            $displayAction = 'review_expired_stock';
        } elseif ($isBelowMinimum) {
            $action = 'add_stock_now';
            $displayAction = $action;
        } elseif ($isHighStock && $isNearExpiry) {
            $action = 'do_not_buy';
            $displayAction = $action;
        } elseif ($isNearExpiry) {
            $action = 'use_before_expiry';
            $displayAction = $action;
        }

        $suggestedQuantity = is_numeric($prediction['suggested_quantity'] ?? null)
            ? (float) $prediction['suggested_quantity']
            : null;

        if (in_array($action, ['add_stock_now', 'add_stock_soon'], true)) {
            if ($suggestedQuantity === null || ! is_finite($suggestedQuantity) || $suggestedQuantity <= 0) {
                $suggestedQuantity = max(
                    ($minimumStock * 2) - $currentQuantity,
                    $minimumStock,
                    1.0,
                );
            }

            $suggestedQuantity = round($suggestedQuantity, 2);
        } else {
            $suggestedQuantity = null;
        }

        $reasonCodes = collect($prediction['reason_codes'] ?? [])
            ->filter(fn ($reasonCode): bool => is_string($reasonCode) && $reasonCode !== '')
            ->when($isExpired, fn ($codes) => $codes->push('expired_stock_review'))
            ->when($isBelowMinimum && ! $isExpired, fn ($codes) => $codes->push('below_minimum_stock'))
            ->unique()
            ->values();

        return [
            ...$prediction,
            'recommended_action' => $action,
            'display_action' => $displayAction,
            'action_label' => $this->actionLabel($displayAction),
            'action_tone' => $this->actionTone($displayAction),
            'suggested_quantity' => $suggestedQuantity,
            'purchase_guidance' => $this->purchaseGuidance($displayAction),
            'is_expired' => $isExpired,
            'reason_codes' => $reasonCodes->all(),
            'reason_labels' => $reasonCodes
                ->map(fn (string $reasonCode): string => $this->reasonLabel($reasonCode))
                ->all(),
        ];
    }

    private function purchaseGuidance(string $displayAction): string
    {
        return match ($displayAction) {
            'do_not_buy' => 'Current stock is sufficient. No purchase suggested.',
            'buy_less' => 'Current stock is sufficient. Buy less on the next order.',
            'use_before_expiry' => 'Use existing stock first and review expiry risk.',
            'review_expired_stock' => 'Review expired stock before making another purchase.',
            default => 'No purchase suggested. Continue monitoring stock usage.',
        };
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function unavailable(string $message = 'Prediction service unavailable', array $context = []): array
    {
        return [
            'available' => false,
            'message' => $message,
            'context' => $context,
            'recommended_action' => null,
            'action_label' => 'Unavailable',
            'estimated_days_until_stockout' => null,
            'suggested_quantity' => null,
            'risk_level' => null,
            'risk_label' => 'Unknown',
            'confidence' => null,
            'confidence_percent' => null,
            'reason_codes' => [],
            'reason_labels' => [],
            'calculation_summary' => [],
            'raw_response' => null,
            'predicted_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $action = is_string($data['recommended_action'] ?? null)
            ? $data['recommended_action']
            : 'monitor';
        $riskLevel = is_string($data['risk_level'] ?? null)
            ? $data['risk_level']
            : null;
        $confidence = is_numeric($data['confidence'] ?? null)
            ? (float) $data['confidence']
            : null;
        $reasonCodes = collect($data['reason_codes'] ?? [])
            ->filter(fn ($reasonCode): bool => is_string($reasonCode) && $reasonCode !== '')
            ->values()
            ->all();

        return [
            'available' => true,
            'message' => null,
            'ingredient' => $data['ingredient'] ?? null,
            'recommended_action' => $action,
            'action_label' => $this->actionLabel($action),
            'action_tone' => $this->actionTone($action),
            'estimated_days_until_stockout' => is_numeric($data['estimated_days_until_stockout'] ?? null)
                ? (int) $data['estimated_days_until_stockout']
                : null,
            'suggested_quantity' => is_numeric($data['suggested_quantity'] ?? null)
                ? (float) $data['suggested_quantity']
                : null,
            'risk_level' => $riskLevel,
            'risk_label' => $riskLevel ? Str::of($riskLevel)->replace('_', ' ')->title()->toString() : 'Unknown',
            'confidence' => $confidence,
            'confidence_percent' => $confidence !== null ? (int) round($confidence * 100) : null,
            'reason_codes' => $reasonCodes,
            'reason_labels' => collect($reasonCodes)
                ->map(fn (string $reasonCode): string => $this->reasonLabel($reasonCode))
                ->values()
                ->all(),
            'calculation_summary' => is_array($data['calculation_summary'] ?? null)
                ? $data['calculation_summary']
                : [],
            'raw_response' => $data,
            'predicted_at' => now()->toDateTimeString(),
        ];
    }
}
