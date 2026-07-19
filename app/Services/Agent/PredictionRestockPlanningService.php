<?php

namespace App\Services\Agent;

use App\Models\AgentRun;
use App\Models\AgentToolCall;
use App\Models\ApprovalRequest;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Qwen\QwenClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PredictionRestockPlanningService
{
    private const RESTOCK_ACTIONS = ['add_stock_now', 'add_stock_soon'];

    private const MAX_DECISION_ITERATIONS = 4;

    private const ALLOWED_ACTIONS = [
        'get_inventory',
        'read_stock_prediction',
        'check_open_purchase_order',
        'compare_suppliers',
        'create_purchase_order_draft',
        'request_human_approval',
        'require_expiry_review',
        'stop',
    ];

    private const STOP_REASONS = [
        'human_approval_required',
        'duplicate_po_found',
        'expiry_review_required',
        'completed',
        'blocked',
        'max_iterations_reached',
    ];

    public function __construct(
        private readonly SupplierComparisonService $supplierComparison,
        private readonly QwenClient $qwenClient,
        private readonly ReasoningActivityService $reasoningActivity,
    ) {}

    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $predictionInput
     * @return array{allowed: bool, message: string|null, pending_purchase_order: PurchaseOrder|null, supplier_comparison: array<string, mixed>|null}
     */
    public function availability(Ingredient $ingredient, array $prediction, array $predictionInput = []): array
    {
        $action = (string) ($prediction['recommended_action'] ?? 'monitor');
        $expiryDays = is_numeric($predictionInput['expiry_days_remaining'] ?? null)
            ? (int) $predictionInput['expiry_days_remaining']
            : null;
        $pendingQuantity = is_numeric($predictionInput['pending_po_quantity'] ?? null)
            ? (float) $predictionInput['pending_po_quantity']
            : 0.0;
        $pendingPurchaseOrder = $pendingQuantity > 0
            ? $this->pendingPurchaseOrderFor($ingredient)
            : null;

        if ($expiryDays !== null && $expiryDays < 0) {
            return [
                'allowed' => false,
                'message' => 'This item is past expiry. Review or remove expired stock before restocking.',
                'pending_purchase_order' => $pendingPurchaseOrder,
                'supplier_comparison' => null,
            ];
        }

        if ($pendingQuantity > 0) {
            return [
                'allowed' => false,
                'message' => 'A pending purchase order already exists for this item.',
                'pending_purchase_order' => $pendingPurchaseOrder,
                'supplier_comparison' => null,
            ];
        }

        if (! in_array($action, self::RESTOCK_ACTIONS, true)) {
            return [
                'allowed' => false,
                'message' => $prediction['purchase_guidance'] ?? 'No purchase suggested.',
                'pending_purchase_order' => null,
                'supplier_comparison' => null,
            ];
        }

        if (! is_numeric($prediction['suggested_quantity'] ?? null) || (float) $prediction['suggested_quantity'] <= 0) {
            return ['allowed' => false, 'message' => 'Refresh prediction before planning a restock.', 'pending_purchase_order' => null, 'supplier_comparison' => null];
        }

        $comparison = $this->supplierComparison->compare($ingredient);

        if (! $comparison['recommended_supplier']) {
            return ['allowed' => false, 'message' => 'Assign supplier before restock.', 'pending_purchase_order' => null, 'supplier_comparison' => $comparison];
        }

        return ['allowed' => true, 'message' => null, 'pending_purchase_order' => null, 'supplier_comparison' => $comparison];
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $predictionInput
     * @param  array<string, mixed>|null  $qwenExplanation
     * @return array{status: string, message: string, purchase_order: PurchaseOrder|null, agent_run: AgentRun|null}
     */
    public function plan(User $user, Ingredient $ingredient, array $prediction, array $predictionInput, ?array $qwenExplanation = null): array
    {
        $action = (string) ($prediction['recommended_action'] ?? 'monitor');
        $expiryDays = is_numeric($predictionInput['expiry_days_remaining'] ?? null)
            ? (int) $predictionInput['expiry_days_remaining']
            : null;

        if (($expiryDays === null || $expiryDays >= 0) && ! in_array($action, self::RESTOCK_ACTIONS, true)) {
            return [
                'status' => 'blocked_action',
                'message' => $this->blockedActionMessage($action),
                'purchase_order' => null,
                'agent_run' => null,
            ];
        }

        $agentRun = AgentRun::create([
            'user_id' => $user->id,
            'input_text' => 'Plan restock from stock prediction for '.$ingredient->name.'.',
            'input_type' => 'stock_prediction_restock',
            'status' => AgentRun::STATUS_COMPLETED,
            'qwen_mocked' => false,
        ]);

        $this->timeline($agentRun, $ingredient, $prediction, $predictionInput, $qwenExplanation);

        $inventoryTool = $this->logToolCall($agentRun, 'get_inventory', ['ingredient_id' => $ingredient->id], [
            'ingredient' => $this->ingredientFacts($ingredient, $predictionInput),
            'expiry' => $this->expiryFacts($ingredient, $predictionInput),
        ]);
        $this->timelineTool($agentRun, 'Inventory observed', 'Laravel loaded the current ingredient, stock level, and expiry facts.', $inventoryTool);

        $predictionTool = $this->logToolCall($agentRun, 'read_stock_prediction', ['ingredient_id' => $ingredient->id], [
            'prediction' => $this->predictionSnapshot($prediction, $predictionInput),
        ]);
        $this->timelineTool($agentRun, 'Prediction observed', 'Laravel loaded the existing FastAPI prediction without asking Qwen to recalculate it.', $predictionTool);

        $state = [
            'inventory_observed' => true,
            'prediction_observed' => true,
            'open_po_checked' => false,
            'open_purchase_order' => null,
            'supplier_comparison' => null,
            'purchase_order' => null,
            'previous_tool_result' => null,
            'iterations' => [],
        ];
        $qwenMocked = false;
        $stopReason = null;
        $resultStatus = 'max_iterations_reached';
        $resultMessage = 'The restock decision loop reached its safe iteration limit without creating a draft.';

        for ($iteration = 1; $iteration <= self::MAX_DECISION_ITERATIONS; $iteration++) {
            $allowedActions = $this->allowedActionsForState($state, $prediction, $predictionInput);
            $observation = $this->observationSummary($state, $predictionInput);
            $decision = $this->selectNextAction($ingredient, $prediction, $predictionInput, $state, $allowedActions);
            $qwenMocked = $qwenMocked || $decision['mocked'];

            $decisionTool = $this->logToolCall($agentRun, 'qwen_select_next_action', [
                'iteration' => $iteration,
                'observation' => $observation,
                'allowed_actions' => $allowedActions,
            ], [
                'selected_action' => $decision['next_action'],
                'reason_summary' => $decision['reason_summary'],
                'confidence' => $decision['confidence'],
                'requested_stop_reason' => $decision['stop_reason'],
                'decision_source' => $decision['source'],
                'rejected_action' => $decision['rejected_action'],
                'qwen_metadata' => $decision['metadata'],
            ], $decision['source'] === 'qwen' ? 'completed' : 'fallback');

            $this->reasoningActivity->decision(
                $agentRun,
                'Decision iteration '.$iteration.': '.str_replace('_', ' ', $decision['next_action']),
                $decision['reason_summary'],
                [
                    'observation' => $observation,
                    'selected_action' => $decision['next_action'],
                    'allowed_actions' => $allowedActions,
                    'decision_source' => $decision['source'],
                    'stop_reason' => $decision['stop_reason'],
                ],
                $decision['confidence']
            );

            $execution = $this->executeDecision(
                $decision,
                $user,
                $agentRun,
                $ingredient,
                $prediction,
                $predictionInput,
                $qwenExplanation,
                $state
            );
            $this->reasoningActivity->toolResult(
                $agentRun,
                'Tool result: '.str_replace('_', ' ', $decision['next_action']),
                $execution['result_summary'],
                $execution['tool_call']
            );

            $state['previous_tool_result'] = $execution['result_summary'];
            $state['iterations'][] = [
                'iteration' => $iteration,
                'observation' => $observation,
                'selected_action' => $decision['next_action'],
                'tool_result' => $execution['result_summary'],
                'reason_summary' => $decision['reason_summary'],
                'confidence' => $decision['confidence'],
                'decision_source' => $decision['source'],
                'stop_reason' => $execution['stop_reason'],
            ];

            if ($execution['stop_reason'] !== null) {
                $stopReason = $execution['stop_reason'];
                $resultStatus = $execution['status'];
                $resultMessage = $execution['message'];
                break;
            }
        }

        if ($stopReason === null) {
            $stopReason = 'max_iterations_reached';
            $this->logToolCall($agentRun, 'stop', ['iteration_limit' => self::MAX_DECISION_ITERATIONS], [
                'stop_reason' => $stopReason,
                'created_purchase_order' => false,
            ], 'blocked');
        }

        $purchaseOrder = $state['purchase_order'] ?? $state['open_purchase_order'];
        $comparison = $state['supplier_comparison'];
        $selectedSupplier = data_get($comparison, 'recommended_supplier');
        $runStatus = $stopReason === 'human_approval_required'
            ? AgentRun::STATUS_NEEDS_APPROVAL
            : ($stopReason === 'max_iterations_reached' ? AgentRun::STATUS_FAILED : AgentRun::STATUS_COMPLETED);
        $finalSummary = $this->finalSummaryFor($ingredient, $purchaseOrder, $stopReason, $resultMessage);

        $agentRun->update([
            'status' => $runStatus,
            'qwen_mocked' => $qwenMocked,
            'final_summary' => $finalSummary,
            'parsed_intent' => [
                'intent' => 'restock',
                'type' => 'stock_prediction_restock',
                'source' => 'stock_planner',
                'urgency' => ($prediction['risk_level'] ?? null) === 'high' ? 'high' : 'medium',
                'ingredients' => [['name' => $ingredient->name, 'quantity' => null, 'unit' => $ingredient->unit]],
                'matched_inventory' => [$this->ingredientFacts($ingredient, $predictionInput)],
                'matched_suppliers' => collect($comparison['suppliers'] ?? [])->map(fn (array $supplier): array => [
                    'id' => $supplier['id'] ?? null,
                    'name' => $supplier['name'] ?? 'Unknown supplier',
                ])->values()->all(),
                'restock_plan' => $purchaseOrder && $stopReason === 'human_approval_required' ? [[
                    'ingredient_name' => $ingredient->name,
                    'recommended_quantity' => $purchaseOrder->items()->where('ingredient_id', $ingredient->id)->value('quantity'),
                    'unit' => $ingredient->unit,
                ]] : [],
                'stock_prediction' => $this->predictionSnapshot($prediction, $predictionInput),
                'qwen_explanation' => ($qwenExplanation['available'] ?? false) ? $qwenExplanation : null,
                'selected_supplier' => $selectedSupplier,
                'supplier_comparison' => $comparison,
                'purchase_order_id' => $purchaseOrder?->id,
                'decision_loop' => [
                    'maximum_iterations' => self::MAX_DECISION_ITERATIONS,
                    'iterations' => $state['iterations'],
                    'stop_reason' => $stopReason,
                ],
            ],
        ]);

        $this->reasoningActivity->finalSummary($agentRun, 'Decision loop stopped: '.str_replace('_', ' ', $stopReason), $finalSummary);

        return [
            'status' => $resultStatus,
            'message' => $resultMessage,
            'purchase_order' => $purchaseOrder?->load(['supplier', 'items.ingredient', 'approvalRequest', 'agentRun']),
            'agent_run' => $agentRun->fresh(['toolCalls', 'reasoningSteps', 'purchaseOrders']),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $predictionInput
     * @return array<int, string>
     */
    private function allowedActionsForState(array $state, array $prediction, array $predictionInput): array
    {
        $expiryDays = is_numeric($predictionInput['expiry_days_remaining'] ?? null)
            ? (int) $predictionInput['expiry_days_remaining']
            : null;

        if ($expiryDays !== null && $expiryDays < 0) {
            return ['require_expiry_review', 'stop'];
        }

        if (! ($state['inventory_observed'] ?? false)) {
            return ['get_inventory', 'stop'];
        }

        if (! ($state['prediction_observed'] ?? false)) {
            return ['read_stock_prediction', 'stop'];
        }

        if (! in_array((string) ($prediction['recommended_action'] ?? 'monitor'), self::RESTOCK_ACTIONS, true)) {
            return ['stop'];
        }

        if (! ($state['open_po_checked'] ?? false)) {
            return ['check_open_purchase_order', 'stop'];
        }

        if (($state['open_purchase_order'] ?? null) || (float) ($predictionInput['pending_po_quantity'] ?? 0) > 0) {
            return ['stop'];
        }

        if (! is_array($state['supplier_comparison'] ?? null)) {
            return ['compare_suppliers', 'stop'];
        }

        if (! data_get($state, 'supplier_comparison.recommended_supplier.id')) {
            return ['stop'];
        }

        if (! ($state['purchase_order'] ?? null)) {
            return ['create_purchase_order_draft', 'stop'];
        }

        return ['request_human_approval', 'stop'];
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $predictionInput
     * @param  array<string, mixed>  $state
     * @param  array<int, string>  $allowedActions
     * @return array{next_action: string, reason_summary: string, confidence: float|null, stop_reason: string|null, source: string, mocked: bool, rejected_action: string|null, metadata: array<string, mixed>}
     */
    private function selectNextAction(Ingredient $ingredient, array $prediction, array $predictionInput, array $state, array $allowedActions): array
    {
        $facts = [
            'mission_type' => 'stock_prediction_restock',
            'ingredient_facts' => $this->ingredientFacts($ingredient, $predictionInput),
            'fastapi_prediction_summary' => $this->predictionSnapshot($prediction, $predictionInput),
            'expiry_status' => $this->expiryFacts($ingredient, $predictionInput),
            'pending_po_summary' => $this->pendingPoSummary($state, $predictionInput),
            'available_supplier_summary' => $this->availableSupplierSummary($ingredient, $state),
            'previous_tool_result' => $state['previous_tool_result'],
            'allowed_actions' => $allowedActions,
        ];
        $response = $this->qwenClient->generateJson($this->decisionSystemPrompt(), (string) json_encode($facts, JSON_UNESCAPED_SLASHES), [
            'max_tokens' => (int) config('qwen.max_tokens.restock_decision', 220),
            'temperature' => 0.1,
        ]);
        $json = is_array($response['json'] ?? null) ? $response['json'] : [];
        $requestedAction = is_string($json['next_action'] ?? null) ? $json['next_action'] : '';
        $validAction = in_array($requestedAction, self::ALLOWED_ACTIONS, true)
            && in_array($requestedAction, $allowedActions, true);
        $validShape = is_string($json['reason_summary'] ?? null)
            && is_array($json['required_inputs'] ?? null)
            && (is_numeric($json['confidence'] ?? null) || ($json['confidence'] ?? null) === null);
        $useQwenDecision = ($response['error'] ?? null) === null && $validAction && $validShape;
        $fallbackAction = collect($allowedActions)->first(fn (string $allowed): bool => $allowed !== 'stop') ?? 'stop';
        $nextAction = $useQwenDecision ? $requestedAction : $fallbackAction;
        $reasonSummary = $useQwenDecision
            ? Str::limit(Str::squish(strip_tags((string) $json['reason_summary'])), 500, '')
            : 'Qwen output was unavailable or invalid, so Laravel selected the next safe action from the bounded workflow.';
        $confidence = $useQwenDecision && is_numeric($json['confidence'] ?? null)
            ? max(0.0, min(1.0, (float) $json['confidence']))
            : null;
        $requestedStopReason = is_string($json['stop_reason'] ?? null)
            && in_array($json['stop_reason'], self::STOP_REASONS, true)
            ? $json['stop_reason']
            : null;

        return [
            'next_action' => $nextAction,
            'reason_summary' => $reasonSummary,
            'confidence' => $confidence,
            'stop_reason' => $requestedStopReason,
            'source' => $useQwenDecision ? 'qwen' : 'deterministic_fallback',
            'mocked' => (bool) ($response['mocked'] ?? false),
            'rejected_action' => $requestedAction !== '' && ! $validAction ? Str::limit($requestedAction, 100, '') : null,
            'metadata' => is_array($response['metadata'] ?? null) ? $response['metadata'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $predictionInput
     * @param  array<string, mixed>|null  $qwenExplanation
     * @param  array<string, mixed>  $state
     * @return array{tool_call: AgentToolCall, result_summary: string, stop_reason: string|null, status: string, message: string}
     */
    private function executeDecision(array $decision, User $user, AgentRun $agentRun, Ingredient $ingredient, array $prediction, array $predictionInput, ?array $qwenExplanation, array &$state): array
    {
        $action = $decision['next_action'];

        if (! in_array($action, self::ALLOWED_ACTIONS, true)) {
            $toolCall = $this->logToolCall($agentRun, 'stop', ['rejected_action' => $action], ['stop_reason' => 'blocked'], 'blocked');

            return $this->executionResult($toolCall, 'Laravel rejected an action outside the restock allowlist.', 'blocked', 'blocked', 'The selected action was blocked by Laravel.');
        }

        if ($action === 'get_inventory') {
            $state['inventory_observed'] = true;
            $toolCall = $this->logToolCall($agentRun, $action, ['ingredient_id' => $ingredient->id], [
                'ingredient' => $this->ingredientFacts($ingredient, $predictionInput),
                'expiry' => $this->expiryFacts($ingredient, $predictionInput),
            ]);

            return $this->executionResult($toolCall, 'Current inventory and expiry facts were loaded.', null, 'observed', 'Inventory observed.');
        }

        if ($action === 'read_stock_prediction') {
            $state['prediction_observed'] = true;
            $toolCall = $this->logToolCall($agentRun, $action, ['ingredient_id' => $ingredient->id], [
                'prediction' => $this->predictionSnapshot($prediction, $predictionInput),
            ]);

            return $this->executionResult($toolCall, 'The cached FastAPI prediction was loaded without recalculation.', null, 'observed', 'Prediction observed.');
        }

        if ($action === 'require_expiry_review') {
            $toolCall = $this->logToolCall($agentRun, $action, ['ingredient_id' => $ingredient->id], [
                'created_purchase_order' => false,
                'expiry' => $this->expiryFacts($ingredient, $predictionInput),
                'stop_reason' => 'expiry_review_required',
            ], 'blocked');

            return $this->executionResult(
                $toolCall,
                'The item is expired, so Laravel blocked draft creation and requires expiry review.',
                'expiry_review_required',
                'blocked_expired_stock',
                'This item is past expiry. Review or remove expired stock before restocking.'
            );
        }

        if ($action === 'check_open_purchase_order') {
            $existingPurchaseOrder = $this->pendingPurchaseOrderFor($ingredient);
            $pendingSignal = (float) ($predictionInput['pending_po_quantity'] ?? 0) > 0;
            $state['open_po_checked'] = true;
            $state['open_purchase_order'] = $existingPurchaseOrder;
            $toolCall = $this->logToolCall($agentRun, $action, ['ingredient_id' => $ingredient->id], [
                'found' => (bool) $existingPurchaseOrder || $pendingSignal,
                'purchase_order_id' => $existingPurchaseOrder?->id,
                'po_number' => $existingPurchaseOrder?->po_number,
                'status' => $existingPurchaseOrder?->status,
                'pending_po_quantity' => (float) ($predictionInput['pending_po_quantity'] ?? 0),
            ]);

            if ($existingPurchaseOrder || $pendingSignal) {
                return $this->executionResult($toolCall, 'An open purchase order or pending quantity already covers this item.', 'duplicate_po_found', 'duplicate_pending_po', 'A purchase order is already pending for this item.');
            }

            return $this->executionResult($toolCall, 'No open purchase order was found for this ingredient.', null, 'checked', 'No duplicate purchase order found.');
        }

        if ($action === 'compare_suppliers') {
            $comparison = $this->supplierComparison->compare($ingredient);
            $state['supplier_comparison'] = $comparison;
            $toolCall = $this->logToolCall($agentRun, $action, ['ingredient_id' => $ingredient->id], $comparison);
            $supplierName = data_get($comparison, 'recommended_supplier.name');

            if (! $supplierName) {
                $this->logToolCall($agentRun, 'create_purchase_order_draft', ['ingredient_id' => $ingredient->id], [
                    'created' => false,
                    'reason' => 'supplier_missing',
                ], 'skipped');

                return $this->executionResult($toolCall, 'No eligible supplier was available, so no purchase order draft was created.', 'blocked', 'supplier_missing', 'No supplier found. Please assign a supplier before creating a purchase order.');
            }

            return $this->executionResult($toolCall, 'Supplier '.$supplierName.' ranked first using available business evidence.', null, 'compared', 'Supplier comparison completed.');
        }

        if ($action === 'create_purchase_order_draft') {
            $existingPurchaseOrder = $this->pendingPurchaseOrderFor($ingredient);
            $expiryDays = is_numeric($predictionInput['expiry_days_remaining'] ?? null) ? (int) $predictionInput['expiry_days_remaining'] : null;
            $supplierId = data_get($state, 'supplier_comparison.recommended_supplier.id');
            $supplier = $supplierId ? Supplier::find($supplierId) : null;
            $quantity = $this->suggestedQuantity($ingredient, $prediction, $predictionInput);

            if ($existingPurchaseOrder || (float) ($predictionInput['pending_po_quantity'] ?? 0) > 0) {
                $state['open_purchase_order'] = $existingPurchaseOrder;
                $toolCall = $this->logToolCall($agentRun, $action, ['ingredient_id' => $ingredient->id], ['created' => false, 'reason' => 'duplicate_po_found'], 'blocked');

                return $this->executionResult($toolCall, 'Laravel rechecked open orders and blocked a duplicate draft.', 'duplicate_po_found', 'duplicate_pending_po', 'A purchase order is already pending for this item.');
            }

            if (($expiryDays !== null && $expiryDays < 0) || ! in_array((string) ($prediction['recommended_action'] ?? ''), self::RESTOCK_ACTIONS, true) || ! $supplier || $quantity <= 0) {
                $toolCall = $this->logToolCall($agentRun, $action, ['ingredient_id' => $ingredient->id], ['created' => false, 'reason' => 'safety_validation_failed'], 'blocked');

                return $this->executionResult($toolCall, 'Laravel rejected draft creation because a required safety condition was not met.', 'blocked', 'blocked', 'Purchase order draft creation was blocked by safety validation.');
            }

            $purchaseOrder = DB::transaction(fn (): PurchaseOrder => $this->createPurchaseOrder(
                $user,
                $agentRun,
                $ingredient,
                $supplier,
                $quantity,
                $prediction,
                $predictionInput,
                $qwenExplanation
            ));
            $state['purchase_order'] = $purchaseOrder;
            $toolCall = $this->logToolCall($agentRun, $action, [
                'ingredient_id' => $ingredient->id,
                'supplier_id' => $supplier->id,
                'suggested_quantity' => $quantity,
            ], [
                'created' => true,
                'purchase_order_id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'status' => $purchaseOrder->status,
            ]);
            $approvalTool = $this->logToolCall($agentRun, 'create_approval_request', ['purchase_order_id' => $purchaseOrder->id], [
                'approval_request_id' => $purchaseOrder->approvalRequest?->id,
                'status' => $purchaseOrder->approvalRequest?->status,
            ]);
            $this->timelineTool($agentRun, 'Approval request created', 'The PO remains pending until an admin approves or rejects it.', $approvalTool);
            $this->reasoningActivity->humanCheckpoint(
                $agentRun,
                'Human approval required',
                'The Qwen-selected draft action stopped at the existing admin approval checkpoint.',
                $purchaseOrder->approvalRequest
            );

            return $this->executionResult($toolCall, 'Draft '.$purchaseOrder->po_number.' was created and stopped at pending admin approval.', 'human_approval_required', 'created', 'Restock plan created. Purchase order draft is waiting for admin approval.');
        }

        if ($action === 'request_human_approval') {
            $purchaseOrder = $state['purchase_order'] ?? null;
            $toolCall = $this->logToolCall($agentRun, $action, ['purchase_order_id' => $purchaseOrder?->id], [
                'approval_request_id' => $purchaseOrder?->approvalRequest?->id,
                'status' => $purchaseOrder?->approvalRequest?->status,
            ], $purchaseOrder ? 'completed' : 'blocked');

            if (! $purchaseOrder) {
                return $this->executionResult($toolCall, 'No draft exists to submit for human approval.', 'blocked', 'blocked', 'Human approval could not be requested because no draft exists.');
            }

            $this->reasoningActivity->humanCheckpoint($agentRun, 'Human approval required', 'The mission stopped for explicit admin approval.', $purchaseOrder->approvalRequest);

            return $this->executionResult($toolCall, 'The draft is waiting for explicit admin approval.', 'human_approval_required', 'created', 'Restock plan created. Purchase order draft is waiting for admin approval.');
        }

        $stopReason = in_array($decision['stop_reason'], self::STOP_REASONS, true) ? $decision['stop_reason'] : 'completed';
        $toolCall = $this->logToolCall($agentRun, 'stop', ['requested_reason' => $decision['stop_reason']], [
            'stop_reason' => $stopReason,
            'created_purchase_order' => false,
        ]);

        return $this->executionResult($toolCall, 'The bounded decision loop stopped without executing a critical action.', $stopReason, $stopReason === 'blocked' ? 'blocked' : 'completed', 'The restock mission stopped safely without creating a purchase order.');
    }

    /**
     * @return array{tool_call: AgentToolCall, result_summary: string, stop_reason: string|null, status: string, message: string}
     */
    private function executionResult(AgentToolCall $toolCall, string $summary, ?string $stopReason, string $status, string $message): array
    {
        return [
            'tool_call' => $toolCall,
            'result_summary' => $summary,
            'stop_reason' => $stopReason,
            'status' => $status,
            'message' => $message,
        ];
    }

    /** @param array<string, mixed> $predictionInput */
    private function ingredientFacts(Ingredient $ingredient, array $predictionInput): array
    {
        return [
            'id' => $ingredient->id,
            'name' => $ingredient->name,
            'current_quantity' => (float) ($predictionInput['current_quantity'] ?? $ingredient->quantity),
            'minimum_stock' => (float) ($predictionInput['minimum_stock'] ?? $ingredient->minimum_stock),
            'unit' => $ingredient->unit,
            'low_stock' => (float) ($predictionInput['current_quantity'] ?? $ingredient->quantity) <= (float) ($predictionInput['minimum_stock'] ?? $ingredient->minimum_stock),
        ];
    }

    /** @param array<string, mixed> $predictionInput */
    private function expiryFacts(Ingredient $ingredient, array $predictionInput): array
    {
        $days = is_numeric($predictionInput['expiry_days_remaining'] ?? null) ? (int) $predictionInput['expiry_days_remaining'] : null;

        return [
            'expiry_date' => $ingredient->expiry_date?->toDateString(),
            'days_remaining' => $days,
            'status' => $days === null ? 'not_recorded' : ($days < 0 ? 'expired' : ($days <= 7 ? 'near_expiry' : 'usable')),
        ];
    }

    /** @param array<string, mixed> $state @param array<string, mixed> $predictionInput */
    private function pendingPoSummary(array $state, array $predictionInput): array
    {
        $purchaseOrder = $state['open_purchase_order'] ?? null;

        return [
            'checked' => (bool) ($state['open_po_checked'] ?? false),
            'found' => (bool) $purchaseOrder || (float) ($predictionInput['pending_po_quantity'] ?? 0) > 0,
            'purchase_order_id' => $purchaseOrder?->id,
            'po_number' => $purchaseOrder?->po_number,
            'status' => $purchaseOrder?->status,
            'pending_quantity' => (float) ($predictionInput['pending_po_quantity'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $state */
    private function availableSupplierSummary(Ingredient $ingredient, array $state): array
    {
        if (is_array($state['supplier_comparison'] ?? null)) {
            return [
                'compared' => true,
                'candidate_count' => count($state['supplier_comparison']['suppliers'] ?? []),
                'recommended_supplier' => collect(data_get($state, 'supplier_comparison.recommended_supplier', []))->only(['id', 'name', 'contact_available'])->all(),
            ];
        }

        return [
            'compared' => false,
            'assigned_supplier' => $ingredient->supplier ? [
                'id' => $ingredient->supplier->id,
                'name' => $ingredient->supplier->name,
                'contact_available' => filled($ingredient->supplier->email) || filled($ingredient->supplier->phone),
            ] : null,
        ];
    }

    /** @param array<string, mixed> $state @param array<string, mixed> $predictionInput */
    private function observationSummary(array $state, array $predictionInput): string
    {
        if (filled($state['previous_tool_result'] ?? null)) {
            return Str::limit((string) $state['previous_tool_result'], 300, '');
        }

        $expiryDays = $predictionInput['expiry_days_remaining'] ?? null;

        return is_numeric($expiryDays) && (int) $expiryDays < 0
            ? 'Inventory and FastAPI facts show that the ingredient is past expiry.'
            : 'Inventory and FastAPI prediction facts are available; an open purchase order check has not yet been completed.';
    }

    private function decisionSystemPrompt(): string
    {
        return <<<'PROMPT'
You are the bounded TingHao restock decision selector. Select exactly one next action from allowed_actions using only the supplied facts. Return JSON only with keys: next_action, reason_summary, required_inputs, confidence, stop_reason. reason_summary must be one concise business sentence, not chain-of-thought. required_inputs must be an object. confidence must be between 0 and 1. Never approve a purchase order, send email, mutate inventory, invent facts, reveal secrets, or return markdown. Critical actions must stop for human approval.
PROMPT;
    }

    private function finalSummaryFor(Ingredient $ingredient, ?PurchaseOrder $purchaseOrder, string $stopReason, string $message): string
    {
        return match ($stopReason) {
            'human_approval_required' => 'Qwen selected a bounded restock path for '.$ingredient->name.'. Draft '.$purchaseOrder?->po_number.' is waiting for admin approval.',
            'duplicate_po_found' => 'The restock mission stopped because an open purchase order already covers '.$ingredient->name.'.',
            'expiry_review_required' => 'The restock mission stopped because '.$ingredient->name.' requires expired-stock review before any purchase.',
            'max_iterations_reached' => 'The restock mission reached the four-iteration safety limit without executing a critical action.',
            default => $message,
        };
    }

    public function pendingPurchaseOrderFor(Ingredient $ingredient): ?PurchaseOrder
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'agentRun'])
            ->whereIn('status', [
                PurchaseOrder::STATUS_DRAFT,
                PurchaseOrder::STATUS_PENDING_APPROVAL,
                PurchaseOrder::STATUS_APPROVED,
                PurchaseOrder::STATUS_SENT,
                PurchaseOrder::STATUS_CONFIRMED,
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ])
            ->whereHas('items', fn ($query) => $query->where('ingredient_id', $ingredient->id))
            ->latest()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $prediction
     */
    private function suggestedQuantity(Ingredient $ingredient, array $prediction, array $predictionInput): float
    {
        $quantity = (float) ($prediction['suggested_quantity'] ?? 0);
        $pendingQuantity = max(0, (float) ($predictionInput['pending_po_quantity'] ?? 0));

        if ($quantity > 0) {
            return round(max(0, $quantity - $pendingQuantity), 2);
        }

        $minimumStock = (float) $ingredient->minimum_stock;
        $currentQuantity = (float) $ingredient->quantity;

        return round(max(($minimumStock * 2) - $currentQuantity - $pendingQuantity, $minimumStock, 1.0), 2);
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $predictionInput
     * @param  array<string, mixed>|null  $qwenExplanation
     * @return array{status: string, message: string, purchase_order: null, agent_run: AgentRun}
     */
    private function createSkippedRun(User $user, Ingredient $ingredient, array $prediction, array $predictionInput, ?array $qwenExplanation, string $message, string $reason): array
    {
        $agentRun = DB::transaction(function () use ($user, $ingredient, $prediction, $predictionInput, $qwenExplanation, $message, $reason): AgentRun {
            $agentRun = AgentRun::create([
                'user_id' => $user->id,
                'input_text' => 'Plan restock from stock prediction for '.$ingredient->name.'.',
                'input_type' => 'stock_prediction_restock',
                'status' => AgentRun::STATUS_COMPLETED,
                'qwen_mocked' => (bool) data_get($qwenExplanation, 'qwen_metadata.mock_mode', false),
            ]);

            $this->timeline($agentRun, $ingredient, $prediction, $predictionInput, $qwenExplanation);
            $this->logToolCall($agentRun, 'create_purchase_order_draft', [
                'ingredient_id' => $ingredient->id,
            ], [
                'created' => false,
                'reason' => $reason,
                'message' => $message,
            ], 'skipped');

            $agentRun->update([
                'final_summary' => $message,
                'parsed_intent' => [
                    'type' => 'stock_prediction_restock',
                    'source' => 'stock_planner',
                    'stock_prediction' => $this->predictionSnapshot($prediction, $predictionInput),
                    'qwen_explanation' => ($qwenExplanation['available'] ?? false) ? $qwenExplanation : null,
                    'purchase_order_id' => null,
                    'skip_reason' => $reason,
                ],
            ]);

            return $agentRun->fresh(['toolCalls', 'reasoningSteps']);
        });

        return [
            'status' => $reason,
            'message' => $message,
            'purchase_order' => null,
            'agent_run' => $agentRun,
        ];
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $predictionInput
     * @param  array<string, mixed>|null  $qwenExplanation
     */
    private function createPurchaseOrder(User $user, AgentRun $agentRun, Ingredient $ingredient, Supplier $supplier, float $quantity, array $prediction, array $predictionInput, ?array $qwenExplanation): PurchaseOrder
    {
        $comparison = $this->supplierComparison->compare($ingredient);
        $recommendedPrice = data_get($comparison, 'recommended_supplier.latest_item_price');
        $unitPrice = is_numeric($recommendedPrice) && (float) $recommendedPrice > 0
            ? (float) $recommendedPrice
            : (float) ($ingredient->cost_price ?? 0);
        $reasoning = $this->agentReasoning($ingredient, $supplier, $quantity, $prediction, $predictionInput, $qwenExplanation);

        $purchaseOrder = PurchaseOrder::create([
            'po_number' => $this->nextPoNumber(),
            'supplier_id' => $supplier->id,
            'agent_run_id' => $agentRun->id,
            'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
            'order_date' => now()->toDateString(),
            'subtotal' => round($quantity * $unitPrice, 2),
            'notes' => 'Created from FastAPI Stock Prediction. Admin approval required.',
            'agent_reasoning' => $reasoning,
            'email_to' => $supplier->email,
            'created_by' => $user->id,
            'requested_by' => $user->id,
        ]);

        $purchaseOrder->items()->create([
            'ingredient_id' => $ingredient->id,
            'description' => $ingredient->name,
            'quantity' => $quantity,
            'unit' => $ingredient->unit,
            'unit_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
        ]);

        $purchaseOrder->approvalRequest()->create([
            'agent_run_id' => $agentRun->id,
            'type' => ApprovalRequest::TYPE_PURCHASE_ORDER,
            'status' => ApprovalRequest::STATUS_PENDING,
            'requested_by' => $user->id,
        ]);

        return $purchaseOrder->load(['supplier', 'items.ingredient', 'approvalRequest']);
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $predictionInput
     * @return array<string, mixed>
     */
    private function predictionSnapshot(array $prediction, array $predictionInput): array
    {
        return [
            'source' => 'FastAPI Stock Prediction',
            'ingredient' => $prediction['ingredient'] ?? $predictionInput['ingredient'] ?? null,
            'current_quantity' => $predictionInput['current_quantity'] ?? data_get($prediction, 'calculation_summary.current_quantity'),
            'minimum_stock' => $predictionInput['minimum_stock'] ?? data_get($prediction, 'calculation_summary.minimum_stock'),
            'pending_po_quantity' => $predictionInput['pending_po_quantity'] ?? data_get($prediction, 'calculation_summary.pending_po_quantity'),
            'recommended_action' => $prediction['recommended_action'] ?? null,
            'action_label' => $prediction['action_label'] ?? null,
            'estimated_days_until_stockout' => $prediction['estimated_days_until_stockout'] ?? null,
            'suggested_quantity' => $prediction['suggested_quantity'] ?? null,
            'risk_level' => $prediction['risk_level'] ?? null,
            'risk_label' => $prediction['risk_label'] ?? null,
            'confidence' => $prediction['confidence'] ?? null,
            'confidence_percent' => $prediction['confidence_percent'] ?? null,
            'reason_codes' => collect($prediction['reason_codes'] ?? [])->values()->all(),
            'reason_labels' => collect($prediction['reason_labels'] ?? [])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $predictionInput
     * @param  array<string, mixed>|null  $qwenExplanation
     */
    private function timeline(AgentRun $agentRun, Ingredient $ingredient, array $prediction, array $predictionInput, ?array $qwenExplanation): void
    {
        $agentRun->reasoningSteps()->create([
            'step_order' => 1,
            'step_type' => 'observe',
            'title' => 'Observed stock prediction',
            'summary' => 'FastAPI prediction for '.$ingredient->name.' returned '.str_replace('_', ' ', (string) ($prediction['recommended_action'] ?? 'monitor')).'.',
            'evidence' => $this->predictionSnapshot($prediction, $predictionInput),
            'confidence' => is_numeric($prediction['confidence'] ?? null) ? (float) $prediction['confidence'] : null,
            'risk_level' => $prediction['risk_level'] ?? null,
            'requires_human_approval' => false,
        ]);

        if ($qwenExplanation['available'] ?? false) {
            $agentRun->reasoningSteps()->create([
                'step_order' => 2,
                'step_type' => 'understand',
                'title' => 'Cached Qwen explanation available',
                'summary' => (string) ($qwenExplanation['summary'] ?? 'A cached business explanation is available for admin review.'),
                'evidence' => ['source' => 'Qwen Explanation'],
                'requires_human_approval' => false,
            ]);
        }
    }

    private function timelineTool(AgentRun $agentRun, string $title, string $summary, AgentToolCall $toolCall): void
    {
        $agentRun->reasoningSteps()->create([
            'step_order' => ((int) $agentRun->reasoningSteps()->max('step_order')) + 1,
            'step_type' => 'tool_result',
            'title' => $title,
            'summary' => $summary,
            'related_tool_call_id' => $toolCall->id,
            'requires_human_approval' => $title === 'Waiting for admin approval',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $input
     * @param  array<string, mixed>|null  $output
     */
    private function logToolCall(AgentRun $agentRun, string $toolName, ?array $input, ?array $output, string $status = 'completed'): AgentToolCall
    {
        return $agentRun->toolCalls()->create([
            'tool_name' => $toolName,
            'input_payload' => $input,
            'output_payload' => $output,
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $predictionInput
     * @param  array<string, mixed>|null  $qwenExplanation
     */
    private function agentReasoning(Ingredient $ingredient, Supplier $supplier, float $quantity, array $prediction, array $predictionInput, ?array $qwenExplanation): string
    {
        $lines = [
            'Prediction Source: FastAPI Stock Prediction',
            'Workflow: Admin approval required',
            'Ingredient: '.$ingredient->name,
            'Current stock: '.number_format((float) ($predictionInput['current_quantity'] ?? $ingredient->quantity), 2).' '.$ingredient->unit,
            'Minimum stock: '.number_format((float) ($predictionInput['minimum_stock'] ?? $ingredient->minimum_stock), 2).' '.$ingredient->unit,
            'Predicted action: '.str_replace('_', ' ', (string) ($prediction['recommended_action'] ?? 'unknown')),
            'Estimated stockout: '.(($prediction['estimated_days_until_stockout'] ?? null) !== null ? $prediction['estimated_days_until_stockout'].' day(s)' : 'unknown'),
            'Suggested quantity: '.number_format($quantity, 2).' '.$ingredient->unit,
            'Risk level: '.(string) ($prediction['risk_label'] ?? $prediction['risk_level'] ?? 'unknown'),
            'Supplier: '.$supplier->name,
        ];

        if ($prediction['reason_codes'] ?? []) {
            $lines[] = 'Reason codes: '.implode(', ', $prediction['reason_codes']);
        }

        if ($qwenExplanation['available'] ?? false) {
            $lines[] = 'AI Explanation Source: Qwen Explanation';
            $lines[] = 'Qwen summary: '.(string) ($qwenExplanation['summary'] ?? '');
        }

        return implode("\n", array_filter($lines));
    }

    private function blockedActionMessage(string $action): string
    {
        return match ($action) {
            'do_not_buy' => 'Buying is not recommended for this item right now.',
            'buy_less' => 'Review usage before purchasing.',
            'use_before_expiry' => 'Use this stock before expiry instead of creating a purchase order.',
            default => 'Continue monitoring stock movement.',
        };
    }

    private function nextPoNumber(): string
    {
        $year = now()->format('Y');
        $count = PurchaseOrder::where('po_number', 'like', "PO-{$year}-%")->count() + 1;

        return sprintf('PO-%s-%04d', $year, $count);
    }
}
