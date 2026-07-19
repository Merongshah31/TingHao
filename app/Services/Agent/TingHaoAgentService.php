<?php

namespace App\Services\Agent;

use App\Models\AgentRun;
use App\Models\AgentToolCall;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TingHaoAgentService
{
    public function __construct(
        private readonly ProcurementMessageParserService $parser,
        private readonly InventoryLookupToolService $inventoryLookup,
        private readonly SupplierLookupToolService $supplierLookup,
        private readonly RestockPlanningService $restockPlanning,
        private readonly SupplierRankingService $supplierRanking,
        private readonly PurchaseOrderDraftService $purchaseOrderDraft,
        private readonly ReasoningActivityService $reasoningActivity,
        private readonly HumanApprovalGuardService $approvalGuard,
    ) {
    }

    public function run(User $user, string $inputText): AgentRun
    {
        return DB::transaction(function () use ($user, $inputText): AgentRun {
            $agentRun = AgentRun::create([
                'user_id' => $user->id,
                'input_text' => $inputText,
                'input_type' => 'procurement_message',
                'status' => AgentRun::STATUS_COMPLETED,
            ]);
            $this->reasoningActivity->observe($agentRun, 'Staff procurement message received', 'TingHao Agent received a staff message for procurement analysis.', [
                'input_type' => 'procurement_message',
                'message_preview' => str($inputText)->limit(140)->toString(),
            ]);

            $parseResult = $this->parser->parse($inputText);
            $parseToolCall = $this->logToolCall($agentRun, 'parse_procurement_message', ['input_text' => $inputText], $parseResult);
            $this->reasoningActivity->understand($agentRun, 'Message intent summarized', $this->understandingSummary($parseResult['parsed']), [
                'intent' => $parseResult['parsed']['intent'] ?? null,
                'ingredients' => $parseResult['parsed']['ingredients'] ?? [],
                'supplier_name' => $parseResult['parsed']['supplier_name'] ?? null,
                'confidence' => $parseResult['parsed']['confidence'] ?? null,
                'decision_factors' => $parseResult['parsed']['decision_factors'] ?? [],
            ]);
            $this->reasoningActivity->toolResult($agentRun, 'Procurement parser result stored', 'The parser output was stored as structured JSON without raw chain-of-thought.', $parseToolCall);

            $matchedInventory = $this->inventoryLookup->lookup($parseResult['parsed']['ingredients'] ?? []);
            $inventoryToolCall = $this->logToolCall($agentRun, 'lookup_inventory', ['ingredients' => $parseResult['parsed']['ingredients'] ?? []], [
                'matches' => $matchedInventory,
            ]);
            $this->reasoningActivity->toolAction($agentRun, 'Inventory lookup', 'Checking real inventory records for parsed ingredient hints.', $inventoryToolCall);
            $this->reasoningActivity->toolResult($agentRun, 'Inventory lookup result', $this->inventorySummary($matchedInventory), $inventoryToolCall);

            $matchedSuppliers = $this->supplierLookup->lookup($parseResult['parsed']['supplier_name'] ?? null, $matchedInventory);
            $restockPlan = $this->restockPlanning->plan($parseResult['parsed'], $matchedInventory);
            $planToolCall = $this->logToolCall($agentRun, 'plan_restock_quantity', [
                'parsed_intent' => $parseResult['parsed'],
                'matched_inventory' => $matchedInventory,
            ], [
                'restock_plan' => $restockPlan,
            ]);
            $this->reasoningActivity->plan($agentRun, 'Restock quantity planned', $this->restockPlanSummary($restockPlan), [
                'planned_items' => count($restockPlan),
            ]);
            $this->reasoningActivity->toolResult($agentRun, 'Restock planning result', 'Recommended quantities were calculated from parsed quantities or current stock thresholds.', $planToolCall);

            $supplierRanking = $this->supplierRanking->rank($parseResult['parsed'], $matchedInventory);
            $supplierToolCall = $this->logToolCall($agentRun, 'rank_suppliers', [
                'supplier_name' => $parseResult['parsed']['supplier_name'] ?? null,
                'matched_inventory_ids' => collect($matchedInventory)->pluck('id')->all(),
            ], [
                'phase_1_matches' => $matchedSuppliers,
                ...$supplierRanking,
            ]);
            $this->reasoningActivity->decision($agentRun, 'Supplier candidate selected', $this->supplierDecisionSummary($supplierRanking), [
                'recommended_supplier' => $supplierRanking['recommended_supplier']['name'] ?? null,
                'ranked_supplier_count' => count($supplierRanking['ranked_suppliers'] ?? []),
            ], $this->confidenceFromParsed($parseResult['parsed']));

            $reasoning = $this->reasoning($parseResult['parsed'], $restockPlan, $supplierRanking);
            $this->approvalGuard->requiresAdminApproval(HumanApprovalGuardService::ACTION_PURCHASE_ORDER_APPROVAL);
            $purchaseOrder = $this->purchaseOrderDraft->create(
                $user,
                $agentRun,
                $restockPlan,
                $supplierRanking['recommended_supplier'],
                $reasoning
            );

            $draftToolCall = $this->logToolCall($agentRun, 'create_purchase_order_draft', [
                'restock_plan' => $restockPlan,
                'recommended_supplier' => $supplierRanking['recommended_supplier'],
            ], [
                'created' => $purchaseOrder !== null,
                'purchase_order' => $purchaseOrder ? $this->purchaseOrderPayload($purchaseOrder) : null,
            ], $purchaseOrder ? 'completed' : 'skipped');
            $this->reasoningActivity->decision($agentRun, 'Purchase order draft decision', $purchaseOrder
                ? "Draft {$purchaseOrder->po_number} was created for admin review."
                : 'No purchase order draft was created because no actionable restock plan and supplier combination was available.', [
                    'purchase_order_id' => $purchaseOrder?->id,
                    'draft_status' => $purchaseOrder?->status,
                ], $this->confidenceFromParsed($parseResult['parsed']));
            $this->reasoningActivity->toolResult($agentRun, 'Purchase order draft result', $purchaseOrder
                ? 'The agent created a draft only; it did not approve or send the order.'
                : 'Draft creation was skipped.', $draftToolCall);

            $approvalToolCall = $this->logToolCall($agentRun, 'create_approval_request', [
                'purchase_order_id' => $purchaseOrder?->id,
            ], [
                'created' => (bool) $purchaseOrder?->approvalRequest,
                'approval_request' => $purchaseOrder?->approvalRequest?->only(['id', 'status', 'type', 'requested_by']),
            ], $purchaseOrder?->approvalRequest ? 'completed' : 'skipped');
            if ($purchaseOrder?->approvalRequest) {
                $this->reasoningActivity->humanCheckpoint($agentRun, 'Admin approval required', 'The purchase order remains pending until an admin approves or rejects it.', $purchaseOrder->approvalRequest);
            } else {
                $this->reasoningActivity->riskCheck($agentRun, 'No approval checkpoint created', 'No purchase order was created, so there is no approval checkpoint for this run.', 'low');
            }

            $summary = $this->summary($parseResult['parsed'], $matchedInventory, $matchedSuppliers, $restockPlan, $purchaseOrder);
            $this->reasoningActivity->finalSummary($agentRun, 'Safe decision summary', $summary);

            $agentRun->update([
                'status' => $purchaseOrder ? AgentRun::STATUS_NEEDS_APPROVAL : AgentRun::STATUS_COMPLETED,
                'parsed_intent' => [
                    ...$parseResult['parsed'],
                    'matched_inventory' => $matchedInventory,
                    'matched_suppliers' => $matchedSuppliers,
                    'restock_plan' => $restockPlan,
                    'supplier_ranking' => $supplierRanking,
                    'purchase_order' => $purchaseOrder ? $this->purchaseOrderPayload($purchaseOrder) : null,
                ],
                'final_summary' => $summary,
                'qwen_mocked' => $parseResult['mocked'],
            ]);

            return $agentRun->load(['user:id,name,role', 'toolCalls', 'reasoningSteps', 'purchaseOrders.approvalRequest']);
        });
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
     * @param  array<string, mixed>  $parsed
     * @param  array<int, array<string, mixed>>  $matchedInventory
     * @param  array<int, array<string, mixed>>  $matchedSuppliers
     */
    private function summary(array $parsed, array $matchedInventory, array $matchedSuppliers, array $restockPlan, ?PurchaseOrder $purchaseOrder): string
    {
        $intent = str_replace('_', ' ', (string) ($parsed['intent'] ?? 'general_stock_note'));
        $urgency = (string) ($parsed['urgency'] ?? 'low');
        $ingredientCount = count($parsed['ingredients'] ?? []);
        $inventoryCount = count($matchedInventory);
        $supplierCount = count($matchedSuppliers);
        $planCount = count($restockPlan);

        $summary = "Detected {$intent} with {$urgency} urgency. Parsed {$ingredientCount} ingredient hint(s), matched {$inventoryCount} inventory record(s), found {$supplierCount} supplier option(s), and planned {$planCount} restock item(s).";

        if ($purchaseOrder) {
            $summary .= " Drafted {$purchaseOrder->po_number} for admin approval.";
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<int, array<string, mixed>>  $restockPlan
     * @param  array<string, mixed>  $supplierRanking
     */
    private function reasoning(array $parsed, array $restockPlan, array $supplierRanking): string
    {
        $lines = [
            'Intent: '.str_replace('_', ' ', (string) ($parsed['intent'] ?? 'general_stock_note')),
            'Urgency: '.(string) ($parsed['urgency'] ?? 'low'),
        ];

        foreach ($restockPlan as $plan) {
            $lines[] = "{$plan['ingredient_name']}: recommend {$plan['recommended_quantity']} {$plan['unit']} because {$plan['reasoning']}";
        }

        if ($supplierRanking['recommended_supplier'] ?? null) {
            $supplier = $supplierRanking['recommended_supplier'];
            $lines[] = "Recommended supplier: {$supplier['name']} (score {$supplier['score']}). {$supplier['explanation']}";
        } else {
            $lines[] = 'No supplier could be ranked, so no purchase order draft was created.';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseOrderPayload(PurchaseOrder $purchaseOrder): array
    {
        return [
            'id' => $purchaseOrder->id,
            'po_number' => $purchaseOrder->po_number,
            'status' => $purchaseOrder->status,
            'supplier_id' => $purchaseOrder->supplier_id,
            'supplier_name' => $purchaseOrder->supplier?->name,
            'subtotal' => (float) $purchaseOrder->subtotal,
            'approval_request_id' => $purchaseOrder->approvalRequest?->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function understandingSummary(array $parsed): string
    {
        $intent = str_replace('_', ' ', (string) ($parsed['intent'] ?? 'general stock note'));
        $ingredients = collect($parsed['ingredients'] ?? [])
            ->map(fn ($ingredient): string => is_array($ingredient) ? (string) ($ingredient['name'] ?? 'unknown item') : 'unknown item')
            ->filter()
            ->implode(', ');
        $supplier = $parsed['supplier_name'] ?? null;

        return 'Message interpreted as '.$intent.($ingredients ? " for {$ingredients}" : '').($supplier ? " with supplier hint {$supplier}." : '.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $matchedInventory
     */
    private function inventorySummary(array $matchedInventory): string
    {
        if ($matchedInventory === []) {
            return 'No matching inventory records were found.';
        }

        return collect($matchedInventory)
            ->map(fn (array $item): string => ($item['name'] ?? 'Ingredient').' has '.number_format((float) ($item['current_quantity'] ?? 0), 2).' '.($item['unit'] ?? 'unit').' against minimum '.number_format((float) ($item['minimum_stock'] ?? 0), 2).'.')
            ->implode(' ');
    }

    /**
     * @param  array<int, array<string, mixed>>  $restockPlan
     */
    private function restockPlanSummary(array $restockPlan): string
    {
        if ($restockPlan === []) {
            return 'No restock plan was created.';
        }

        return collect($restockPlan)
            ->map(fn (array $plan): string => 'Recommend '.number_format((float) ($plan['recommended_quantity'] ?? 0), 2).' '.($plan['unit'] ?? 'unit').' for '.($plan['ingredient_name'] ?? 'ingredient').'.')
            ->implode(' ');
    }

    /**
     * @param  array<string, mixed>  $supplierRanking
     */
    private function supplierDecisionSummary(array $supplierRanking): string
    {
        $supplier = $supplierRanking['recommended_supplier'] ?? null;

        if (! $supplier) {
            return 'No supplier could be confidently selected from the available records.';
        }

        return 'Recommended supplier is '.$supplier['name'].' with score '.$supplier['score'].'. '.$supplier['explanation'];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function confidenceFromParsed(array $parsed): ?float
    {
        return is_numeric($parsed['confidence'] ?? null) ? (float) $parsed['confidence'] : null;
    }
}
