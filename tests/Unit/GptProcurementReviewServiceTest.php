<?php

namespace Tests\Unit;

use App\Contracts\AI\StructuredDecisionProvider;
use App\Models\Ingredient;
use App\Services\Agent\SupplierComparisonService;
use App\Services\Procurement\GptProcurementReviewService;
use Mockery;
use Tests\TestCase;

class GptProcurementReviewServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_valid_provider_response_is_normalized_and_approval_is_forced(): void
    {
        $result = $this->reviewWith(['recommended_supplier_id' => 7, 'recommended_quantity' => 12, 'risk_level' => 'medium', 'reasoning_summary' => 'Use the ranked supplier.', 'confidence' => 0.9, 'human_approval_required' => false]);

        $this->assertSame(7, $result['recommended_supplier_id']);
        $this->assertSame(12.0, $result['recommended_quantity']);
        $this->assertTrue($result['human_approval_required']);
    }

    public function test_invalid_supplier_id_returns_safe_fallback(): void { $this->assertFallback(['recommended_supplier_id' => 99, 'recommended_quantity' => 2, 'risk_level' => 'low', 'confidence' => 0.8]); }

    public function test_malformed_or_missing_response_returns_safe_fallback(): void { $this->assertFallback([]); }

    public function test_quantity_below_one_returns_safe_fallback(): void { $this->assertFallback(['recommended_supplier_id' => 7, 'recommended_quantity' => 0, 'risk_level' => 'low', 'confidence' => 0.8]); }

    public function test_confidence_outside_range_returns_safe_fallback(): void { $this->assertFallback(['recommended_supplier_id' => 7, 'recommended_quantity' => 2, 'risk_level' => 'low', 'confidence' => 1.1]); }

    private function assertFallback(array $response): void
    {
        $result = $this->reviewWith($response);
        $this->assertNull($result['recommended_supplier_id']);
        $this->assertSame(0.0, $result['recommended_quantity']);
        $this->assertTrue($result['human_approval_required']);
    }

    private function reviewWith(array $response): array
    {
        $provider = Mockery::mock(StructuredDecisionProvider::class);
        $provider->shouldReceive('generateJson')->once()->andReturn(['json' => $response, 'raw' => null, 'mocked' => true, 'error' => null, 'metadata' => []]);
        $comparison = ['recommended_supplier' => ['id' => 7], 'suppliers' => [['id' => 7]], 'decision_factors' => []];
        $service = new GptProcurementReviewService($provider, Mockery::mock(SupplierComparisonService::class));

        return $service->review(new Ingredient(['id' => 1, 'name' => 'Flour', 'unit' => 'kg']), 2, 10, 1.5, [], $comparison);
    }
}
