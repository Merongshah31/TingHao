<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Procurement\GptProcurementReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GptProcurementReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_run_review_and_result_is_displayed(): void
    {
        [$user, $ingredient] = $this->fixture();
        $this->bindReview([
            'recommended_supplier_id' => 4,
            'recommended_quantity' => 12,
            'risk_level' => 'medium',
            'reasoning_summary' => 'Use the ranked supplier before stockout.',
            'cost_observation' => 'Historical price is stable.',
            'delivery_risk' => 'Lead time is moderate.',
            'stockout_risk' => 'Stock may run out soon.',
            'confidence' => 0.9,
            'human_approval_required' => true,
        ]);

        $this->actingAs($user)
            ->post(route('stock-planner.gpt-review', $ingredient))
            ->assertRedirect(route('stock-planner.prediction', $ingredient))
            ->assertSessionHas('status', 'GPT-5.6 Review completed. No purchase order was created.');

        $this->actingAs($user)
            ->get(route('stock-planner.prediction', $ingredient))
            ->assertOk()
            ->assertSee('GPT-5.6 Review')
            ->assertSee('Use the ranked supplier before stockout.')
            ->assertSee('Human approval required');
    }

    public function test_invalid_provider_result_shows_safe_fallback(): void
    {
        [$user, $ingredient] = $this->fixture();
        $this->bindReview([
            'recommended_supplier_id' => null,
            'recommended_quantity' => 0,
            'risk_level' => 'high',
            'reasoning_summary' => 'Procurement review could not be validated safely.',
            'cost_observation' => '',
            'delivery_risk' => '',
            'stockout_risk' => '',
            'confidence' => 0,
            'human_approval_required' => true,
        ]);

        $this->actingAs($user)
            ->post(route('stock-planner.gpt-review', $ingredient))
            ->assertRedirect(route('stock-planner.prediction', $ingredient))
            ->assertSessionHas('error');
    }

    public function test_unauthorized_user_cannot_run_review(): void
    {
        [, $ingredient] = $this->fixture();
        $user = User::factory()->create(['role' => 'manager', 'status' => User::STATUS_ACTIVE]);

        $this->actingAs($user)
            ->post(route('stock-planner.gpt-review', $ingredient))
            ->assertForbidden();
    }

    /** @return array{0: User, 1: Ingredient} */
    private function fixture(): array
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);
        Cache::flush();
        Http::fake(['http://127.0.0.1:8001/predict-stock-action' => Http::response([
            'recommended_action' => 'add_stock_now',
            'suggested_quantity' => 12,
            'confidence' => 0.9,
            'risk_level' => 'high',
            'calculation_summary' => ['average_daily_usage' => 2],
        ], 200)]);

        $user = User::factory()->create(['role' => User::ROLE_STAFF, 'status' => User::STATUS_ACTIVE]);
        $supplier = Supplier::create(['name' => 'Review Supplier', 'email' => 'supplier@example.test']);
        $ingredient = Ingredient::create([
            'name' => 'Sugar',
            'unit' => 'kg',
            'quantity' => 3,
            'minimum_stock' => 20,
            'supplier_id' => $supplier->id,
        ]);

        return [$user, $ingredient];
    }

    private function bindReview(array $review): void
    {
        $this->app->instance(GptProcurementReviewService::class, new class($review) extends GptProcurementReviewService {
            public function __construct(private readonly array $review) {}

            public function review(Ingredient $ingredient, float|int $currentStock, float|int $minimumStock, float|int $averageDailyUsage, array $pendingPurchaseOrders = [], ?array $supplierComparisonResults = null, ?array $stockPrediction = null): array
            {
                return $this->review;
            }
        });
    }
}
