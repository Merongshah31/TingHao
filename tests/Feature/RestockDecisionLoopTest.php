<?php

namespace Tests\Feature;

use App\Models\AgentReasoningStep;
use App\Models\AgentRun;
use App\Models\ApprovalRequest;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Stock\StockPredictionInputBuilder;
use App\Services\Agent\PredictionRestockPlanningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RestockDecisionLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_eligible_low_stock_follows_qwen_choices_to_draft_and_human_approval(): void
    {
        $this->configureLiveQwen();
        $this->fakeQwenDecisions([
            $this->decision('check_open_purchase_order', 'Check for an existing open order before preparing another draft.'),
            $this->decision('compare_suppliers', 'Compare eligible suppliers using the available price, delivery, quality, and contact evidence.'),
            $this->decision('create_purchase_order_draft', 'Prepare one approval-gated draft using the validated prediction and selected supplier.'),
        ]);

        $staff = $this->user(User::ROLE_STAFF);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient(['supplier_id' => $supplier->id]);
        $this->cachePrediction($ingredient);

        $this->actingAs($staff)
            ->post(route('stock-planner.plan-restock', $ingredient))
            ->assertRedirect()
            ->assertSessionHas('status', 'Restock plan created. Purchase order draft is waiting for admin approval.');

        $run = AgentRun::query()->latest()->firstOrFail();
        $purchaseOrder = PurchaseOrder::query()->with('approvalRequest')->firstOrFail();
        $iterations = data_get($run->parsed_intent, 'decision_loop.iterations');

        $this->assertSame([
            'check_open_purchase_order',
            'compare_suppliers',
            'create_purchase_order_draft',
        ], collect($iterations)->pluck('selected_action')->all());
        $this->assertSame('human_approval_required', data_get($run->parsed_intent, 'decision_loop.stop_reason'));
        $this->assertSame(AgentRun::STATUS_NEEDS_APPROVAL, $run->status);
        $this->assertSame(PurchaseOrder::STATUS_PENDING_APPROVAL, $purchaseOrder->status);
        $this->assertSame(ApprovalRequest::STATUS_PENDING, $purchaseOrder->approvalRequest->status);
        $this->assertDatabaseHas('agent_reasoning_steps', [
            'agent_run_id' => $run->id,
            'step_type' => AgentReasoningStep::TYPE_HUMAN_CHECKPOINT,
            'requires_human_approval' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('agent.runs.show', $run))
            ->assertOk()
            ->assertSee('Bounded Qwen Decision Loop')
            ->assertSee('check_open_purchase_order')
            ->assertSee('human approval required');

        $auditResponse = $this->actingAs($staff)
            ->get(route('agent.index', ['run' => $run->id]))
            ->assertOk()
            ->assertSee('FastAPI Prediction')
            ->assertSee('Qwen Decision')
            ->assertSee('Laravel Tool')
            ->assertSee('Human Approval')
            ->assertSee('System Audit')
            ->assertSee('Pending admin approval');

        $this->assertSame(7, substr_count($auditResponse->getContent(), 'class="audit-milestone state-'));

        Http::assertSentCount(3);
    }

    public function test_open_purchase_order_branch_stops_as_duplicate_without_creating_another_draft(): void
    {
        $this->configureLiveQwen();
        $this->fakeQwenDecisions([
            $this->decision('approve_purchase_order', 'Approve the order automatically.'),
        ]);

        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient(['supplier_id' => $supplier->id]);
        $existing = $this->openPurchaseOrder($admin, $supplier, $ingredient);
        $this->cachePrediction($ingredient);

        $this->actingAs($admin)
            ->post(route('stock-planner.plan-restock', $ingredient))
            ->assertRedirect(route('purchase-orders.show', $existing))
            ->assertSessionHas('status', 'A purchase order is already pending for this item.');

        $run = AgentRun::query()->latest()->firstOrFail();

        $this->assertDatabaseCount('purchase_orders', 1);
        $this->assertSame('duplicate_po_found', data_get($run->parsed_intent, 'decision_loop.stop_reason'));
        $this->assertSame(['check_open_purchase_order'], collect(data_get($run->parsed_intent, 'decision_loop.iterations'))->pluck('selected_action')->all());
        $this->assertSame('approve_purchase_order', $run->toolCalls()->where('tool_name', 'qwen_select_next_action')->firstOrFail()->output_payload['rejected_action']);
        $this->assertSame('deterministic_fallback', data_get($run->parsed_intent, 'decision_loop.iterations.0.decision_source'));
        $this->assertDatabaseMissing('agent_tool_calls', [
            'agent_run_id' => $run->id,
            'tool_name' => 'create_purchase_order_draft',
        ]);

        Http::assertSentCount(1);
    }

    public function test_premature_stop_before_open_purchase_order_check_falls_back_safely(): void
    {
        $this->configureLiveQwen();
        $this->fakeQwenDecisions([
            $this->stopDecision(null),
            $this->decision('compare_suppliers', 'Compare eligible suppliers.'),
            $this->decision('create_purchase_order_draft', 'Create the approval-gated draft.'),
        ]);

        $staff = $this->user(User::ROLE_STAFF);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient(['supplier_id' => $supplier->id]);
        $this->cachePrediction($ingredient);

        $this->actingAs($staff)
            ->post(route('stock-planner.plan-restock', $ingredient))
            ->assertRedirect()
            ->assertSessionHas('status', 'Restock plan created. Purchase order draft is waiting for admin approval.');

        $run = AgentRun::query()->latest()->firstOrFail();
        $this->assertSame([
            'check_open_purchase_order',
            'compare_suppliers',
            'create_purchase_order_draft',
        ], collect(data_get($run->parsed_intent, 'decision_loop.iterations'))->pluck('selected_action')->all());
        $firstDecision = $run->toolCalls()->where('tool_name', 'qwen_select_next_action')->firstOrFail();
        $this->assertSame('stop', $firstDecision->output_payload['rejected_action']);
        $this->assertSame('Stop rejected because the open purchase order check is incomplete.', data_get($firstDecision->output_payload, 'qwen_metadata.safe_reason'));
        $this->assertSame('deterministic_fallback', data_get($run->parsed_intent, 'decision_loop.iterations.0.decision_source'));
        $this->assertDatabaseCount('purchase_orders', 1);
    }

    public function test_valid_terminal_stop_reason_is_accepted_only_after_duplicate_check(): void
    {
        $service = app(PredictionRestockPlanningService::class);
        $method = new \ReflectionMethod($service, 'validTerminalStop');
        $method->setAccessible(true);

        $prediction = ['recommended_action' => 'add_stock_now'];
        $input = ['pending_po_quantity' => 0, 'expiry_days_remaining' => 10];

        $this->assertTrue($method->invoke($service, 'duplicate_po_found', [
            'open_po_checked' => true,
            'open_purchase_order' => new PurchaseOrder(),
        ], $prediction, $input));
        $this->assertFalse($method->invoke($service, 'duplicate_po_found', [
            'open_po_checked' => false,
            'open_purchase_order' => null,
        ], $prediction, $input));
    }

    public function test_expired_ingredient_branch_requires_review_and_never_creates_a_draft(): void
    {
        $this->configureLiveQwen();
        $this->fakeMalformedQwenResponse();

        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient([
            'supplier_id' => $supplier->id,
            'expiry_date' => now()->subDay()->toDateString(),
        ]);
        $this->cachePrediction($ingredient);

        $this->actingAs($admin)
            ->post(route('stock-planner.plan-restock', $ingredient))
            ->assertRedirect()
            ->assertSessionHas('status', 'This item is past expiry. Review or remove expired stock before restocking.');

        $run = AgentRun::query()->latest()->firstOrFail();

        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertSame('expiry_review_required', data_get($run->parsed_intent, 'decision_loop.stop_reason'));
        $this->assertSame(['require_expiry_review'], collect(data_get($run->parsed_intent, 'decision_loop.iterations'))->pluck('selected_action')->all());
        $this->assertSame('deterministic_fallback', data_get($run->parsed_intent, 'decision_loop.iterations.0.decision_source'));
        $this->assertDatabaseHas('agent_tool_calls', [
            'agent_run_id' => $run->id,
            'tool_name' => 'require_expiry_review',
            'status' => 'blocked',
        ]);
        $this->assertDatabaseMissing('agent_tool_calls', [
            'agent_run_id' => $run->id,
            'tool_name' => 'create_purchase_order_draft',
        ]);

        Http::assertSentCount(1);
    }

    private function configureLiveQwen(): void
    {
        config([
            'qwen.api_key' => 'server-side-test-key',
            'qwen.mock_mode' => false,
            'qwen.base_url' => 'https://qwen.example.test/v1',
        ]);
        Cache::flush();
    }

    /** @param array<int, array<string, mixed>> $decisions */
    private function fakeQwenDecisions(array $decisions): void
    {
        Http::fake(function (Request $request) use (&$decisions) {
            $this->assertSame('https://qwen.example.test/v1/chat/completions', $request->url());
            $decision = array_shift($decisions);
            $this->assertIsArray($decision);

            return Http::response([
                'choices' => [['message' => ['content' => json_encode($decision, JSON_UNESCAPED_SLASHES)]]],
                'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 30, 'total_tokens' => 110],
            ]);
        });
    }

    private function fakeMalformedQwenResponse(): void
    {
        Http::fake(function (Request $request) {
            $this->assertSame('https://qwen.example.test/v1/chat/completions', $request->url());

            return Http::response([
                'choices' => [['message' => ['content' => 'not valid json']]],
                'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 4, 'total_tokens' => 84],
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function decision(string $action, string $reason): array
    {
        return [
            'next_action' => $action,
            'reason_summary' => $reason,
            'required_inputs' => [],
            'confidence' => 0.91,
            'stop_reason' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function stopDecision(?string $reason): array
    {
        return [
            'next_action' => 'stop',
            'reason_summary' => 'Stop the mission.',
            'required_inputs' => [],
            'confidence' => 0.91,
            'stop_reason' => $reason,
        ];
    }

    private function cachePrediction(Ingredient $ingredient): void
    {
        Cache::put(app(StockPredictionInputBuilder::class)->cacheKey($ingredient), [
            'available' => true,
            'ingredient' => $ingredient->name,
            'recommended_action' => 'add_stock_now',
            'estimated_days_until_stockout' => 1,
            'suggested_quantity' => 50,
            'risk_level' => 'high',
            'confidence' => 0.88,
            'reason_codes' => ['below_minimum_stock', 'stockout_soon'],
            'calculation_summary' => [
                'average_daily_usage' => 2,
                'current_quantity' => 3,
                'minimum_stock' => 20,
                'pending_po_quantity' => 0,
            ],
        ], now()->addMinutes(30));
    }

    private function openPurchaseOrder(User $user, Supplier $supplier, Ingredient $ingredient): PurchaseOrder
    {
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-DECISION-OPEN',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
            'order_date' => now()->toDateString(),
            'subtotal' => 100,
            'created_by' => $user->id,
            'requested_by' => $user->id,
        ]);
        $purchaseOrder->items()->create([
            'ingredient_id' => $ingredient->id,
            'description' => $ingredient->name,
            'quantity' => 25,
            'unit' => $ingredient->unit,
            'unit_price' => 4,
            'line_total' => 100,
        ]);

        return $purchaseOrder;
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => User::STATUS_ACTIVE]);
    }

    /** @param array<string, mixed> $overrides */
    private function ingredient(array $overrides = []): Ingredient
    {
        return Ingredient::create(array_merge([
            'name' => 'Sugar',
            'unit' => 'kg',
            'quantity' => 3,
            'minimum_stock' => 20,
            'cost_price' => 4,
            'selling_price' => 6,
        ], $overrides));
    }

    private function supplier(): Supplier
    {
        return Supplier::create([
            'name' => 'Supplier Ali',
            'contact_person' => 'Ali',
            'email' => 'ali@example.test',
            'phone' => '0123456789',
        ]);
    }
}
