<?php

namespace App\Http\Controllers;

use App\Models\AgentRun;
use App\Models\AgentToolCall;
use App\Models\ApprovalRequest;
use App\Models\ExpiryLossRecommendation;
use App\Models\PurchaseOrder;
use App\Models\SupplierEmailDraft;
use App\Services\Agent\PhaseOneCapabilityService;
use App\Services\Agent\TingHaoAgentService;
use App\Services\Qwen\QwenClient;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AgentController extends Controller
{
    public function index(Request $request, QwenClient $qwenClient, PhaseOneCapabilityService $capabilityService): View
    {
        $runs = AgentRun::query()
            ->select(['id', 'user_id', 'input_text', 'status', 'parsed_intent', 'final_summary', 'qwen_mocked', 'created_at'])
            ->with('user:id,name,role')
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $isAdmin = $request->user()->isAdmin();
        $workflowRun = $this->workflowRunFor($request, $isAdmin);

        return view('agent.index', [
            'title' => 'Ting Hao | Agent Audit Console',
            'runs' => $runs,
            'workflowRun' => $workflowRun,
            'agentAudit' => $this->agentAuditVisualizer($workflowRun),
            'qwenConfigured' => $qwenClient->isConfigured(),
            'qwenMockMode' => $qwenClient->isMockMode(),
            'qwenModel' => config('qwen.model', 'qwen-plus'),
            'phaseOneCapabilities' => $capabilityService->map($request->user()),
            'autopilotStats' => [
                'pending_po_approvals' => ApprovalRequest::query()
                    ->when(! $isAdmin, fn ($query) => $query->where('requested_by', $request->user()->id))
                    ->where('status', ApprovalRequest::STATUS_PENDING)
                    ->count(),
                'email_drafts_waiting' => SupplierEmailDraft::query()
                    ->when(! $isAdmin, fn ($query) => $query->whereHas('purchaseOrder', fn ($purchaseOrderQuery) => $purchaseOrderQuery->where('requested_by', $request->user()->id)))
                    ->where('status', SupplierEmailDraft::STATUS_DRAFT)
                    ->count(),
                'expiry_risk_rm' => (float) ExpiryLossRecommendation::query()
                    ->whereIn('status', ExpiryLossRecommendation::OPEN_STATUSES)
                    ->sum('potential_loss'),
                'recent_missions' => AgentRun::query()
                    ->when(! $isAdmin, fn ($query) => $query->where('user_id', $request->user()->id))
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
            ],
        ]);
    }

    public function run(Request $request, TingHaoAgentService $agentService): RedirectResponse
    {
        $validated = $request->validate([
            'input_text' => ['required', 'string', 'max:5000'],
        ]);

        $agentRun = $agentService->run($request->user(), $validated['input_text']);

        return redirect()
            ->route('agent.runs.show', $agentRun)
            ->with('status', 'TingHao Agent completed the procurement analysis.');
    }

    public function show(Request $request, AgentRun $agentRun): View
    {
        abort_if(! $request->user()->isAdmin() && $agentRun->user_id !== $request->user()->id, 403);

        $agentRun->load([
            'user:id,name,role',
            'toolCalls' => fn ($query) => $query->oldest(),
            'reasoningSteps.relatedToolCall',
            'purchaseOrders.approvalRequest',
            'purchaseOrders.latestSupplierEmailDraft',
            'purchaseOrders.supplier',
            'expiryLossRecommendations.ingredient',
        ]);

        return view('agent.show', [
            'title' => 'Ting Hao | Agent Run #'.$agentRun->id,
            'agentRun' => $agentRun,
            'parsed' => $agentRun->parsed_intent ?? [],
        ]);
    }

    private function workflowRunFor(Request $request, bool $isAdmin): ?AgentRun
    {
        $baseQuery = AgentRun::query()
            ->with([
                'user:id,name,role',
                'toolCalls' => fn ($query) => $query->oldest(),
                'reasoningSteps' => fn ($query) => $query->orderBy('step_order')->orderBy('id'),
                'reasoningSteps.relatedToolCall',
                'approvalRequests',
                'approvalRequests.reviewedBy:id,name',
                'purchaseOrders' => fn ($query) => $query->latest(),
                'purchaseOrders.approvalRequest',
                'purchaseOrders.approvalRequest.reviewedBy:id,name',
                'purchaseOrders.supplier',
                'purchaseOrders.items.ingredient',
                'purchaseOrders.latestSupplierEmailDraft',
                'supplierEmailDrafts',
                'expiryLossRecommendations.ingredient',
                'expiryLossRecommendations.reviewedBy:id,name',
            ])
            ->when(! $isAdmin, fn ($query) => $query->where('user_id', $request->user()->id));

        $selectedRunId = $request->integer('run');
        $selectedRun = $selectedRunId > 0
            ? (clone $baseQuery)->whereKey($selectedRunId)->first()
            : null;

        return $selectedRun ?: (clone $baseQuery)->latest()->first();
    }

    /**
     * Build a presentation-only audit trail from persisted agent records.
     *
     * @return array<string, mixed>
     */
    private function agentAuditVisualizer(?AgentRun $agentRun): array
    {
        if (! $agentRun) {
            return [
                'has_run' => false,
                'summary' => [],
                'milestones' => collect([
                    'Trigger Received', 'Request Interpreted', 'Inventory and Prediction Checked',
                    'Reorder and Supplier Decided', 'PO Draft Prepared', 'Human Approval',
                    'Supplier Action and Audit Completed',
                ])->map(fn (string $title): array => $this->auditMilestone($title, 'skipped', 'System Audit', null, 'No run selected.'))
                    ->all(),
                'selected_milestone' => 0,
                'checkpoint' => ['required' => false],
                'outcomes' => [],
            ];
        }

        $isExpiry = $this->workflowType($agentRun) === 'expiry';
        $toolCalls = $agentRun->toolCalls;
        $reasoningSteps = $agentRun->reasoningSteps;
        $toolNames = $toolCalls->pluck('tool_name');
        $purchaseOrder = $agentRun->purchaseOrders->first();
        $emailDraft = $purchaseOrder?->latestSupplierEmailDraft ?? $agentRun->supplierEmailDrafts->sortByDesc('id')->first();
        $expiryRecommendation = $agentRun->expiryLossRecommendations->first();
        $approval = $purchaseOrder?->approvalRequest ?? $agentRun->approvalRequests->first();
        $approvalRequired = (bool) ($approval
            || $reasoningSteps->contains('requires_human_approval', true)
            || $purchaseOrder?->status === PurchaseOrder::STATUS_PENDING_APPROVAL
            || $expiryRecommendation);

        $approvalState = match ($approval?->status) {
            ApprovalRequest::STATUS_APPROVED => 'completed',
            ApprovalRequest::STATUS_REJECTED => 'failed',
            ApprovalRequest::STATUS_PENDING => 'pending',
            default => $this->expiryApprovalState($expiryRecommendation, $approvalRequired),
        };

        $milestones = $this->auditMilestones($agentRun, $purchaseOrder, $emailDraft, $expiryRecommendation, $approval, $approvalRequired, $approvalState);
        $selectedMilestone = collect($milestones)->search(fn (array $milestone): bool => in_array($milestone['state'], ['pending', 'failed'], true));
        if ($selectedMilestone === false) {
            $selectedMilestone = max(0, collect($milestones)->where('state', 'completed')->keys()->last() ?? 0);
        }

        return [
            'has_run' => true,
            'summary' => [
                'run_id' => '#'.$agentRun->id,
                'mission_type' => str((string) data_get($agentRun->parsed_intent, 'intent', $agentRun->input_type ?: 'agent_run'))->replace('_', ' ')->title()->toString(),
                'agent_status' => str($agentRun->status)->replace('_', ' ')->title()->toString(),
                'procurement_status' => $this->procurementWorkflowStatus($agentRun, $purchaseOrder, $expiryRecommendation, $approval),
                'started_at' => $agentRun->created_at?->format('d M Y, H:i:s'),
                'owner' => $agentRun->user?->name ?? 'System',
                'qwen_mode' => $agentRun->qwen_mocked ? 'Mock mode' : 'Live Qwen',
                'approval_state' => $approvalRequired ? str($approvalState)->replace('_', ' ')->title()->toString() : 'Not required',
            ],
            'milestones' => $milestones,
            'selected_milestone' => $selectedMilestone,
            'checkpoint' => [
                'required' => $approvalRequired,
                'status' => $approvalRequired ? str($approvalState)->replace('_', ' ')->title()->toString() : 'Not required',
                'reviewed_by' => $approval?->reviewedBy?->name ?? $expiryRecommendation?->reviewedBy?->name ?? 'Not reviewed',
                'reviewed_at' => $approval && $approval->status !== ApprovalRequest::STATUS_PENDING
                    ? $approval->updated_at?->format('d M Y, H:i:s')
                    : ($expiryRecommendation && $expiryRecommendation->status !== ExpiryLossRecommendation::STATUS_ACTIVE ? $expiryRecommendation->updated_at?->format('d M Y, H:i:s') : null),
                'changed_fields' => data_get($agentRun->parsed_intent, 'approval.changed_fields') ?: 'None recorded',
            ],
            'outcomes' => [
                $this->auditOutcome('PO draft created', (bool) $purchaseOrder, $purchaseOrder?->po_number, $purchaseOrder ? route('purchase-orders.show', $purchaseOrder) : null),
                $this->auditOutcome('Supplier selected', (bool) $purchaseOrder?->supplier, $purchaseOrder?->supplier?->name),
                $this->auditOutcome('Email drafted', (bool) $emailDraft, $emailDraft ? 'Draft #'.$emailDraft->id : null, $emailDraft ? route('supplier-email-drafts.show', $emailDraft) : null),
                $this->auditOutcome('Marked sent', (bool) $emailDraft?->sent_at, $emailDraft?->sent_at?->format('d M Y, H:i')),
                $this->auditOutcome('Receiving completed', in_array($purchaseOrder?->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED], true), $purchaseOrder ? str($purchaseOrder->status)->replace('_', ' ')->title()->toString() : null),
                $this->auditOutcome('Audit stored', true, 'AgentRun #'.$agentRun->id),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function auditMilestones(
        AgentRun $agentRun,
        ?PurchaseOrder $purchaseOrder,
        ?SupplierEmailDraft $emailDraft,
        ?ExpiryLossRecommendation $expiryRecommendation,
        ?ApprovalRequest $approval,
        bool $approvalRequired,
        string $approvalState,
    ): array {
        $toolCalls = $agentRun->toolCalls;
        $reasoning = $agentRun->reasoningSteps;
        $toolNames = $toolCalls->pluck('tool_name');
        $failed = fn (Collection $calls): bool => $calls->contains(fn (AgentToolCall $call): bool => in_array($call->status, ['failed', 'blocked'], true));
        $callsFor = fn (array $names): Collection => $toolCalls->filter(fn (AgentToolCall $call): bool => in_array($call->tool_name, $names, true));
        $stepSummary = function (array $types, array $tools = []) use ($reasoning): ?string {
            return $reasoning->first(function ($step) use ($types, $tools): bool {
                return in_array($step->step_type, $types, true)
                    || ($step->relatedToolCall && in_array($step->relatedToolCall->tool_name, $tools, true));
            })?->summary;
        };
        $latestConfidence = function (array $types) use ($reasoning): ?string {
            $confidence = $reasoning->whereIn('step_type', $types)->whereNotNull('confidence')->last()?->confidence;

            return $confidence !== null ? (int) round(((float) $confidence) * 100).'%' : null;
        };

        $interpretTools = $callsFor(['parse_procurement_message', 'qwen_parse_procurement_message']);
        $inventoryTools = $callsFor([
            'get_inventory', 'lookup_inventory', 'read_stock_prediction', 'predict_stock_action',
            'scan_expiring_ingredients', 'calculate_expiry_loss',
        ]);
        $decisionTools = $callsFor([
            'qwen_select_next_action', 'compare_suppliers', 'rank_suppliers', 'select_supplier',
            'plan_restock_quantity', 'generate_expiry_recommendation',
        ]);
        $draftTools = $callsFor(['create_purchase_order', 'create_purchase_order_draft']);
        $supplierTools = $callsFor(['generate_email_draft', 'generate_supplier_email_draft', 'mark_sent', 'mark_email_sent', 'receive_goods', 'close_purchase_order']);

        $interpretRecorded = $interpretTools->isNotEmpty() || $reasoning->contains(fn ($step): bool => in_array($step->step_type, ['understand', 'observe'], true));
        $inventoryRecorded = $inventoryTools->isNotEmpty();
        $decisionRecorded = $decisionTools->isNotEmpty() || $reasoning->contains(fn ($step): bool => in_array($step->step_type, ['plan', 'decision', 'risk_check'], true));
        $qwenDecision = $toolNames->contains(fn (string $name): bool => in_array($name, [
            'qwen_select_next_action', 'generate_expiry_recommendation',
        ], true)) || $interpretTools->isNotEmpty();
        $predictionActor = $toolNames->contains(fn (string $name): bool => in_array($name, ['read_stock_prediction', 'predict_stock_action'], true))
            ? 'FastAPI Prediction'
            : 'Laravel Tool';

        $supplierResult = $purchaseOrder?->supplier
            ? 'Supplier selected: '.$purchaseOrder->supplier->name.'.'
            : ($expiryRecommendation ? 'Expiry recommendation stored for human review.' : 'No supplier action was recorded.');
        $decisionSummary = $stepSummary(['decision', 'plan', 'risk_check'], $decisionTools->pluck('tool_name')->all())
            ?? ($purchaseOrder?->agent_reasoning ? str($purchaseOrder->agent_reasoning)->limit(180)->toString() : null)
            ?? $supplierResult;

        $finalParts = ['Audit stored as AgentRun #'.$agentRun->id.'.'];
        if ($emailDraft) {
            $finalParts[] = $emailDraft->sent_at ? 'Supplier email was marked sent.' : 'Supplier email draft was recorded.';
        } elseif ($purchaseOrder) {
            $finalParts[] = 'No supplier email action is recorded yet.';
        } else {
            $finalParts[] = 'Supplier communication was not used in this mission.';
        }
        $outcomeRecords = collect([
            $purchaseOrder ? 'PO '.$purchaseOrder->po_number.' ('.str($purchaseOrder->status)->replace('_', ' ')->toString().')' : null,
            $purchaseOrder?->supplier ? 'Supplier '.$purchaseOrder->supplier->name : null,
            $emailDraft ? 'Email draft #'.$emailDraft->id.' ('.str($emailDraft->status)->replace('_', ' ')->toString().')' : null,
            in_array($purchaseOrder?->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED], true) ? 'Receiving completed' : null,
            'AgentRun #'.$agentRun->id.' audit stored',
        ])->filter()->implode('; ');

        return [
            $this->auditMilestone('Trigger Received', 'completed', 'System Audit', $agentRun->created_at, str($agentRun->input_text)->limit(180)->toString(), [
                'action' => 'Created AgentRun #'.$agentRun->id,
            ]),
            $this->auditMilestone('Request Interpreted', $interpretRecorded ? ($failed($interpretTools) ? 'failed' : 'completed') : 'skipped', $qwenDecision ? 'Qwen Decision' : 'Laravel Tool', $interpretTools->last()?->created_at, $stepSummary(['understand', 'observe'], $interpretTools->pluck('tool_name')->all()) ?? data_get($agentRun->parsed_intent, 'summary') ?? 'No separate interpretation record was stored.', [
                'confidence' => $latestConfidence(['understand']),
                'tools' => $interpretTools->pluck('tool_name')->implode(', ') ?: null,
            ]),
            $this->auditMilestone('Inventory and Prediction Checked', $inventoryRecorded ? ($failed($inventoryTools) ? 'failed' : 'completed') : 'skipped', $predictionActor, $inventoryTools->last()?->created_at, $stepSummary(['tool_result', 'observe'], $inventoryTools->pluck('tool_name')->all()) ?? ($inventoryRecorded ? 'Inventory or prediction evidence was recorded.' : 'This mission did not record an inventory or prediction check.'), [
                'tools' => $inventoryTools->pluck('tool_name')->implode(', ') ?: null,
                'reason' => $this->workflowType($agentRun) === 'expiry' ? 'Expiry and RM-loss facts guide this branch.' : 'Current stock and prediction facts guide restock safety.',
            ]),
            $this->auditMilestone('Reorder and Supplier Decided', $decisionRecorded ? ($failed($decisionTools) ? 'failed' : 'completed') : 'skipped', $qwenDecision ? 'Qwen Decision' : 'Laravel Tool', $decisionTools->last()?->created_at, $decisionSummary, [
                'decision' => $decisionSummary,
                'confidence' => $latestConfidence(['decision', 'plan', 'risk_check']),
                'tools' => $decisionTools->pluck('tool_name')->implode(', ') ?: null,
            ]),
            $this->auditMilestone('PO Draft Prepared', $purchaseOrder ? 'completed' : ($failed($draftTools) ? 'failed' : 'skipped'), 'Laravel Tool', $purchaseOrder?->created_at ?? $draftTools->last()?->created_at, $purchaseOrder ? 'Purchase order '.$purchaseOrder->po_number.' was prepared with status '.str($purchaseOrder->status)->replace('_', ' ')->toString().'.' : 'No PO draft was created for this mission.', [
                'action' => $purchaseOrder ? 'Prepared '.$purchaseOrder->po_number : null,
                'tools' => $draftTools->pluck('tool_name')->implode(', ') ?: null,
            ]),
            $this->auditMilestone('Human Approval', $approvalRequired ? $approvalState : 'skipped', 'Human Approval', $approval?->updated_at ?? $expiryRecommendation?->updated_at, $approvalRequired ? 'Human checkpoint is '.str($approvalState)->replace('_', ' ')->toString().'.' : 'No approval checkpoint was required.', [
                'approval' => $approvalRequired ? str($approvalState)->replace('_', ' ')->title()->toString() : null,
                'action' => $approval?->status === ApprovalRequest::STATUS_PENDING ? 'Waiting for admin review' : ($approvalRequired ? 'Review state recorded' : null),
                'reason' => $approvalRequired ? 'Critical actions remain under human control.' : null,
                'reviewed by' => $approval?->reviewedBy?->name ?? $expiryRecommendation?->reviewedBy?->name,
                'reviewed at' => $approval && $approval->status !== ApprovalRequest::STATUS_PENDING
                    ? $approval->updated_at?->format('d M Y, H:i:s')
                    : ($expiryRecommendation && $expiryRecommendation->status !== ExpiryLossRecommendation::STATUS_ACTIVE ? $expiryRecommendation->updated_at?->format('d M Y, H:i:s') : null),
            ]),
            $this->auditMilestone('Supplier Action and Audit Completed', $failed($supplierTools) ? 'failed' : 'completed', 'System Audit', $supplierTools->last()?->updated_at ?? $agentRun->updated_at, implode(' ', $finalParts), [
                'action' => $emailDraft?->sent_at ? 'Marked supplier email as sent' : ($emailDraft ? 'Stored supplier email draft' : 'Stored audit outcome'),
                'tools' => $supplierTools->pluck('tool_name')->implode(', ') ?: null,
                'records' => $outcomeRecords,
            ]),
        ];
    }

    private function auditMilestone(string $title, string $state, string $actor, $timestamp, string $result, array $details = []): array
    {
        return [
            'title' => $title,
            'state' => $state,
            'actor' => $actor,
            'timestamp' => $timestamp?->format('d M Y, H:i:s'),
            'result' => $result,
            'details' => array_filter($details, fn ($value): bool => $value !== null && $value !== ''),
        ];
    }

    private function procurementWorkflowStatus(AgentRun $agentRun, ?PurchaseOrder $purchaseOrder, ?ExpiryLossRecommendation $recommendation, ?ApprovalRequest $approval): string
    {
        if ($approval?->status === ApprovalRequest::STATUS_REJECTED) {
            return 'Rejected by admin';
        }

        if ($purchaseOrder) {
            return $purchaseOrder->status === PurchaseOrder::STATUS_PENDING_APPROVAL
                ? 'Pending admin approval'
                : str($purchaseOrder->status)->replace('_', ' ')->title()->toString();
        }

        if ($recommendation) {
            return 'Expiry review: '.str($recommendation->status)->replace('_', ' ')->title()->toString();
        }

        $stopReason = data_get($agentRun->parsed_intent, 'decision_loop.stop_reason');

        return $stopReason ? str($stopReason)->replace('_', ' ')->title()->toString() : 'No procurement action';
    }

    private function auditStage(string $label, string $state, string $detail): array
    {
        return compact('label', 'state', 'detail');
    }

    private function expiryApprovalState(?ExpiryLossRecommendation $recommendation, bool $required): string
    {
        if (! $required) {
            return 'skipped';
        }

        return match ($recommendation?->status) {
            ExpiryLossRecommendation::STATUS_REVIEWED, ExpiryLossRecommendation::STATUS_COMPLETED => 'completed',
            ExpiryLossRecommendation::STATUS_DISMISSED => 'failed',
            default => 'pending',
        };
    }

    private function auditActionState(bool $isExpiry, ?PurchaseOrder $purchaseOrder, ?ExpiryLossRecommendation $recommendation, string $approvalState): string
    {
        if ($isExpiry) {
            return match ($recommendation?->status) {
                ExpiryLossRecommendation::STATUS_COMPLETED, ExpiryLossRecommendation::STATUS_REVIEWED => 'completed',
                ExpiryLossRecommendation::STATUS_DISMISSED => 'failed',
                ExpiryLossRecommendation::STATUS_ACTIVE => 'pending',
                default => 'skipped',
            };
        }

        if (! $purchaseOrder) {
            return 'skipped';
        }

        return match ($approvalState) {
            'failed' => 'failed',
            'pending' => 'pending',
            default => 'completed',
        };
    }

    private function auditVerifyState(bool $isExpiry, ?PurchaseOrder $purchaseOrder, ?ExpiryLossRecommendation $recommendation, string $actionState): string
    {
        if ($isExpiry) {
            return $recommendation?->status === ExpiryLossRecommendation::STATUS_COMPLETED ? 'completed' : ($actionState === 'failed' ? 'failed' : 'skipped');
        }

        if (in_array($purchaseOrder?->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED], true)) {
            return 'completed';
        }

        return $actionState === 'failed' ? 'failed' : 'skipped';
    }

    /** @return array<int, array<string, mixed>> */
    private function auditTimeline(AgentRun $agentRun, ?ApprovalRequest $approval): array
    {
        $linkedToolIds = $agentRun->reasoningSteps->pluck('related_tool_call_id')->filter()->all();
        $events = collect([[
            'sort_at' => $agentRun->created_at,
            'timestamp' => $agentRun->created_at?->format('H:i:s'),
            'title' => 'Mission triggered',
            'tool' => 'Business trigger',
            'result' => str($agentRun->input_text)->limit(180)->toString(),
            'decision' => 'Agent audit started.',
            'confidence' => null,
            'action' => 'Created AgentRun #'.$agentRun->id,
            'approval' => 'Not applicable',
            'reason' => 'The user or system started a governed mission.',
            'state' => 'completed',
        ]]);

        foreach ($agentRun->reasoningSteps as $step) {
            $tool = $step->relatedToolCall;
            $events->push([
                'sort_at' => $step->created_at,
                'timestamp' => $step->created_at?->format('H:i:s'),
                'title' => $step->title,
                'tool' => $tool?->tool_name ? str($tool->tool_name)->replace('_', ' ')->title()->toString() : 'Reasoning record',
                'result' => $step->step_type === 'tool_result' ? $step->summary : ($tool ? $this->safeToolResult($tool) : 'Safe audit observation recorded.'),
                'decision' => in_array($step->step_type, ['decision', 'plan', 'risk_check', 'human_checkpoint'], true) ? $step->summary : 'No separate decision at this step.',
                'confidence' => $step->confidence !== null ? (int) round(((float) $step->confidence) * 100).'%' : null,
                'action' => $tool?->tool_name ? str($tool->tool_name)->replace('_', ' ')->title()->toString() : str($step->step_type)->replace('_', ' ')->title()->toString(),
                'approval' => $step->requires_human_approval ? 'Human approval required' : 'Not required',
                'reason' => $step->summary,
                'state' => $tool && in_array($tool->status, ['failed', 'blocked'], true) ? 'failed' : 'completed',
            ]);
        }

        foreach ($agentRun->toolCalls->whereNotIn('id', $linkedToolIds) as $tool) {
            $events->push([
                'sort_at' => $tool->created_at,
                'timestamp' => $tool->created_at?->format('H:i:s'),
                'title' => str($tool->tool_name)->replace('_', ' ')->title()->toString(),
                'tool' => $tool->tool_name,
                'result' => $this->safeToolResult($tool),
                'decision' => 'Laravel validated and executed the recorded tool.',
                'confidence' => null,
                'action' => str($tool->tool_name)->replace('_', ' ')->title()->toString(),
                'approval' => in_array($tool->tool_name, ['request_human_approval', 'human_approval'], true) ? 'Human approval required' : 'Not required',
                'reason' => 'This is a persisted tool result, not raw chain-of-thought.',
                'state' => in_array($tool->status, ['failed', 'blocked'], true) ? 'failed' : 'completed',
            ]);
        }

        if ($approval) {
            $events->push([
                'sort_at' => $approval->updated_at,
                'timestamp' => $approval->updated_at?->format('H:i:s'),
                'title' => 'Human approval checkpoint',
                'tool' => 'Admin review',
                'result' => 'Approval is '.str($approval->status)->replace('_', ' ')->toString().'.',
                'decision' => $approval->review_notes ?: 'Admin approval state recorded.',
                'confidence' => null,
                'action' => $approval->status === ApprovalRequest::STATUS_PENDING ? 'Waiting for admin' : 'Admin review recorded',
                'approval' => str($approval->status)->replace('_', ' ')->title()->toString(),
                'reason' => 'Critical procurement actions remain under human control.',
                'state' => $approval->status === ApprovalRequest::STATUS_REJECTED ? 'failed' : ($approval->status === ApprovalRequest::STATUS_PENDING ? 'pending' : 'completed'),
            ]);
        }

        return $events->sortBy('sort_at')->values()->map(function (array $event): array {
            unset($event['sort_at']);

            return $event;
        })->all();
    }

    private function safeToolResult(AgentToolCall $tool): string
    {
        $payload = $tool->output_payload ?? [];
        $summary = data_get($payload, 'summary')
            ?? data_get($payload, 'message')
            ?? data_get($payload, 'reason_summary')
            ?? data_get($payload, 'result_summary')
            ?? data_get($payload, 'status');

        return $summary
            ? str((string) $summary)->limit(180)->toString()
            : 'Tool completed and stored a compact audit result.';
    }

    private function auditOutcome(string $label, bool $completed, ?string $detail = null, ?string $url = null): array
    {
        return compact('label', 'completed', 'detail', 'url');
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowVisualizer(?AgentRun $agentRun): array
    {
        return [
            'template' => $this->templateWorkflowNodes(),
            'live' => $agentRun
                ? ($this->workflowType($agentRun) === 'expiry' ? $this->expiryWorkflowNodes($agentRun) : $this->liveWorkflowNodes($agentRun))
                : $this->emptyLiveWorkflowNodes(),
            'live_type' => $agentRun && $this->workflowType($agentRun) === 'expiry' ? 'Expiry Loss Prevention' : 'Procurement Autopilot',
            'selected_summary' => [
                'run_id' => $agentRun ? '#'.$agentRun->id : null,
                'status' => $agentRun ? str($agentRun->status)->replace('_', ' ')->title()->toString() : 'No live run yet',
                'user' => $agentRun?->user?->name,
                'created' => $agentRun?->created_at?->format('d M Y, H:i'),
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function templateWorkflowNodes(): array
    {
        return [
            $this->workflowNode('trigger', 'Trigger', 'not-started', 'Staff message, low-stock page, or Stock Planner action starts an autopilot mission.', 'Business input', 'The workflow begins only after a user requests planning or review.'),
            $this->workflowNode('qwen_reasoning', 'Qwen Reasoning', 'not-started', 'Qwen summarizes intent or prepares business-friendly supplier wording.', 'parse_procurement_message', 'Qwen does not approve actions, send emails, or expose raw chain-of-thought.'),
            $this->workflowNode('inventory_checked', 'Inventory Checked', 'not-started', 'Laravel checks existing inventory records and low-stock thresholds.', 'get_inventory', 'Uses TingHao database records rather than a separate AI inventory source.'),
            $this->workflowNode('prediction_engine', 'Prediction Engine', 'not-started', 'FastAPI Stock Prediction can recommend add, monitor, buy less, or use-before-expiry actions.', 'predict_stock_action', 'Prediction facts stay separate from Qwen explanations.'),
            $this->workflowNode('supplier_selected', 'Supplier Selected', 'not-started', 'Supplier records are matched or ranked for the ingredient.', 'select_supplier', 'The agent suggests a supplier from existing TingHao records.'),
            $this->workflowNode('po_draft_created', 'PO Draft Created', 'not-started', 'A purchase order draft may be prepared for review.', 'create_purchase_order', 'Draft creation does not approve the PO or change stock by itself.'),
            $this->workflowNode('admin_approval', 'Admin Approval', 'pending', 'Human-in-the-loop checkpoint. Admin approves, edits, or rejects critical actions.', 'human_approval', 'The agent cannot approve purchase orders or supplier emails automatically.', null, null, true),
            $this->workflowNode('email_drafted', 'Email Drafted', 'not-started', 'After PO approval, Qwen can prepare a supplier email draft for admin review.', 'generate_email_draft', 'No real email is sent automatically.'),
            $this->workflowNode('marked_sent', 'Marked Sent', 'not-started', 'Admin marks the reviewed email as sent for the demo-safe workflow.', 'mark_sent', 'This records the state in TingHao without sending a real email.'),
            $this->workflowNode('audit_logged', 'Audit Logged', 'not-started', 'AgentRun, tool calls, approvals, PO, and email states remain available for audit.', 'audit_trail', 'Judges can inspect proof without seeing secrets or raw chain-of-thought.'),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function emptyLiveWorkflowNodes(): array
    {
        return collect($this->templateWorkflowNodes())
            ->map(fn (array $node): array => [
                ...$node,
                'status' => 'not-started',
                'summary' => 'Run an agent mission to see live workflow progress here.',
                'detail' => 'No AgentRun is available for this user yet.',
                'tool' => 'Not recorded',
            ])
            ->all();
    }

    private function workflowType(AgentRun $agentRun): string
    {
        $intent = strtolower((string) data_get($agentRun->parsed_intent, 'intent', ''));
        $inputType = strtolower((string) $agentRun->input_type);

        return str_contains($intent.' '.$inputType, 'expiry_loss') ? 'expiry' : 'procurement';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expiryWorkflowNodes(AgentRun $agentRun): array
    {
        $toolCalls = $agentRun->toolCalls;
        $recommendations = $agentRun->expiryLossRecommendations;
        $parsed = $agentRun->parsed_intent ?? [];
        $matchedIngredients = collect($parsed['matched_ingredients'] ?? []);
        $scanTool = $this->firstToolCall($toolCalls, ['scan_expiring_ingredients']);
        $calculationTool = $this->firstToolCall($toolCalls, ['calculate_expiry_loss']);
        $recommendationTool = $this->firstToolCall($toolCalls, ['generate_expiry_recommendation']);
        $saveTool = $this->firstToolCall($toolCalls, ['save_expiry_recommendation']);
        $firstRecommendation = $recommendations->first();
        $recommendationUrl = $firstRecommendation
            ? route('expiry-loss-recommendations.show', $firstRecommendation)
            : null;
        $recommendationLabel = $firstRecommendation
            ? 'Expiry recommendation #'.$firstRecommendation->id
            : null;
        $hasRiskFacts = $matchedIngredients->isNotEmpty() || $recommendations->isNotEmpty();
        $totalLoss = $recommendations->sum(fn (ExpiryLossRecommendation $recommendation): float => (float) ($recommendation->potential_loss ?? 0));
        $adminReviewCompleted = $recommendations->isNotEmpty() && $recommendations->every(
            fn (ExpiryLossRecommendation $recommendation): bool => in_array($recommendation->status, [
                ExpiryLossRecommendation::STATUS_REVIEWED,
                ExpiryLossRecommendation::STATUS_DISMISSED,
                ExpiryLossRecommendation::STATUS_COMPLETED,
            ], true)
        );
        $runUrl = route('agent.runs.show', $agentRun);

        return [
            $this->workflowNode(
                'trigger',
                'Trigger',
                $agentRun->status === AgentRun::STATUS_FAILED ? 'failed' : 'completed',
                'Expiry loss prevention scan started.',
                $agentRun->input_type ?: 'expiry_loss_prevention',
                'Run #'.$agentRun->id.' was created by '.($agentRun->user?->name ?? 'System').' at '.$agentRun->created_at->format('d M Y, H:i').'.',
                'Agent Run #'.$agentRun->id,
                $runUrl
            ),
            $this->workflowNode(
                'inventory_scan',
                'Inventory Scan',
                $scanTool ? $this->statusFromTool($scanTool) : ($hasRiskFacts ? 'completed' : ($agentRun->status === AgentRun::STATUS_FAILED ? 'blocked' : 'not-started')),
                $hasRiskFacts ? 'Inventory was checked for stock approaching expiry.' : 'No expiry scan result was recorded.',
                $scanTool?->tool_name ?: 'Not recorded',
                $matchedIngredients->isNotEmpty()
                    ? $matchedIngredients->count().' ingredient record(s) were identified in the expiry window.'
                    : ($recommendations->isNotEmpty() ? $recommendations->count().' expiry recommendation record(s) were linked.' : 'Not recorded.'),
                $recommendationLabel,
                $recommendationUrl
            ),
            $this->workflowNode(
                'expiry_risk_calculated',
                'Expiry Risk Calculated',
                $calculationTool ? $this->statusFromTool($calculationTool) : ($hasRiskFacts ? 'completed' : 'not-started'),
                $hasRiskFacts ? 'Days until expiry and quantity at risk were calculated.' : 'No expiry risk calculation was recorded.',
                $calculationTool?->tool_name ?: 'Not recorded',
                $firstRecommendation
                    ? ($firstRecommendation->ingredient?->name ?? 'Ingredient').' has '.$firstRecommendation->days_until_expiry.' day(s) until expiry.'
                    : 'Not recorded.',
                $recommendationLabel,
                $recommendationUrl
            ),
            $this->workflowNode(
                'rm_loss_calculated',
                'RM Loss Calculated',
                $calculationTool ? $this->statusFromTool($calculationTool) : ($hasRiskFacts ? 'completed' : 'not-started'),
                $hasRiskFacts ? 'Potential inventory loss was calculated from quantity and cost facts.' : 'No RM loss calculation was recorded.',
                $calculationTool?->tool_name ?: 'Not recorded',
                $hasRiskFacts ? 'Recorded potential loss: RM '.number_format((float) data_get($parsed, 'total_potential_loss', $totalLoss), 2).'.' : 'Not recorded.',
                $recommendationLabel,
                $recommendationUrl
            ),
            $this->workflowNode(
                'qwen_recommendation',
                'Qwen Recommendation',
                $recommendationTool ? $this->statusFromTool($recommendationTool) : ($recommendations->isNotEmpty() ? 'completed' : 'not-started'),
                $recommendations->isNotEmpty() ? 'A business-friendly expiry action was prepared.' : 'No Qwen recommendation was recorded.',
                $recommendationTool?->tool_name ?: 'Not recorded',
                $firstRecommendation?->recommendation_title ?: 'Not recorded. Raw chain-of-thought is never displayed.',
                $recommendationLabel,
                $recommendationUrl
            ),
            $this->workflowNode(
                'admin_review',
                'Admin Review',
                $recommendations->isEmpty() ? 'not-started' : ($adminReviewCompleted ? 'completed' : 'pending'),
                $recommendations->isEmpty()
                    ? 'No recommendation is waiting for admin review.'
                    : ($adminReviewCompleted ? 'Admin review was recorded.' : 'Waiting for admin review.'),
                'human_approval',
                'Human-in-the-loop: admin reviews, dismisses, or completes the recommendation.',
                $recommendationLabel,
                $recommendationUrl,
                true
            ),
            $this->workflowNode(
                'audit_logged',
                'Audit Logged',
                $saveTool ? $this->statusFromTool($saveTool) : ($toolCalls->isNotEmpty() || $agentRun->status === AgentRun::STATUS_COMPLETED ? 'completed' : 'not-started'),
                $toolCalls->count().' tool call(s) are stored for this expiry mission.',
                $saveTool?->tool_name ?: ($toolCalls->isNotEmpty() ? 'audit_trail' : 'Not recorded'),
                'Safe summaries, tool calls, recommendation records, and human review state remain available for audit.',
                'Agent Run #'.$agentRun->id,
                $runUrl
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function liveWorkflowNodes(AgentRun $agentRun): array
    {
        $toolCalls = $agentRun->toolCalls;
        $purchaseOrder = $agentRun->purchaseOrders->first();
        $approvalRequest = $purchaseOrder?->approvalRequest ?: $agentRun->approvalRequests->first();
        $emailDraft = $purchaseOrder?->latestSupplierEmailDraft ?: $agentRun->supplierEmailDrafts->sortByDesc('created_at')->first();
        $parsed = $agentRun->parsed_intent ?? [];
        $matchedInventory = collect($parsed['matched_inventory'] ?? []);
        $stockPrediction = (array) data_get($parsed, 'stock_prediction', []);

        $triggerStatus = $agentRun->status === AgentRun::STATUS_FAILED ? 'failed' : 'completed';
        $qwenTool = $this->firstToolCall($toolCalls, ['qwen_select_next_action', 'parse_procurement_message', 'call_qwen_email_draft', 'generate_supplier_email_draft']);
        $inventoryTool = $this->firstToolCall($toolCalls, ['scan_inventory', 'lookup_inventory', 'read_stock_prediction']);
        $predictionTool = $this->firstToolCall($toolCalls, ['predict_stock_action', 'read_stock_prediction']);
        $supplierTool = $this->firstToolCall($toolCalls, ['compare_suppliers', 'select_supplier', 'rank_suppliers']);
        $draftTool = $this->firstToolCall($toolCalls, ['create_purchase_order_draft', 'prepare_autopilot_po_draft']);
        $emailTool = $this->firstToolCall($toolCalls, ['generate_supplier_email_draft', 'save_supplier_email_draft']);
        $sentTool = $this->firstToolCall($toolCalls, ['send_supplier_email_gmail', 'mark_supplier_email_sent', 'mark_email_sent']);
        $wasDelivered = $emailDraft?->delivery_status === SupplierEmailDraft::DELIVERY_DELIVERED;
        $runLabel = 'Agent Run #'.$agentRun->id;
        $runUrl = route('agent.runs.show', $agentRun);
        $purchaseOrderLabel = $purchaseOrder?->po_number;
        $purchaseOrderUrl = $purchaseOrder ? route('purchase-orders.show', $purchaseOrder) : null;
        $emailDraftLabel = $emailDraft ? 'Supplier email draft #'.$emailDraft->id : null;
        $emailDraftUrl = $emailDraft ? route('supplier-email-drafts.show', $emailDraft) : null;

        return [
            $this->workflowNode(
                'trigger',
                'Trigger',
                $triggerStatus,
                $agentRun->input_type === 'stock_prediction_restock' ? 'Stock Planner triggered restock planning.' : 'Agent mission was started from the audit console.',
                $agentRun->input_type ?: 'agent_run',
                'Run '.$agentRun->id.' was created by '.($agentRun->user?->name ?? 'System').' at '.$agentRun->created_at->format('d M Y, H:i').'.',
                $runLabel,
                $runUrl
            ),
            $this->workflowNode(
                'qwen_reasoning',
                'Qwen Reasoning',
                $qwenTool ? $this->statusFromTool($qwenTool) : ($agentRun->qwen_mocked ? 'completed' : 'not-started'),
                $qwenTool || $agentRun->qwen_mocked
                    ? ($agentRun->qwen_mocked ? 'Mock Qwen mode produced a safe business summary.' : 'Qwen produced a safe business summary or supplier draft.')
                    : 'No Qwen call was required for this deterministic workflow.',
                $qwenTool?->tool_name ?: 'Not recorded',
                $agentRun->final_summary ?: 'The run stores safe summaries only; raw chain-of-thought is not shown.',
                $runLabel,
                $runUrl
            ),
            $this->workflowNode(
                'inventory_checked',
                'Inventory Checked',
                $inventoryTool || $matchedInventory->isNotEmpty() || filled($stockPrediction) ? 'completed' : 'not-started',
                $matchedInventory->isNotEmpty()
                    ? 'Matched '.$matchedInventory->count().' inventory record(s).'
                    : (filled($stockPrediction) ? 'Stock prediction facts include current quantity and minimum stock.' : 'No inventory match was recorded for this run.'),
                $inventoryTool?->tool_name ?: 'Not recorded',
                $this->inventoryDetail($matchedInventory, $stockPrediction),
                $runLabel,
                $runUrl
            ),
            $this->workflowNode(
                'prediction_engine',
                'Prediction Engine',
                $predictionTool || filled($stockPrediction) ? 'completed' : 'not-started',
                filled($stockPrediction)
                    ? 'FastAPI prediction recommended '.str_replace('_', ' ', (string) ($stockPrediction['recommended_action'] ?? 'monitor')).'.'
                    : 'This run did not require the FastAPI prediction service.',
                $predictionTool?->tool_name ?: (filled($stockPrediction) ? 'predict_stock_action' : 'Not recorded'),
                filled($stockPrediction)
                    ? 'Risk: '.($stockPrediction['risk_label'] ?? $stockPrediction['risk_level'] ?? 'unknown').'. Suggested quantity: '.($stockPrediction['suggested_quantity'] ?? 'not provided').'.'
                    : 'Not recorded for this mission.',
                $runLabel,
                $runUrl
            ),
            $this->workflowNode(
                'supplier_selected',
                'Supplier Selected',
                $supplierTool || $purchaseOrder?->supplier ? 'completed' : 'not-started',
                $purchaseOrder?->supplier
                    ? $purchaseOrder->supplier->name.' is linked to the purchase order.'
                    : 'No supplier was selected for this run.',
                $supplierTool?->tool_name ?: ($purchaseOrder?->supplier ? 'select_supplier' : 'Not recorded'),
                $purchaseOrder?->supplier?->email ? 'Supplier email on file: '.$purchaseOrder->supplier->email.'.' : 'Not recorded.',
                $purchaseOrderLabel,
                $purchaseOrderUrl
            ),
            $this->workflowNode(
                'po_draft_created',
                'PO Draft Created',
                $purchaseOrder ? 'completed' : ($draftTool ? $this->statusFromTool($draftTool) : 'not-started'),
                $purchaseOrder
                    ? $purchaseOrder->po_number.' is '.str_replace('_', ' ', $purchaseOrder->status).'.'
                    : 'No purchase order draft is linked to this run.',
                $draftTool?->tool_name ?: ($purchaseOrder ? 'create_purchase_order' : 'Not recorded'),
                $purchaseOrder
                    ? 'Items: '.$purchaseOrder->items->map(fn ($item) => ($item->ingredient?->name ?? $item->description).' '.number_format((float) $item->quantity, 2).' '.$item->unit)->implode(', ')
                    : 'Not recorded.',
                $purchaseOrderLabel,
                $purchaseOrderUrl
            ),
            $this->workflowNode(
                'admin_approval',
                'Admin Approval',
                $this->approvalStatus($approvalRequest, $purchaseOrder),
                $this->approvalSummary($approvalRequest, $purchaseOrder),
                'human_approval',
                'Human-in-the-loop: admin approval is required before supplier communication proceeds.',
                $purchaseOrderLabel,
                $purchaseOrderUrl,
                true
            ),
            $this->workflowNode(
                'email_drafted',
                'Email Drafted',
                $emailDraft ? 'completed' : ($purchaseOrder?->status === PurchaseOrder::STATUS_APPROVED ? 'pending' : 'not-started'),
                $emailDraft
                    ? 'Supplier email draft is '.str_replace('_', ' ', $emailDraft->status).'.'
                    : 'Email draft waits until the purchase order is approved.',
                $emailTool?->tool_name ?: ($emailDraft ? 'generate_email_draft' : 'Not recorded'),
                $emailDraft
                    ? 'Subject: '.$emailDraft->subject
                    : 'Not recorded. Qwen can draft business wording after PO approval; no email is sent automatically.',
                $emailDraftLabel,
                $emailDraftUrl
            ),
            $this->workflowNode(
                'marked_sent',
                $wasDelivered ? 'Sent via Gmail' : 'Marked Sent',
                $emailDraft?->status === SupplierEmailDraft::STATUS_SENT ? 'completed' : ($emailDraft ? 'pending' : 'not-started'),
                $emailDraft?->status === SupplierEmailDraft::STATUS_SENT
                    ? ($wasDelivered ? 'Admin explicitly sent the approved supplier email through Gmail.' : 'Admin recorded the demo-safe Mark Sent action.')
                    : 'Waiting for admin-controlled email review and delivery action.',
                $sentTool?->tool_name ?: ($emailDraft?->status === SupplierEmailDraft::STATUS_SENT ? 'mark_sent' : 'Not recorded'),
                $wasDelivered
                    ? 'Delivery was accepted by configured Gmail SMTP and safe provider metadata was recorded.'
                    : 'Demo-safe Mark Sent records workflow progress without sending real email.',
                $emailDraftLabel,
                $emailDraftUrl
            ),
            $this->workflowNode(
                'audit_logged',
                'Audit Logged',
                $toolCalls->isNotEmpty() || $agentRun->reasoningSteps()->exists() ? 'completed' : 'not-started',
                $toolCalls->count().' tool call(s) stored for audit.',
                'audit_trail',
                'AgentRun, safe reasoning summaries, tool calls, PO approval state, and email draft state remain available for judges and developers.',
                $runLabel,
                $runUrl
            ),
        ];
    }

    /**
     * @param  Collection<int, AgentToolCall>|EloquentCollection<int, AgentToolCall>  $toolCalls
     * @param  array<int, string>  $names
     */
    private function firstToolCall(Collection|EloquentCollection $toolCalls, array $names): ?AgentToolCall
    {
        return $toolCalls->first(fn (AgentToolCall $toolCall): bool => in_array($toolCall->tool_name, $names, true));
    }

    private function statusFromTool(AgentToolCall $toolCall): string
    {
        return match ($toolCall->status) {
            'completed' => 'completed',
            'skipped' => 'blocked',
            'failed' => 'failed',
            default => 'pending',
        };
    }

    private function approvalStatus(?ApprovalRequest $approvalRequest, ?PurchaseOrder $purchaseOrder): string
    {
        if ($approvalRequest?->status === ApprovalRequest::STATUS_APPROVED || $purchaseOrder?->status === PurchaseOrder::STATUS_APPROVED) {
            return 'completed';
        }

        if ($approvalRequest?->status === ApprovalRequest::STATUS_REJECTED || $purchaseOrder?->status === PurchaseOrder::STATUS_REJECTED) {
            return 'blocked';
        }

        if ($approvalRequest?->status === ApprovalRequest::STATUS_PENDING || $purchaseOrder?->status === PurchaseOrder::STATUS_PENDING_APPROVAL) {
            return 'pending';
        }

        return $purchaseOrder ? 'pending' : 'not-started';
    }

    private function approvalSummary(?ApprovalRequest $approvalRequest, ?PurchaseOrder $purchaseOrder): string
    {
        if ($approvalRequest?->status === ApprovalRequest::STATUS_APPROVED || $purchaseOrder?->status === PurchaseOrder::STATUS_APPROVED) {
            return 'Admin approved the purchase order checkpoint.';
        }

        if ($approvalRequest?->status === ApprovalRequest::STATUS_REJECTED || $purchaseOrder?->status === PurchaseOrder::STATUS_REJECTED) {
            return 'Admin rejected this purchase order checkpoint.';
        }

        if ($approvalRequest?->status === ApprovalRequest::STATUS_PENDING || $purchaseOrder?->status === PurchaseOrder::STATUS_PENDING_APPROVAL) {
            return 'Waiting for admin approval. The agent cannot approve it.';
        }

        return 'No approval checkpoint has been created yet.';
    }

    /**
     * @param  Collection<int, mixed>  $matchedInventory
     * @param  array<string, mixed>  $stockPrediction
     */
    private function inventoryDetail(Collection $matchedInventory, array $stockPrediction): string
    {
        if ($matchedInventory->isNotEmpty()) {
            return $matchedInventory
                ->take(3)
                ->map(fn (array $item): string => ($item['name'] ?? 'Ingredient').' has '.number_format((float) ($item['current_quantity'] ?? 0), 2).' '.($item['unit'] ?? '').' on hand.')
                ->implode(' ');
        }

        if (filled($stockPrediction)) {
            return 'Current quantity: '.data_get($stockPrediction, 'current_quantity', data_get($stockPrediction, 'calculation_summary.current_quantity', 'unknown')).'; minimum stock: '.data_get($stockPrediction, 'minimum_stock', data_get($stockPrediction, 'calculation_summary.minimum_stock', 'unknown')).'.';
        }

        return 'Inventory checks appear when the mission matches ingredients or Stock Planner facts.';
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowNode(
        string $id,
        string $label,
        string $status,
        string $summary,
        string $tool,
        string $detail,
        ?string $recordLabel = null,
        ?string $recordUrl = null,
        bool $humanLoop = false
    ): array {
        return compact('id', 'label', 'status', 'summary', 'tool', 'detail', 'recordLabel', 'recordUrl', 'humanLoop');
    }
}
