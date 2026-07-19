<?php

namespace App\Services\Agent;

use App\Models\AgentToolCall;
use App\Models\PurchaseOrder;

class AgentWorkflowAuditService
{
    public function __construct(private readonly ReasoningActivityService $reasoningActivity) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    public function record(
        PurchaseOrder $purchaseOrder,
        string $toolName,
        array $input,
        array $output,
        string $title,
        string $summary,
        string $status = 'completed',
    ): ?AgentToolCall {
        if (! $purchaseOrder->agent_run_id) {
            return null;
        }

        $toolCall = AgentToolCall::create([
            'agent_run_id' => $purchaseOrder->agent_run_id,
            'tool_name' => $toolName,
            'input_payload' => $input,
            'output_payload' => $output,
            'status' => $status,
        ]);

        $purchaseOrder->loadMissing('agentRun');

        if ($purchaseOrder->agentRun) {
            $this->reasoningActivity->toolResult($purchaseOrder->agentRun, $title, $summary, $toolCall);
        }

        return $toolCall;
    }
}
