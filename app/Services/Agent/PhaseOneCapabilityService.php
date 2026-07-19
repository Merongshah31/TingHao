<?php

namespace App\Services\Agent;

use App\Models\AgentRun;
use App\Models\AgentToolCall;
use App\Models\PurchaseOrder;
use App\Models\SupplierEmailDraft;
use App\Models\User;

class PhaseOneCapabilityService
{
    public function __construct(private readonly SupplierEmailDeliveryService $emailDelivery) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function map(?User $viewer = null): array
    {
        $limitToViewer = $viewer && ! $viewer->isAdmin();
        $scanRun = AgentRun::query()
            ->when($limitToViewer, fn ($query) => $query->where('user_id', $viewer->id))
            ->where('input_type', 'autopilot_inventory_scan')
            ->latest()
            ->first();
        $predictionTool = AgentToolCall::query()
            ->when($limitToViewer, fn ($query) => $query->whereHas('agentRun', fn ($runQuery) => $runQuery->where('user_id', $viewer->id)))
            ->where('tool_name', 'predict_stock_action')
            ->latest()
            ->first();
        $comparisonTool = AgentToolCall::query()
            ->when($limitToViewer, fn ($query) => $query->whereHas('agentRun', fn ($runQuery) => $runQuery->where('user_id', $viewer->id)))
            ->whereIn('tool_name', ['compare_suppliers', 'select_supplier'])
            ->latest()
            ->first();
        $purchaseOrder = PurchaseOrder::query()
            ->when($limitToViewer, fn ($query) => $query->where('requested_by', $viewer->id))
            ->whereNotNull('agent_run_id')
            ->latest()
            ->first();
        $emailDraft = SupplierEmailDraft::query()
            ->when($limitToViewer, fn ($query) => $query->whereHas('purchaseOrder', fn ($poQuery) => $poQuery->where('requested_by', $viewer->id)))
            ->whereNotNull('agent_run_id')
            ->latest()
            ->first();
        $emailConfiguration = $this->emailDelivery->configuration();
        $auditRun = AgentRun::query()
            ->when($limitToViewer, fn ($query) => $query->where('user_id', $viewer->id))
            ->latest()
            ->first();

        return [
            $this->capability('observe', 'Observe', 'Inventory monitoring',
                $scanRun ? $this->runStatus($scanRun) : 'available',
                $scanRun ? 'Latest scheduled scan: Agent Run #'.$scanRun->id.'.' : 'Command is available and waiting for its first scheduled or manual scan.',
                route('agent.index', $scanRun ? ['run' => $scanRun->id] : [])),
            $this->capability('predict', 'Predict', 'FastAPI demand forecasting',
                $predictionTool ? $this->toolStatus($predictionTool) : 'available',
                $predictionTool ? 'A stock prediction was recorded in Agent Run #'.$predictionTool->agent_run_id.'.' : 'FastAPI prediction is available through Stock Planner and the scheduled scan.',
                route('stock-planner.index')),
            $this->capability('decide', 'Decide', 'Reorder quantity and supplier comparison',
                $comparisonTool ? $this->toolStatus($comparisonTool) : 'available',
                $comparisonTool ? 'Supplier decision evidence exists in Agent Run #'.$comparisonTool->agent_run_id.'.' : 'Waiting for an add-stock prediction with an eligible supplier.',
                route('stock-planner.index')),
            $this->capability('approve', 'Human Approve', 'Admin approval checkpoint',
                $this->approvalStatus($purchaseOrder),
                $this->approvalDetail($purchaseOrder),
                $purchaseOrder ? route('purchase-orders.show', $purchaseOrder) : route('purchase-orders.index'),
                true),
            $this->capability('act', 'Act', 'PO draft and approved Gmail delivery',
                $this->actStatus($purchaseOrder, $emailDraft, $emailConfiguration),
                $this->actDetail($purchaseOrder, $emailDraft, $emailConfiguration),
                $emailDraft ? route('supplier-email-drafts.show', $emailDraft) : route('purchase-orders.index')),
            $this->capability('verify', 'Verify', 'Supplier confirmation and goods receiving',
                $this->verifyStatus($purchaseOrder),
                $this->verifyDetail($purchaseOrder),
                $purchaseOrder ? route('purchase-orders.show', $purchaseOrder) : route('purchase-orders.index')),
            $this->capability('audit', 'Audit', 'Agent runs, tool calls and decision summaries',
                $auditRun ? $this->runStatus($auditRun) : 'available',
                $auditRun ? 'Latest auditable mission is Agent Run #'.$auditRun->id.'.' : 'Audit storage is available and waiting for the first mission.',
                route('agent.index', $auditRun ? ['run' => $auditRun->id] : [])),
        ];
    }

    /** @return array<string, mixed> */
    private function capability(string $key, string $label, string $summary, string $status, string $detail, string $url, bool $human = false): array
    {
        return compact('key', 'label', 'summary', 'status', 'detail', 'url', 'human');
    }

    private function runStatus(AgentRun $run): string
    {
        return match ($run->status) {
            AgentRun::STATUS_COMPLETED => 'completed',
            AgentRun::STATUS_FAILED => 'failed',
            AgentRun::STATUS_NEEDS_APPROVAL => 'waiting',
            default => 'available',
        };
    }

    private function toolStatus(AgentToolCall $tool): string
    {
        return in_array($tool->status, ['failed', 'blocked'], true) ? 'failed' : 'completed';
    }

    private function approvalStatus(?PurchaseOrder $purchaseOrder): string
    {
        if (! $purchaseOrder) {
            return 'available';
        }

        return match ($purchaseOrder->status) {
            PurchaseOrder::STATUS_PENDING_APPROVAL => 'waiting',
            PurchaseOrder::STATUS_REJECTED => 'failed',
            default => in_array($purchaseOrder->status, [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_CONFIRMED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED], true)
                ? 'completed'
                : 'available',
        };
    }

    private function approvalDetail(?PurchaseOrder $purchaseOrder): string
    {
        return match ($this->approvalStatus($purchaseOrder)) {
            'waiting' => $purchaseOrder->po_number.' is waiting for admin approval.',
            'completed' => $purchaseOrder->po_number.' passed the admin approval checkpoint.',
            'failed' => $purchaseOrder->po_number.' was rejected by an admin.',
            default => 'Waiting for an approval-gated autopilot PO draft.',
        };
    }

    /** @param array<string, mixed> $configuration */
    private function actStatus(?PurchaseOrder $purchaseOrder, ?SupplierEmailDraft $emailDraft, array $configuration): string
    {
        if ($emailDraft?->delivery_status === SupplierEmailDraft::DELIVERY_FAILED) {
            return 'failed';
        }
        if ($emailDraft?->delivery_status === SupplierEmailDraft::DELIVERY_DELIVERED) {
            return 'completed';
        }
        if (! $configuration['enabled'] || ! $configuration['configured']) {
            return 'not-configured';
        }
        if ($purchaseOrder || $emailDraft) {
            return 'waiting';
        }

        return 'available';
    }

    /** @param array<string, mixed> $configuration */
    private function actDetail(?PurchaseOrder $purchaseOrder, ?SupplierEmailDraft $emailDraft, array $configuration): string
    {
        return match ($this->actStatus($purchaseOrder, $emailDraft, $configuration)) {
            'completed' => 'Approved supplier email delivery was accepted by configured Gmail SMTP.',
            'failed' => 'The latest Gmail delivery attempt failed and remains available for admin retry.',
            'not-configured' => $configuration['message'],
            'waiting' => 'A PO or supplier email draft is waiting for the next explicit admin action.',
            default => 'Waiting for a purchase order draft and approved supplier email.',
        };
    }

    private function verifyStatus(?PurchaseOrder $purchaseOrder): string
    {
        if (! $purchaseOrder) {
            return 'available';
        }

        return match ($purchaseOrder->status) {
            PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED => 'completed',
            PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_CONFIRMED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'waiting',
            PurchaseOrder::STATUS_REJECTED, PurchaseOrder::STATUS_CANCELLED => 'failed',
            default => 'available',
        };
    }

    private function verifyDetail(?PurchaseOrder $purchaseOrder): string
    {
        return match ($this->verifyStatus($purchaseOrder)) {
            'completed' => $purchaseOrder->po_number.' has recorded goods receiving evidence.',
            'waiting' => $purchaseOrder->po_number.' is waiting for supplier confirmation or complete goods receiving.',
            'failed' => $purchaseOrder->po_number.' ended before verification was completed.',
            default => 'Waiting for supplier confirmation and a receiving record.',
        };
    }
}
