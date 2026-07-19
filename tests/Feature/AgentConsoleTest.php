<?php

namespace Tests\Feature;

use App\Models\AgentRun;
use App\Models\AgentReasoningStep;
use App\Models\AgentToolCall;
use App\Models\ApprovalRequest;
use App\Models\ExpiryLossRecommendation;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\SupplierEmailDraft;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Agent\ProcurementMessageParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AgentConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_run_agent_in_mock_mode_and_view_own_result(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);

        $staff = $this->user(User::ROLE_STAFF);
        $supplier = Supplier::create([
            'name' => 'Supplier Ali',
            'email' => 'orders@supplierali.test',
            'phone' => '+60 14-555 0199',
        ]);
        Ingredient::create([
            'supplier_id' => $supplier->id,
            'name' => 'Caster Sugar',
            'unit' => 'kg',
            'quantity' => 8,
            'minimum_stock' => 20,
            'cost_price' => 3,
            'selling_price' => 5,
        ]);

        $this->actingAs($staff)
            ->get(route('agent.index'))
            ->assertOk()
            ->assertSee('Agent Audit Console')
            ->assertSee('Mock demo mode')
            ->assertSee('Agent Audit Visualizer')
            ->assertSee('Live Audit Milestones')
            ->assertSee('How TingHao Autopilot Works')
            ->assertDontSee('Template View')
            ->assertDontSee('Live Run View')
            ->assertDontSee('Smart Procurement Inbox')
            ->assertDontSee('Run an audit mission')
            ->assertDontSee('Paste staff or supplier message here')
            ->assertDontSee('Run Agent Audit')
            ->assertDontSee('Visible activity')
            ->assertSee('Pending PO Approvals')
            ->assertSee('Email Drafts Waiting Approval')
            ->assertSee('Expiry Risk RM')
            ->assertSee('Recent Agent Missions');

        $response = $this->actingAs($staff)
            ->post(route('agent.run'), [
                'input_text' => 'gula dah abis boss, nak order 50kg dari Supplier Ali tak?',
            ])
            ->assertRedirect();

        $agentRun = AgentRun::firstOrFail();
        $response->assertRedirect(route('agent.runs.show', $agentRun));

        $this->assertTrue($agentRun->qwen_mocked);
        $agentRun->refresh();
        $this->assertSame(AgentRun::STATUS_NEEDS_APPROVAL, $agentRun->status);
        $this->assertSame('restock_request', $agentRun->parsed_intent['intent']);
        $this->assertCount(6, AgentToolCall::where('agent_run_id', $agentRun->id)->get());
        $this->assertDatabaseHas('agent_reasoning_steps', [
            'agent_run_id' => $agentRun->id,
            'step_type' => AgentReasoningStep::TYPE_OBSERVE,
        ]);
        $this->assertDatabaseHas('agent_reasoning_steps', [
            'agent_run_id' => $agentRun->id,
            'step_type' => AgentReasoningStep::TYPE_HUMAN_CHECKPOINT,
            'requires_human_approval' => true,
        ]);
        $this->assertDatabaseHas('purchase_orders', [
            'agent_run_id' => $agentRun->id,
            'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
            'requested_by' => $staff->id,
        ]);
        $this->assertDatabaseHas('approval_requests', [
            'agent_run_id' => $agentRun->id,
            'status' => ApprovalRequest::STATUS_PENDING,
            'requested_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->get(route('agent.index', ['run' => $agentRun->id]))
            ->assertOk()
            ->assertSee('Run Summary')
            ->assertSee('Restock Request')
            ->assertSee('Agent Mission Status')
            ->assertSee('Procurement Workflow Status')
            ->assertSee('Human Approval')
            ->assertSee('Pending')
            ->assertSee('PO Draft Prepared')
            ->assertSee('Supplier Ali')
            ->assertSee('create_purchase_order_draft');

        $this->actingAs($staff)
            ->get(route('agent.runs.show', $agentRun))
            ->assertOk()
            ->assertSee('Autopilot Procurement Mission')
            ->assertSee('Mission Summary')
            ->assertSee('Next Best Action')
            ->assertSee('Approve Purchase Order')
            ->assertSee('Business Impact')
            ->assertSee('Autopilot Safety Guardrails')
            ->assertSee('Human Checkpoint')
            ->assertSee('Execution / Outcome')
            ->assertSee('Caster Sugar')
            ->assertSee('Supplier Ali')
            ->assertSee('parse_procurement_message')
            ->assertSee('lookup_inventory')
            ->assertSee('plan_restock_quantity')
            ->assertSee('rank_suppliers')
            ->assertSee('create_purchase_order_draft')
            ->assertSee('create_approval_request')
            ->assertSee('Reasoning Activity')
            ->assertDontSee('hidden chain-of-thought');
    }

    public function test_agent_audit_shows_live_qwen_mode_without_exposing_api_key(): void
    {
        config([
            'qwen.api_key' => 'secret-qwen-key',
            'qwen.mock_mode' => false,
            'qwen.model' => 'qwen-plus',
        ]);

        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->get(route('agent.index'))
            ->assertOk()
            ->assertSee('Agent Audit Console')
            ->assertSee('Live Qwen mode')
            ->assertSee('qwen-plus')
            ->assertSee('Configured')
            ->assertDontSee('secret-qwen-key');
    }

    public function test_agent_visualizer_shows_one_real_record_audit_for_expiry_workflow(): void
    {
        Http::fake();
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);

        $admin = $this->user(User::ROLE_ADMIN);
        $ingredient = Ingredient::create([
            'name' => 'Unsalted Butter',
            'unit' => 'kg',
            'quantity' => 12,
            'minimum_stock' => 5,
            'cost_price' => 18,
            'selling_price' => 24,
            'expiry_date' => now()->addDays(4)->toDateString(),
        ]);
        $agentRun = AgentRun::create([
            'user_id' => $admin->id,
            'input_text' => 'Scan inventory for expiry loss risk.',
            'input_type' => 'expiry_loss_prevention',
            'status' => AgentRun::STATUS_COMPLETED,
            'parsed_intent' => [
                'intent' => 'expiry_loss_prevention',
                'matched_ingredients' => [['ingredient_id' => $ingredient->id]],
                'total_potential_loss' => 216,
            ],
            'final_summary' => 'Expiry loss scan completed.',
            'qwen_mocked' => true,
        ]);

        foreach (['scan_expiring_ingredients', 'calculate_expiry_loss', 'generate_expiry_recommendation', 'save_expiry_recommendation'] as $toolName) {
            $agentRun->toolCalls()->create([
                'tool_name' => $toolName,
                'status' => 'completed',
                'input_payload' => [],
                'output_payload' => [],
            ]);
        }

        ExpiryLossRecommendation::create([
            'agent_run_id' => $agentRun->id,
            'ingredient_id' => $ingredient->id,
            'quantity_at_risk' => 12,
            'unit' => 'kg',
            'cost_price' => 18,
            'potential_loss' => 216,
            'expiry_date' => now()->addDays(4)->toDateString(),
            'days_until_expiry' => 4,
            'recommendation_title' => 'Use Unsalted Butter before expiry',
            'recommendation_body' => 'Prioritize this stock in production.',
            'status' => ExpiryLossRecommendation::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('agent.index', ['run' => $agentRun->id]))
            ->assertOk()
            ->assertSee('Agent Audit Visualizer')
            ->assertSeeInOrder([
                'Run Summary',
                'Live Audit Milestones',
                'Selected Step Details',
                'Technical Audit Details',
            ])
            ->assertSee('Expiry Loss Prevention')
            ->assertSee('calculate_expiry_loss')
            ->assertSee('Human Approval')
            ->assertSee('Qwen Decision')
            ->assertSee('Laravel Tool')
            ->assertSee('System Audit')
            ->assertSee('No PO draft was created for this mission')
            ->assertSee('Supplier communication was not used in this mission')
            ->assertSee('Technical Audit Details')
            ->assertDontSee('Template View')
            ->assertDontSee('No separate decision at this step');

        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'id="agent-audit-visualizer"'));
        $this->assertSame(7, substr_count($html, 'class="audit-milestone state-'));
        $this->assertSame(1, substr_count($html, 'id="selected-audit-milestone"'));
        $this->assertSame(0, substr_count($html, 'class="audit-timeline-event'));
        $this->assertStringNotContainsString('workflow-selected-step-details', $html);
        Http::assertNothingSent();
    }

    public function test_qwen_parser_caches_identical_live_parse_requests_and_sends_token_limits(): void
    {
        config([
            'qwen.api_key' => 'secret-qwen-key',
            'qwen.mock_mode' => false,
            'qwen.model' => 'qwen-plus',
            'qwen.max_tokens.parse' => 350,
            'qwen.temperature' => 0.2,
            'qwen.cache_minutes' => 30,
        ]);
        Cache::flush();
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'intent' => 'restock_request',
                            'ingredients' => [[
                                'name' => 'sugar',
                                'quantity' => 50,
                                'unit' => 'kg',
                            ]],
                            'supplier_name' => 'Supplier Ali',
                            'urgency' => 'high',
                            'deadline' => null,
                            'language' => 'en',
                            'summary' => 'Staff requests urgent 50kg sugar restock from Supplier Ali.',
                            'decision_factors' => ['Sugar quantity and supplier are explicit.'],
                            'risk_flags' => ['Admin approval required.'],
                            'confidence' => 0.87,
                        ]),
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 120,
                    'completion_tokens' => 55,
                    'total_tokens' => 175,
                ],
            ], 200),
        ]);

        $parser = app(ProcurementMessageParserService::class);

        $first = $parser->parse(' Order 50kg sugar from Supplier Ali ');
        $second = $parser->parse('order   50kg sugar from supplier ali');

        $this->assertFalse($first['mocked']);
        $this->assertSame('restock_request', $first['parsed']['intent']);
        $this->assertSame($first['parsed'], $second['parsed']);
        $this->assertSame(175, $first['qwen_metadata']['total_tokens']);
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return ($payload['max_tokens'] ?? null) === 350
                && ($payload['temperature'] ?? null) === 0.2
                && data_get($payload, 'response_format.type') === 'json_object'
                && ! str_contains(json_encode($payload), 'secret-qwen-key');
        });
    }

    public function test_staff_cannot_view_another_users_agent_run_but_admin_can(): void
    {
        $owner = $this->user(User::ROLE_STAFF);
        $otherStaff = $this->user(User::ROLE_STAFF);
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = Supplier::create(['name' => 'Supplier Ali', 'email' => 'ali@example.test']);

        $agentRun = AgentRun::create([
            'user_id' => $owner->id,
            'input_text' => 'Flour and milk are low.',
            'input_type' => 'procurement_message',
            'status' => AgentRun::STATUS_COMPLETED,
            'parsed_intent' => ['intent' => 'restock_request'],
            'final_summary' => 'Demo run.',
            'qwen_mocked' => true,
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-OWNER-0001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'order_date' => now()->toDateString(),
            'subtotal' => 10,
            'created_by' => $owner->id,
            'requested_by' => $owner->id,
        ]);
        $purchaseOrder->supplierEmailDrafts()->create([
            'supplier_id' => $supplier->id,
            'subject' => 'Owner draft',
            'body' => 'Owner body',
            'status' => SupplierEmailDraft::STATUS_DRAFT,
        ]);

        $this->actingAs($otherStaff)
            ->get(route('agent.runs.show', $agentRun))
            ->assertForbidden();

        $this->actingAs($otherStaff)
            ->get(route('agent.index'))
            ->assertOk()
            ->assertSee('Email Drafts Waiting Approval')
            ->assertSeeInOrder(['Email Drafts Waiting Approval', '0']);

        $this->actingAs($admin)
            ->get(route('agent.runs.show', $agentRun))
            ->assertOk()
            ->assertSee('Flour and milk are low.');
    }

    public function test_admin_can_approve_or_reject_agent_purchase_order_and_staff_cannot(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);

        $staff = $this->user(User::ROLE_STAFF);
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = Supplier::create([
            'name' => 'Fresh Dairy Partners',
            'email' => 'supply@freshdairy.test',
            'phone' => '+60 16-778 4455',
        ]);
        Ingredient::create([
            'supplier_id' => $supplier->id,
            'name' => 'Whole Milk Carton',
            'unit' => 'carton',
            'quantity' => 5,
            'minimum_stock' => 20,
            'cost_price' => 4,
            'selling_price' => 6,
        ]);

        $this->actingAs($staff)->post(route('agent.run'), [
            'input_text' => 'Flour and milk are low. Weekend sales may be high.',
        ]);

        $purchaseOrder = PurchaseOrder::firstOrFail();

        $this->actingAs($staff)
            ->post(route('purchase-orders.approve', $purchaseOrder))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('purchase-orders.approve', $purchaseOrder))
            ->assertRedirect();

        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $purchaseOrder->fresh()->status);
        $this->assertSame(ApprovalRequest::STATUS_APPROVED, $purchaseOrder->approvalRequest()->firstOrFail()->status);
        $this->assertDatabaseHas('agent_reasoning_steps', [
            'agent_run_id' => $purchaseOrder->agent_run_id,
            'title' => 'Purchase order approved by admin',
            'requires_human_approval' => true,
        ]);

        $this->actingAs($staff)->post(route('agent.run'), [
            'input_text' => 'milk low, order 10 cartons from Fresh Dairy',
        ]);

        $secondPurchaseOrder = PurchaseOrder::query()->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('purchase-orders.reject', $secondPurchaseOrder), [
                'review_notes' => 'Wait for updated sales forecast.',
            ])
            ->assertRedirect();

        $this->assertSame(PurchaseOrder::STATUS_REJECTED, $secondPurchaseOrder->fresh()->status);
        $this->assertSame('Wait for updated sales forecast.', $secondPurchaseOrder->approvalRequest()->firstOrFail()->review_notes);
    }

    public function test_admin_can_generate_approve_and_mark_supplier_email_draft_sent_without_real_email(): void
    {
        Mail::fake();
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);

        $staff = $this->user(User::ROLE_STAFF);
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = Supplier::create([
            'name' => 'Supplier Ali',
            'email' => 'orders@supplierali.test',
            'phone' => '+60 14-555 0199',
        ]);
        Ingredient::create([
            'supplier_id' => $supplier->id,
            'name' => 'Caster Sugar',
            'unit' => 'kg',
            'quantity' => 8,
            'minimum_stock' => 20,
            'cost_price' => 3,
            'selling_price' => 5,
        ]);

        $this->actingAs($staff)->post(route('agent.run'), [
            'input_text' => 'gula dah abis boss, nak order 50kg dari Supplier Ali tak?',
        ]);

        $purchaseOrder = PurchaseOrder::firstOrFail();

        $this->actingAs($admin)
            ->post(route('purchase-orders.approve', $purchaseOrder))
            ->assertRedirect();

        $this->actingAs($staff)
            ->post(route('purchase-orders.generate-email-draft', $purchaseOrder))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('purchase-orders.generate-email-draft', $purchaseOrder))
            ->assertRedirect();

        $draft = SupplierEmailDraft::firstOrFail();
        $this->assertSame(SupplierEmailDraft::STATUS_DRAFT, $draft->status);
        $this->assertStringContainsString($purchaseOrder->po_number, $draft->subject);
        $this->assertStringContainsString('Caster Sugar', $draft->body);
        $this->assertSame('qwen-plus', $draft->qwen_model);
        $this->assertTrue((bool) data_get($draft->qwen_metadata, 'mock_mode'));

        $this->actingAs($staff)
            ->post(route('supplier-email-drafts.approve', $draft))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('supplier-email-drafts.approve', $draft))
            ->assertRedirect();

        $this->assertSame(SupplierEmailDraft::STATUS_APPROVED, $draft->fresh()->status);
        $this->assertNotNull($draft->fresh()->approved_at);

        $this->actingAs($admin)
            ->post(route('supplier-email-drafts.mark-sent', $draft))
            ->assertRedirect();

        $this->assertSame(SupplierEmailDraft::STATUS_SENT, $draft->fresh()->status);
        $this->assertSame(PurchaseOrder::STATUS_SENT, $purchaseOrder->fresh()->status);
        $this->assertNotNull($draft->fresh()->sent_at);
        $this->assertDatabaseHas('agent_tool_calls', ['tool_name' => 'generate_supplier_email_draft']);
        $this->assertDatabaseHas('agent_tool_calls', ['tool_name' => 'approved_purchase_order_detected']);
        $this->assertDatabaseHas('agent_tool_calls', ['tool_name' => 'build_supplier_email_context']);
        $this->assertDatabaseHas('agent_tool_calls', ['tool_name' => 'call_qwen_email_draft']);
        $this->assertDatabaseHas('agent_tool_calls', ['tool_name' => 'save_supplier_email_draft']);
        $this->assertDatabaseHas('agent_tool_calls', ['tool_name' => 'wait_for_admin_email_approval']);
        $this->assertDatabaseHas('agent_tool_calls', ['tool_name' => 'approve_supplier_email_draft']);
        $this->assertDatabaseHas('agent_tool_calls', ['tool_name' => 'mark_supplier_email_sent']);
        $this->assertDatabaseHas('agent_tool_calls', ['tool_name' => 'mark_email_sent']);
        $this->assertDatabaseHas('agent_reasoning_steps', [
            'agent_run_id' => $purchaseOrder->agent_run_id,
            'title' => 'Email approval required',
            'requires_human_approval' => true,
        ]);
        $this->assertDatabaseHas('agent_reasoning_steps', [
            'agent_run_id' => $purchaseOrder->agent_run_id,
            'title' => 'Supplier email marked sent by admin',
            'requires_human_approval' => true,
        ]);

        Mail::assertNothingSent();
    }

    public function test_existing_supplier_email_draft_is_reused_without_calling_qwen_again(): void
    {
        config([
            'qwen.mock_mode' => false,
            'qwen.api_key' => 'secret-qwen-key',
        ]);
        Http::fake();

        $admin = $this->user(User::ROLE_ADMIN);
        $purchaseOrder = $this->approvedPurchaseOrder($admin);
        $draft = $purchaseOrder->supplierEmailDrafts()->create([
            'supplier_id' => $purchaseOrder->supplier_id,
            'subject' => 'Existing draft',
            'body' => 'Existing draft body',
            'status' => SupplierEmailDraft::STATUS_DRAFT,
        ]);

        $this->actingAs($admin)
            ->post(route('purchase-orders.generate-email-draft', $purchaseOrder))
            ->assertRedirect(route('supplier-email-drafts.show', $draft));

        $this->assertDatabaseCount('supplier_email_drafts', 1);
        Http::assertSentCount(0);
    }

    public function test_qwen_email_draft_failure_does_not_create_fake_draft_when_not_mocked(): void
    {
        config([
            'qwen.mock_mode' => false,
            'qwen.api_key' => 'secret-qwen-key',
            'qwen.max_tokens.email_draft' => 500,
        ]);
        Http::fake([
            '*' => Http::response(['error' => 'temporary unavailable'], 500),
        ]);

        $admin = $this->user(User::ROLE_ADMIN);
        $purchaseOrder = $this->approvedPurchaseOrder($admin);

        $this->actingAs($admin)
            ->post(route('purchase-orders.generate-email-draft', $purchaseOrder))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHasErrors(['supplier_email_draft' => 'Qwen email drafting is temporarily unavailable. You can try again later.']);

        $this->assertDatabaseCount('supplier_email_drafts', 0);
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $purchaseOrder->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_live_qwen_supplier_email_draft_uses_compact_payload_and_can_regenerate(): void
    {
        config([
            'qwen.mock_mode' => false,
            'qwen.api_key' => 'secret-qwen-key',
            'qwen.max_tokens.email_draft' => 500,
        ]);
        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'subject' => 'Purchase Order PO-2026-0005 - Sugar Restock Request',
                                'body' => "Dear Supplier Ali,\n\nPlease confirm sugar availability.\n\nThank you.\n\nTing Hao Team",
                            ]),
                        ],
                    ]],
                    'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 50, 'total_tokens' => 130],
                ], 200)
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'subject' => 'Updated PO Sugar Restock Request',
                                'body' => "Dear Supplier Ali,\n\nPlease confirm earliest delivery.\n\nThank you.\n\nTing Hao Team",
                            ]),
                        ],
                    ]],
                    'usage' => ['prompt_tokens' => 70, 'completion_tokens' => 40, 'total_tokens' => 110],
                ], 200),
        ]);

        $admin = $this->user(User::ROLE_ADMIN);
        $purchaseOrder = $this->approvedPurchaseOrder($admin);

        $this->actingAs($admin)
            ->post(route('purchase-orders.generate-email-draft', $purchaseOrder))
            ->assertRedirect();

        $draft = SupplierEmailDraft::firstOrFail();
        $this->assertSame('Purchase Order PO-2026-0005 - Sugar Restock Request', $draft->subject);
        $this->assertSame('qwen-plus', $draft->qwen_model);
        $this->assertSame(130, data_get($draft->qwen_metadata, 'total_tokens'));

        $this->actingAs($admin)
            ->post(route('supplier-email-drafts.regenerate', $draft))
            ->assertRedirect(route('supplier-email-drafts.show', $draft));

        $this->assertSame('Updated PO Sugar Restock Request', $draft->fresh()->subject);
        $this->assertDatabaseCount('supplier_email_drafts', 1);

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $prompt = data_get($payload, 'messages.1.content', '');

            return ($payload['max_tokens'] ?? null) === 500
                && str_contains($prompt, '"purchase_order"')
                && str_contains($prompt, '"supplier"')
                && str_contains($prompt, '"items"')
                && str_contains($prompt, '"business_context"')
                && ! str_contains($prompt, 'agent_tool_calls')
                && ! str_contains($prompt, 'stock_movements')
                && ! str_contains(json_encode($payload), 'secret-qwen-key');
        });
    }

    public function test_low_stock_page_can_trigger_agent_restock_plan(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);

        $staff = $this->user(User::ROLE_STAFF);
        $supplier = Supplier::create([
            'name' => 'Supplier Bake',
            'email' => 'orders@supplierbake.test',
            'phone' => '+60 12-111 2222',
        ]);
        $ingredient = Ingredient::create([
            'supplier_id' => $supplier->id,
            'name' => 'Baking Powder',
            'unit' => 'kg',
            'quantity' => 2,
            'minimum_stock' => 10,
            'cost_price' => 7,
            'selling_price' => 9,
        ]);

        $response = $this->actingAs($staff)
            ->post(route('alerts.restock.agent-plan', $ingredient))
            ->assertRedirect();

        $purchaseOrder = PurchaseOrder::firstOrFail();
        $agentRun = AgentRun::firstOrFail();

        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $this->assertSame(AgentRun::STATUS_NEEDS_APPROVAL, $agentRun->status);
        $this->assertSame(PurchaseOrder::STATUS_PENDING_APPROVAL, $purchaseOrder->status);
        $this->assertSame($staff->id, $purchaseOrder->requested_by);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'ingredient_id' => $ingredient->id,
            'description' => 'Baking Powder',
        ]);
        $this->assertDatabaseHas('approval_requests', [
            'agent_run_id' => $agentRun->id,
            'status' => ApprovalRequest::STATUS_PENDING,
        ]);
    }

    public function test_admin_can_run_expiry_loss_scan_and_manage_recommendation_status(): void
    {
        config(['qwen.mock_mode' => true, 'qwen.api_key' => null]);

        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);

        Ingredient::create([
            'name' => 'Unsalted Butter',
            'sku' => 'DRY-BUTTER-500G',
            'unit' => 'kg',
            'quantity' => 12,
            'minimum_stock' => 5,
            'cost_price' => 18,
            'selling_price' => 24,
            'expiry_date' => now()->addDays(5)->toDateString(),
        ]);
        Ingredient::create([
            'name' => 'Expired Yeast',
            'unit' => 'pack',
            'quantity' => 8,
            'minimum_stock' => 5,
            'cost_price' => 6,
            'selling_price' => 8,
            'expiry_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($staff)
            ->post(route('agent.expiry-loss.scan'))
            ->assertForbidden();

        $response = $this->actingAs($admin)
            ->post(route('agent.expiry-loss.scan'))
            ->assertRedirect();

        $agentRun = AgentRun::where('input_type', 'expiry_loss_scan')->firstOrFail();
        $response->assertRedirect(route('agent.runs.show', $agentRun));

        $recommendation = ExpiryLossRecommendation::firstOrFail();

        $this->assertSame('Unsalted Butter', $recommendation->ingredient->name);
        $this->assertSame('216.00', $recommendation->potential_loss);
        $this->assertSame(ExpiryLossRecommendation::STATUS_ACTIVE, $recommendation->status);
        $this->assertStringContainsString('Unsalted Butter', $recommendation->recommendation_body);
        $this->assertDatabaseMissing('expiry_loss_recommendations', [
            'ingredient_id' => Ingredient::where('name', 'Expired Yeast')->firstOrFail()->id,
        ]);

        foreach (['scan_expiring_ingredients', 'calculate_expiry_loss', 'generate_expiry_recommendation', 'save_expiry_recommendation'] as $toolName) {
            $this->assertDatabaseHas('agent_tool_calls', [
                'agent_run_id' => $agentRun->id,
                'tool_name' => $toolName,
            ]);
        }
        $this->assertDatabaseHas('agent_reasoning_steps', [
            'agent_run_id' => $agentRun->id,
            'step_type' => AgentReasoningStep::TYPE_DECISION,
            'title' => 'Expiry action recommended',
        ]);
        $this->assertDatabaseHas('agent_reasoning_steps', [
            'agent_run_id' => $agentRun->id,
            'step_type' => AgentReasoningStep::TYPE_HUMAN_CHECKPOINT,
            'requires_human_approval' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('agent.expiry-loss'))
            ->assertOk()
            ->assertSee('Expiry Loss Prevention')
            ->assertSee('RM 216.00');

        $this->actingAs($staff)
            ->post(route('expiry-loss-recommendations.complete', $recommendation))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('expiry-loss-recommendations.review', $recommendation))
            ->assertRedirect();

        $this->assertSame(ExpiryLossRecommendation::STATUS_REVIEWED, $recommendation->fresh()->status);
        $this->assertSame($admin->id, $recommendation->fresh()->reviewed_by);

        $this->actingAs($admin)
            ->post(route('expiry-loss-recommendations.complete', $recommendation))
            ->assertRedirect();

        $this->assertSame(ExpiryLossRecommendation::STATUS_COMPLETED, $recommendation->fresh()->status);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function approvedPurchaseOrder(User $user): PurchaseOrder
    {
        $supplier = Supplier::create([
            'name' => 'Supplier Ali',
            'email' => 'orders@supplierali.test',
            'phone' => '+60 14-555 0199',
        ]);
        $ingredient = Ingredient::create([
            'supplier_id' => $supplier->id,
            'name' => 'Sugar',
            'unit' => 'kg',
            'quantity' => 3,
            'minimum_stock' => 20,
            'cost_price' => 4,
            'selling_price' => 6,
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-2026-0005',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'order_date' => now()->toDateString(),
            'subtotal' => 200,
            'notes' => 'Created from TingHao stock prediction',
            'agent_reasoning' => 'Stock prediction indicates sugar may run out soon.',
            'created_by' => $user->id,
            'requested_by' => $user->id,
            'approved_by' => $user->id,
        ]);
        $purchaseOrder->items()->create([
            'ingredient_id' => $ingredient->id,
            'description' => $ingredient->name,
            'quantity' => 50,
            'unit' => 'kg',
            'unit_price' => 4,
            'line_total' => 200,
        ]);

        return $purchaseOrder;
    }
}
