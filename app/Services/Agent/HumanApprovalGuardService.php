<?php

namespace App\Services\Agent;

use App\Models\AgentReasoningStep;
use App\Models\AgentRun;
use App\Models\User;

class HumanApprovalGuardService
{
    public const ACTION_PURCHASE_ORDER_APPROVAL = 'purchase_order_approval';

    public const ACTION_SUPPLIER_EMAIL_APPROVAL = 'supplier_email_approval';

    public const ACTION_MARK_EMAIL_SENT = 'mark_email_sent';

    public const ACTION_EXPIRY_RECOMMENDATION_COMPLETION = 'expiry_recommendation_completion';

    public const ACTION_EXPIRED_STOCK_REMOVAL = 'expired_stock_removal';

    public const ACTION_STOCK_QUANTITY_CHANGE = 'stock_quantity_change';

    public const ACTION_PURCHASE_ORDER_RECEIPT = 'purchase_order_receipt';

    public function canCreateDraftAction(?string $actionType = null): bool
    {
        $allowed = [
            'purchase_order_draft',
            'supplier_email_draft',
            'expiry_loss_recommendation',
        ];

        return $actionType === null || in_array($actionType, $allowed, true);
    }

    public function requiresAdminApproval(string $actionType): bool
    {
        return in_array($actionType, [
            self::ACTION_PURCHASE_ORDER_APPROVAL,
            self::ACTION_SUPPLIER_EMAIL_APPROVAL,
            self::ACTION_MARK_EMAIL_SENT,
            self::ACTION_EXPIRY_RECOMMENDATION_COMPLETION,
            self::ACTION_EXPIRED_STOCK_REMOVAL,
            self::ACTION_STOCK_QUANTITY_CHANGE,
            self::ACTION_PURCHASE_ORDER_RECEIPT,
        ], true);
    }

    public function assertAdminCanApprove(User $user, string $actionType): void
    {
        abort_unless($this->requiresAdminApproval($actionType), 422);
        abort_unless($user->isAdmin(), 403);
    }

    public function blockAutonomousExecution(string $actionType, ?AgentRun $agentRun = null, ?ReasoningActivityService $reasoning = null): void
    {
        if ($agentRun && $reasoning) {
            $reasoning->humanCheckpoint($agentRun, 'Human approval required', 'This action requires admin approval before execution.', [
                'action_type' => $actionType,
                'risk_level' => AgentReasoningStep::RISK_BLOCKED,
            ]);
        }

        abort(403, 'This action requires admin approval before execution.');
    }
}
