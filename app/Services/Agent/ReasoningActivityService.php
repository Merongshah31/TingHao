<?php

namespace App\Services\Agent;

use App\Models\AgentReasoningStep;
use App\Models\AgentRun;
use App\Models\AgentToolCall;
use App\Models\ApprovalRequest;

class ReasoningActivityService
{
    /**
     * @param  array<string, mixed>|null  $evidence
     */
    public function observe(AgentRun $agentRun, string $title, string $summary, ?array $evidence = null): AgentReasoningStep
    {
        return $this->add($agentRun, AgentReasoningStep::TYPE_OBSERVE, $title, $summary, evidence: $evidence);
    }

    /**
     * @param  array<string, mixed>|null  $evidence
     */
    public function understand(AgentRun $agentRun, string $title, string $summary, ?array $evidence = null): AgentReasoningStep
    {
        return $this->add($agentRun, AgentReasoningStep::TYPE_UNDERSTAND, $title, $summary, evidence: $evidence);
    }

    /**
     * @param  array<string, mixed>|null  $evidence
     */
    public function plan(AgentRun $agentRun, string $title, string $summary, ?array $evidence = null): AgentReasoningStep
    {
        return $this->add($agentRun, AgentReasoningStep::TYPE_PLAN, $title, $summary, evidence: $evidence);
    }

    public function toolAction(AgentRun $agentRun, string $title, string $summary, AgentToolCall $toolCall): AgentReasoningStep
    {
        return $this->add($agentRun, AgentReasoningStep::TYPE_TOOL_ACTION, $title, $summary, relatedToolCall: $toolCall);
    }

    public function toolResult(AgentRun $agentRun, string $title, string $summary, AgentToolCall $toolCall): AgentReasoningStep
    {
        return $this->add($agentRun, AgentReasoningStep::TYPE_TOOL_RESULT, $title, $summary, relatedToolCall: $toolCall);
    }

    /**
     * @param  array<string, mixed>|null  $evidence
     */
    public function decision(AgentRun $agentRun, string $title, string $summary, ?array $evidence = null, ?float $confidence = null): AgentReasoningStep
    {
        return $this->add($agentRun, AgentReasoningStep::TYPE_DECISION, $title, $summary, evidence: $evidence, confidence: $confidence);
    }

    public function riskCheck(AgentRun $agentRun, string $title, string $summary, ?string $riskLevel = AgentReasoningStep::RISK_LOW): AgentReasoningStep
    {
        return $this->add($agentRun, AgentReasoningStep::TYPE_RISK_CHECK, $title, $summary, riskLevel: $riskLevel);
    }

    public function humanCheckpoint(AgentRun $agentRun, string $title, string $summary, ApprovalRequest|array|null $approvalRequest = null): AgentReasoningStep
    {
        $evidence = null;

        if ($approvalRequest instanceof ApprovalRequest) {
            $evidence = [
                'approval_request_id' => $approvalRequest->id,
                'approval_status' => $approvalRequest->status,
                'approval_type' => $approvalRequest->type,
            ];
        } elseif (is_array($approvalRequest)) {
            $evidence = $approvalRequest;
        }

        return $this->add(
            $agentRun,
            AgentReasoningStep::TYPE_HUMAN_CHECKPOINT,
            $title,
            $summary,
            evidence: $evidence,
            riskLevel: AgentReasoningStep::RISK_BLOCKED,
            requiresHumanApproval: true
        );
    }

    public function finalSummary(AgentRun $agentRun, string $title, string $summary): AgentReasoningStep
    {
        return $this->add($agentRun, AgentReasoningStep::TYPE_FINAL_SUMMARY, $title, $summary);
    }

    /**
     * @param  array<string, mixed>|null  $evidence
     */
    public function add(
        AgentRun $agentRun,
        string $stepType,
        string $title,
        string $summary,
        ?array $evidence = null,
        ?float $confidence = null,
        ?string $riskLevel = null,
        bool $requiresHumanApproval = false,
        ?AgentToolCall $relatedToolCall = null
    ): AgentReasoningStep {
        return $agentRun->reasoningSteps()->create([
            'step_order' => $this->nextStepOrder($agentRun),
            'step_type' => $stepType,
            'title' => $title,
            'summary' => $summary,
            'evidence' => $evidence,
            'confidence' => $confidence,
            'risk_level' => $riskLevel,
            'requires_human_approval' => $requiresHumanApproval,
            'related_tool_call_id' => $relatedToolCall?->id,
        ]);
    }

    private function nextStepOrder(AgentRun $agentRun): int
    {
        return ((int) $agentRun->reasoningSteps()->max('step_order')) + 1;
    }
}
