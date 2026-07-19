<?php

namespace Tests\Feature;

use App\Models\AgentRun;
use App\Models\ApprovalRequest;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Stock\StockPredictionInputBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StockPlannerPredictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_planner_calls_prediction_service_with_compact_summary_and_caches_result(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);
        Cache::flush();
        Http::fake([
            'http://127.0.0.1:8001/predict-stock-action' => Http::response($this->predictionResponse(), 200),
        ]);

        $admin = $this->user(User::ROLE_ADMIN);
        $ingredient = $this->ingredient();

        StockMovement::create([
            'ingredient_id' => $ingredient->id,
            'type' => StockMovement::TYPE_OUT,
            'quantity' => 14,
            'quantity_before' => 17,
            'quantity_after' => 3,
            'reason' => 'Production use',
            'created_by' => $admin->id,
            'created_at' => now()->subDays(2),
        ]);

        $this->actingAs($admin)
            ->get(route('stock-planner.index'))
            ->assertOk()
            ->assertSee('Stock Planner')
            ->assertSee('Add Stock Now')
            ->assertSee('Below Minimum Stock');

        $this->actingAs($admin)
            ->get(route('stock-planner.index'))
            ->assertOk()
            ->assertSee('Add Stock Now');

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'http://127.0.0.1:8001/predict-stock-action'
                && $payload['ingredient'] === 'Sugar'
                && $payload['current_quantity'] === 3.0
                && $payload['minimum_stock'] === 20.0
                && $payload['stock_out_last_7_days'] === 14.0
                && array_key_exists('stock_out_last_30_days', $payload)
                && ! array_key_exists('stock_movements', $payload);
        });
    }

    public function test_refresh_prediction_forces_new_service_call(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);
        Cache::flush();
        Http::fake([
            'http://127.0.0.1:8001/predict-stock-action' => Http::sequence()
                ->push($this->predictionResponse('monitor'), 200)
                ->push($this->predictionResponse('add_stock_now'), 200),
        ]);

        $admin = $this->user(User::ROLE_ADMIN);
        $ingredient = $this->ingredient([
            'quantity' => 30,
            'minimum_stock' => 20,
        ]);

        $this->actingAs($admin)
            ->get(route('stock-planner.prediction', $ingredient))
            ->assertOk()
            ->assertSee('Monitor')
            ->assertSee('Click Explain with Qwen');

        $this->actingAs($admin)
            ->post(route('stock-planner.refresh-prediction', $ingredient))
            ->assertRedirect(route('stock-planner.prediction', $ingredient));

        $this->actingAs($admin)
            ->get(route('stock-planner.prediction', $ingredient))
            ->assertOk()
            ->assertSee('Add Stock Now');

        Http::assertSentCount(2);
    }

    public function test_refresh_prediction_only_forces_selected_ingredient(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);
        Cache::flush();
        Http::fake([
            'http://127.0.0.1:8001/predict-stock-action' => Http::response($this->predictionResponse('monitor'), 200),
        ]);

        $admin = $this->user(User::ROLE_ADMIN);
        $sugar = $this->ingredient();
        $flour = $this->ingredient([
            'name' => 'Flour',
            'quantity' => 8,
            'minimum_stock' => 15,
        ]);

        $this->actingAs($admin)
            ->get(route('stock-planner.index', ['view' => 'cards']))
            ->assertOk()
            ->assertSee($sugar->name)
            ->assertSee($flour->name);

        $this->actingAs($admin)
            ->post(route('stock-planner.refresh-prediction', $flour))
            ->assertRedirect(route('stock-planner.prediction', $flour));

        Http::assertSentCount(3);

        $flourRequests = collect(Http::recorded())
            ->filter(fn (array $record): bool => ($record[0]->data()['ingredient'] ?? null) === 'Flour');

        $this->assertCount(2, $flourRequests);
    }

    public function test_prediction_service_outage_does_not_break_stock_planner(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);
        Cache::flush();
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $staff = $this->user(User::ROLE_STAFF);
        $ingredient = $this->ingredient();

        $this->actingAs($staff)
            ->get(route('stock-planner.prediction', $ingredient))
            ->assertOk()
            ->assertSee('Prediction service unavailable')
            ->assertSee('Compact Input Sent');
    }

    public function test_qwen_explains_prediction_with_compact_facts_and_uses_cache(): void
    {
        config([
            'qwen.api_key' => 'secret-qwen-key',
            'qwen.mock_mode' => false,
            'qwen.model' => 'qwen-plus',
            'qwen.max_tokens.stock_reasoning' => 300,
            'qwen.stock_reasoning_cache_minutes' => 30,
        ]);
        Cache::flush();
        Http::fake(function ($request) {
            if ($request->url() === 'http://127.0.0.1:8001/predict-stock-action') {
                return Http::response($this->predictionResponse(), 200);
            }

            return Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Add sugar stock today',
                            'summary' => 'Sugar is below minimum stock and may run out within 1 day.',
                            'business_reason' => 'Restocking today helps avoid production delay.',
                            'recommended_next_step' => 'Prepare a purchase order draft for admin approval.',
                            'warning' => null,
                            'user_friendly_action' => 'Plan Restock',
                            'confidence_label' => 'High',
                        ]),
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 90,
                    'completion_tokens' => 45,
                    'total_tokens' => 135,
                ],
            ], 200);
        });

        $admin = $this->user(User::ROLE_ADMIN);
        $ingredient = $this->ingredient();

        $this->actingAs($admin)
            ->get(route('stock-planner.prediction', $ingredient))
            ->assertOk()
            ->assertSee('Qwen Explanation')
            ->assertSee('Click Explain with Qwen')
            ->assertDontSee('secret-qwen-key');

        $this->actingAs($admin)
            ->post(route('stock-planner.explain', $ingredient))
            ->assertRedirect(route('stock-planner.prediction', $ingredient))
            ->assertSessionHas('status', 'Qwen explanation generated in English.');

        $this->actingAs($admin)
            ->get(route('stock-planner.prediction', $ingredient))
            ->assertOk()
            ->assertSee('Add sugar stock today')
            ->assertSee('AI explanation generated from latest prediction.');

        $this->actingAs($admin)
            ->post(route('stock-planner.explain', $ingredient))
            ->assertRedirect(route('stock-planner.prediction', $ingredient))
            ->assertSessionHas('status', 'Qwen explanation regenerated in English.');

        Http::assertSentCount(3);
        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/chat/completions')) {
                return false;
            }

            $payload = $request->data();
            $systemPrompt = data_get($payload, 'messages.0.content', '');
            $prompt = data_get($payload, 'messages.1.content', '');

            return ($payload['max_tokens'] ?? null) === 300
                && ($payload['temperature'] ?? null) === 0.2
                && str_contains($systemPrompt, 'English only')
                && str_contains($systemPrompt, 'Do not use Malay words')
                && ! str_contains($systemPrompt, 'Malay-English mixed')
                && str_contains($prompt, '"ingredient":"Sugar"')
                && str_contains($prompt, '"recommended_action":"add_stock_now"')
                && ! str_contains($prompt, 'stock_movements')
                && ! str_contains(json_encode($payload), 'secret-qwen-key');
        });
    }

    public function test_qwen_stock_explanation_rejects_malay_mixed_output(): void
    {
        config([
            'qwen.api_key' => 'secret-qwen-key',
            'qwen.mock_mode' => false,
        ]);
        Cache::flush();
        Http::fake(function ($request) {
            if ($request->url() === 'http://127.0.0.1:8001/predict-stock-action') {
                return Http::response($this->predictionResponse(), 200);
            }

            return Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Stok gula perlu tambah',
                            'summary' => 'Gula rendah dan perlu beli segera.',
                            'business_reason' => 'Demand naik kerana hujung minggu.',
                            'recommended_next_step' => 'Sediakan draf PO untuk kelulusan.',
                            'warning' => 'Jangan tunggu lama.',
                            'user_friendly_action' => 'Plan Restock',
                            'confidence_label' => 'Medium-high',
                        ]),
                    ],
                ]],
            ], 200);
        });

        $admin = $this->user(User::ROLE_ADMIN);
        $ingredient = $this->ingredient();

        $this->actingAs($admin)
            ->post(route('stock-planner.explain', $ingredient))
            ->assertRedirect(route('stock-planner.prediction', $ingredient))
            ->assertSessionHas('status', 'Qwen explanation generated in English.');

        $this->actingAs($admin)
            ->get(route('stock-planner.prediction', $ingredient))
            ->assertOk()
            ->assertSee('Sugar Stock Alert')
            ->assertSee('Restocking helps reduce the risk of production disruption')
            ->assertDontSee('Stok')
            ->assertDontSee('segera')
            ->assertDontSee('kerana')
            ->assertDontSee('Demand naik')
            ->assertDontSee('kelulusan');
    }

    public function test_qwen_failure_shows_fallback_without_breaking_prediction(): void
    {
        config([
            'qwen.api_key' => 'secret-qwen-key',
            'qwen.mock_mode' => false,
        ]);
        Cache::flush();
        Http::fake(function ($request) {
            if ($request->url() === 'http://127.0.0.1:8001/predict-stock-action') {
                return Http::response($this->predictionResponse(), 200);
            }

            return Http::response(['error' => 'temporary failure'], 500);
        });

        $staff = $this->user(User::ROLE_STAFF);
        $ingredient = $this->ingredient();

        $this->actingAs($staff)
            ->post(route('stock-planner.explain', $ingredient))
            ->assertRedirect(route('stock-planner.prediction', $ingredient))
            ->assertSessionHas('error', 'Prediction is available, but AI explanation is temporarily unavailable.');
    }

    public function test_calendar_view_uses_prediction_data_without_calling_qwen(): void
    {
        config([
            'qwen.api_key' => 'secret-qwen-key',
            'qwen.mock_mode' => false,
        ]);
        Cache::flush();
        Http::fake([
            'http://127.0.0.1:8001/predict-stock-action' => Http::response($this->predictionResponse('add_stock_now'), 200),
        ]);

        $staff = $this->user(User::ROLE_STAFF);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient(['supplier_id' => $supplier->id]);

        $this->actingAs($staff)
            ->get(route('stock-planner.index', ['view' => 'calendar']))
            ->assertOk()
            ->assertSee('Calendar View')
            ->assertSee('stock-calendar-scroll', false)
            ->assertSee('Add Stock')
            ->assertSee($ingredient->name)
            ->assertSee('Plan Restock with TingHao Agent')
            ->assertDontSee('Calendar Demo')
            ->assertDontSee('Smart Stock Memory Planner');

        $this->actingAs($staff)
            ->get(route('stock-planner.index', ['view' => 'calendar']))
            ->assertOk()
            ->assertSee('Calendar View');

        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/chat/completions'));
    }

    public function test_prediction_view_formats_quantities_without_numeric_unit_noise(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);
        Cache::flush();
        Http::fake([
            'http://127.0.0.1:8001/predict-stock-action' => Http::response($this->predictionResponse(), 200),
        ]);

        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier(['name' => 'bahtera barem']);
        $ingredient = $this->ingredient([
            'name' => 'cadburry choc',
            'unit' => '10 botol',
            'quantity' => 0,
            'minimum_stock' => 2,
            'supplier_id' => $supplier->id,
        ]);

        $this->actingAs($admin)
            ->get(route('stock-planner.index', ['view' => 'cards']))
            ->assertOk()
            ->assertSee('Cadbury Choc')
            ->assertSee('Bahtera Barem')
            ->assertSee('0 bottle')
            ->assertSee('2 bottle')
            ->assertDontSee('0.00 10 botol')
            ->assertDontSee('2.00 10 botol');

        $this->assertSame('cadburry choc', $ingredient->fresh()->name);
        $this->assertSame('bahtera barem', $supplier->fresh()->name);
    }

    public function test_zero_add_stock_quantity_uses_safe_fallback_for_display_and_po(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);
        Cache::flush();
        $response = $this->predictionResponse('add_stock_now');
        $response['suggested_quantity'] = 0;
        Http::fake([
            'http://127.0.0.1:8001/predict-stock-action' => Http::response($response, 200),
        ]);

        $staff = $this->user(User::ROLE_STAFF);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient(['supplier_id' => $supplier->id]);

        $this->actingAs($staff)
            ->get(route('stock-planner.prediction', $ingredient))
            ->assertOk()
            ->assertSee('Suggested Quantity')
            ->assertSee('37.00 kg')
            ->assertSee('Plan Restock with TingHao Agent')
            ->assertDontSee('<strong>0.00 kg</strong>', false);

        $this->actingAs($staff)
            ->post(route('stock-planner.plan-restock', $ingredient))
            ->assertRedirect();

        $this->assertSame(37.0, (float) PurchaseOrder::firstOrFail()->items()->firstOrFail()->quantity);
    }

    public function test_non_purchase_and_expired_actions_hide_suggested_quantity(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);
        Cache::flush();
        $response = $this->predictionResponse('do_not_buy');
        $response['suggested_quantity'] = 0;
        Http::fake([
            'http://127.0.0.1:8001/predict-stock-action' => Http::response($response, 200),
        ]);

        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient([
            'name' => 'Expired Butter',
            'quantity' => 12,
            'minimum_stock' => 25,
            'expiry_date' => now()->subDay()->toDateString(),
            'supplier_id' => $supplier->id,
        ]);

        $this->actingAs($admin)
            ->get(route('stock-planner.prediction', $ingredient))
            ->assertOk()
            ->assertSee('Review Expired Stock')
            ->assertSee('This item is past expiry. Review or remove expired stock before restocking.')
            ->assertSee('Review expired stock before making another purchase.')
            ->assertDontSee('Suggested Quantity')
            ->assertDontSee('Plan Restock with TingHao Agent')
            ->assertSee('Technical Audit Details')
            ->assertSee('For judges/developers only. No API keys or raw chain-of-thought are shown.');
    }

    public function test_pending_po_quantity_hides_duplicate_restock_action_on_detail(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);
        Cache::flush();
        Http::fake([
            'http://127.0.0.1:8001/predict-stock-action' => Http::response($this->predictionResponse('add_stock_now'), 200),
        ]);

        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient(['supplier_id' => $supplier->id]);
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-PENDING-STOCK-1',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
            'order_date' => now()->toDateString(),
            'subtotal' => 100,
            'created_by' => $admin->id,
            'requested_by' => $admin->id,
        ]);
        $purchaseOrder->items()->create([
            'ingredient_id' => $ingredient->id,
            'description' => $ingredient->name,
            'quantity' => 25,
            'unit' => $ingredient->unit,
            'unit_price' => 4,
            'line_total' => 100,
        ]);

        $this->actingAs($admin)
            ->get(route('stock-planner.prediction', $ingredient))
            ->assertOk()
            ->assertSee('A pending purchase order already exists for this item.')
            ->assertSee('View Pending Purchase Order')
            ->assertSee(route('purchase-orders.show', $purchaseOrder), false)
            ->assertDontSee('Plan Restock with TingHao Agent');
    }

    public function test_calendar_limits_day_and_right_panel_signal_lists(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);
        Cache::flush();
        Http::fake([
            'http://127.0.0.1:8001/predict-stock-action' => Http::response($this->predictionResponse('add_stock_now'), 200),
        ]);

        $staff = $this->user(User::ROLE_STAFF);

        foreach (range(1, 6) as $number) {
            $this->ingredient(['name' => 'Calendar Item '.$number]);
        }

        $response = $this->actingAs($staff)
            ->get(route('stock-planner.index', ['view' => 'calendar']))
            ->assertOk()
            ->assertSee('+4 in advice')
            ->assertSee('+1 more signals available in Prediction View');
        $html = $response->getContent();

        $this->assertSame(3, substr_count($html, 'calendar-badge badge-danger'));
        $this->assertSame(4, substr_count($html, ' - Add Stock Now'));
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/chat/completions'));
    }

    public function test_dashboard_uses_cached_prediction_signals_without_calling_qwen(): void
    {
        config([
            'qwen.api_key' => 'secret-qwen-key',
            'qwen.mock_mode' => false,
        ]);
        Cache::flush();
        Http::fake();

        $admin = $this->user(User::ROLE_ADMIN);
        $ingredient = $this->ingredient();
        $cacheKey = app(StockPredictionInputBuilder::class)->cacheKey($ingredient);

        Cache::put($cacheKey, [
            ...$this->predictionResponse(),
            'available' => true,
            'action_label' => 'Add Stock Now',
            'action_tone' => 'danger',
            'risk_label' => 'High',
            'confidence_percent' => 88,
            'reason_labels' => ['Below Minimum Stock'],
        ], now()->addMinutes(30));

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Stock Prediction Signals')
            ->assertSee('Add Stock Now');

        Http::assertSentCount(0);
    }

    public function test_add_stock_prediction_can_create_restock_po_draft_for_admin_approval(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);
        Cache::flush();
        Http::fake([
            'http://127.0.0.1:8001/predict-stock-action' => Http::response($this->predictionResponse('add_stock_now'), 200),
        ]);

        $staff = $this->user(User::ROLE_STAFF);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient(['supplier_id' => $supplier->id]);

        $this->actingAs($staff)
            ->post(route('stock-planner.plan-restock', $ingredient))
            ->assertRedirect()
            ->assertSessionHas('status', 'Restock plan created. Purchase order draft is waiting for admin approval.');

        $purchaseOrder = PurchaseOrder::query()->with(['items', 'approvalRequest', 'agentRun'])->firstOrFail();

        $this->assertSame(PurchaseOrder::STATUS_PENDING_APPROVAL, $purchaseOrder->status);
        $this->assertSame($supplier->id, $purchaseOrder->supplier_id);
        $this->assertSame($staff->id, $purchaseOrder->requested_by);
        $this->assertSame('Created from FastAPI Stock Prediction. Admin approval required.', $purchaseOrder->notes);
        $this->assertSame(50.0, (float) $purchaseOrder->items->first()->quantity);
        $this->assertSame(ApprovalRequest::STATUS_PENDING, $purchaseOrder->approvalRequest->status);
        $this->assertSame('stock_prediction_restock', $purchaseOrder->agentRun->input_type);
        $this->assertSame(AgentRun::STATUS_NEEDS_APPROVAL, $purchaseOrder->agentRun->status);
        $this->assertDatabaseHas('agent_tool_calls', ['tool_name' => 'read_stock_prediction']);
        $this->assertDatabaseHas('agent_tool_calls', ['tool_name' => 'create_purchase_order_draft']);
        $this->assertDatabaseHas('agent_tool_calls', ['tool_name' => 'create_approval_request']);

        $this->actingAs($staff)
            ->post(route('purchase-orders.approve', $purchaseOrder))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Sugar restock plan waiting approval')
            ->assertSee('PO draft created from stock prediction');

        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/chat/completions'));
    }

    public function test_stock_planner_does_not_create_po_for_non_buy_prediction_actions(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);
        Cache::flush();
        Http::fake([
            'http://127.0.0.1:8001/predict-stock-action' => Http::response($this->predictionResponse('do_not_buy'), 200),
        ]);

        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient([
            'supplier_id' => $supplier->id,
            'quantity' => 80,
            'minimum_stock' => 20,
        ]);

        $this->actingAs($admin)
            ->post(route('stock-planner.plan-restock', $ingredient))
            ->assertRedirect(route('stock-planner.prediction', $ingredient))
            ->assertSessionHas('error', 'Buying is not recommended for this item right now.');

        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertDatabaseCount('agent_runs', 0);
    }

    public function test_stock_planner_prevents_duplicate_pending_purchase_order_for_same_ingredient(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);
        Cache::flush();
        Http::fake([
            'http://127.0.0.1:8001/predict-stock-action' => Http::response($this->predictionResponse('add_stock_soon'), 200),
        ]);

        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient(['supplier_id' => $supplier->id]);
        $existingPurchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-2026-0001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
            'order_date' => now()->toDateString(),
            'subtotal' => 100,
            'created_by' => $admin->id,
            'requested_by' => $admin->id,
        ]);
        $existingPurchaseOrder->items()->create([
            'ingredient_id' => $ingredient->id,
            'description' => $ingredient->name,
            'quantity' => 25,
            'unit' => $ingredient->unit,
            'unit_price' => 4,
            'line_total' => 100,
        ]);

        $this->actingAs($admin)
            ->post(route('stock-planner.plan-restock', $ingredient))
            ->assertRedirect(route('purchase-orders.show', $existingPurchaseOrder))
            ->assertSessionHas('status', 'A purchase order is already pending for this item.');

        $this->assertDatabaseCount('purchase_orders', 1);
        $this->assertDatabaseCount('agent_runs', 1);
        $this->assertSame('duplicate_po_found', data_get(AgentRun::latest()->firstOrFail()->parsed_intent, 'decision_loop.stop_reason'));
    }

    public function test_stock_planner_handles_missing_supplier_without_creating_purchase_order(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);
        Cache::flush();
        Http::fake([
            'http://127.0.0.1:8001/predict-stock-action' => Http::response($this->predictionResponse('add_stock_now'), 200),
        ]);

        $staff = $this->user(User::ROLE_STAFF);
        $ingredient = $this->ingredient();

        $this->actingAs($staff)
            ->get(route('stock-planner.prediction', $ingredient))
            ->assertOk()
            ->assertSee('Assign supplier before restock.')
            ->assertDontSee('Plan Restock with TingHao Agent');

        $this->actingAs($staff)
            ->post(route('stock-planner.plan-restock', $ingredient))
            ->assertRedirect()
            ->assertSessionHas('status', 'No supplier found. Please assign a supplier before creating a purchase order.');

        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertDatabaseHas('agent_runs', [
            'input_type' => 'stock_prediction_restock',
            'status' => AgentRun::STATUS_COMPLETED,
        ]);
        $this->assertDatabaseHas('agent_tool_calls', [
            'tool_name' => 'create_purchase_order_draft',
            'status' => 'skipped',
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

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

    private function supplier(array $overrides = []): Supplier
    {
        return Supplier::create(array_merge([
            'name' => 'Supplier Ali',
            'contact_person' => 'Ali',
            'email' => 'ali@example.test',
            'phone' => '0123456789',
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function predictionResponse(string $action = 'add_stock_now'): array
    {
        return [
            'ingredient' => 'Sugar',
            'recommended_action' => $action,
            'estimated_days_until_stockout' => $action === 'monitor' ? 12 : 1,
            'suggested_quantity' => $action === 'monitor' ? 0 : 50,
            'risk_level' => $action === 'monitor' ? 'low' : 'high',
            'confidence' => 0.88,
            'reason_codes' => [
                $action === 'monitor' ? 'stock_level_acceptable' : 'below_minimum_stock',
            ],
            'calculation_summary' => [
                'average_daily_usage' => 2,
                'current_quantity' => 3,
                'minimum_stock' => 20,
                'pending_po_quantity' => 0,
            ],
        ];
    }
}
