<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\SupplierEmailDraft;
use App\Services\Agent\HumanApprovalGuardService;
use App\Services\Agent\ReasoningActivityService;
use App\Services\Agent\SupplierEmailDeliveryService;
use App\Services\Agent\SupplierEmailDraftService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SupplierEmailDraftController extends Controller
{
    public function show(Request $request, SupplierEmailDraft $supplierEmailDraft, SupplierEmailDeliveryService $deliveryService): View
    {
        $supplierEmailDraft->load([
            'purchaseOrder.requestedBy',
            'purchaseOrder.items.ingredient',
            'supplier',
            'approvedBy',
            'agentRun.reasoningSteps.relatedToolCall',
        ]);

        abort_if(
            ! $request->user()->isAdmin()
            && $supplierEmailDraft->purchaseOrder->requested_by !== $request->user()->id,
            403
        );

        return view('supplier-email-drafts.show', [
            'title' => 'Ting Hao | Supplier Email Draft',
            'supplierEmailDraft' => $supplierEmailDraft,
            'purchaseOrder' => $supplierEmailDraft->purchaseOrder,
            'emailDelivery' => $deliveryService->configuration(),
        ]);
    }

    public function update(Request $request, SupplierEmailDraft $supplierEmailDraft, SupplierEmailDraftService $draftService): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless(in_array($supplierEmailDraft->status, [SupplierEmailDraft::STATUS_DRAFT, SupplierEmailDraft::STATUS_APPROVED], true), 422);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);
        $wasApproved = $supplierEmailDraft->status === SupplierEmailDraft::STATUS_APPROVED;

        $supplierEmailDraft->update($this->existingDraftColumns([
            ...$data,
            'status' => SupplierEmailDraft::STATUS_DRAFT,
            'approved_by' => null,
            'approved_at' => null,
            'delivery_status' => null,
            'delivery_provider' => null,
            'delivery_metadata' => null,
            'last_delivery_attempt_at' => null,
        ]));

        $draftService->logToolCallForDraft($supplierEmailDraft, 'edit_supplier_email_draft', [
            'draft_id' => $supplierEmailDraft->id,
        ], [
            'status' => SupplierEmailDraft::STATUS_DRAFT,
            'reapproval_required' => $wasApproved,
            'edited_by' => $request->user()->id,
        ]);

        return back()->with('status', $wasApproved
            ? 'Supplier email draft updated. Admin approval is required again before sending.'
            : 'Supplier email draft updated.');
    }

    public function generate(Request $request, PurchaseOrder $purchaseOrder, SupplierEmailDraftService $draftService): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        try {
            $draft = $draftService->generate($purchaseOrder);
        } catch (DomainException $exception) {
            return redirect()
                ->route('purchase-orders.show', $purchaseOrder)
                ->withErrors(['supplier_email_draft' => $exception->getMessage()]);
        }

        return redirect()
            ->route('supplier-email-drafts.show', $draft)
            ->with('status', $draft->wasRecentlyCreated ? 'Supplier email draft generated for admin review.' : 'Existing supplier email draft opened for review.');
    }

    public function regenerate(Request $request, SupplierEmailDraft $supplierEmailDraft, SupplierEmailDraftService $draftService): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($supplierEmailDraft->status === SupplierEmailDraft::STATUS_DRAFT, 422);

        try {
            $draft = $draftService->regenerate($supplierEmailDraft);
        } catch (DomainException $exception) {
            return back()->withErrors(['supplier_email_draft' => $exception->getMessage()]);
        }

        return redirect()
            ->route('supplier-email-drafts.show', $draft)
            ->with('status', 'Supplier email draft regenerated for admin review.');
    }

    public function approve(Request $request, SupplierEmailDraft $supplierEmailDraft, SupplierEmailDraftService $draftService, HumanApprovalGuardService $guard, ReasoningActivityService $reasoningActivity): RedirectResponse
    {
        $guard->assertAdminCanApprove($request->user(), HumanApprovalGuardService::ACTION_SUPPLIER_EMAIL_APPROVAL);
        abort_unless($supplierEmailDraft->status === SupplierEmailDraft::STATUS_DRAFT, 422);

        $approvalAttributes = $this->existingDraftColumns([
            'status' => SupplierEmailDraft::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $supplierEmailDraft->update($approvalAttributes);

        $draftService->logToolCallForDraft($supplierEmailDraft, 'approve_supplier_email_draft', [
            'draft_id' => $supplierEmailDraft->id,
        ], [
            'status' => SupplierEmailDraft::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
        ]);
        if ($supplierEmailDraft->agentRun) {
            $reasoningActivity->humanCheckpoint($supplierEmailDraft->agentRun, 'Supplier email draft approved', 'Admin approved the draft content. The agent did not approve it autonomously.', [
                'supplier_email_draft_id' => $supplierEmailDraft->id,
                'approved_by' => $request->user()->id,
            ]);
        }

        return back()->with('status', 'Supplier email draft approved.');
    }

    public function markSent(Request $request, SupplierEmailDraft $supplierEmailDraft, SupplierEmailDraftService $draftService, HumanApprovalGuardService $guard, ReasoningActivityService $reasoningActivity): RedirectResponse
    {
        $guard->assertAdminCanApprove($request->user(), HumanApprovalGuardService::ACTION_MARK_EMAIL_SENT);
        abort_unless($supplierEmailDraft->status === SupplierEmailDraft::STATUS_APPROVED, 422);

        if (config('autopilot.real_email_enabled', false)) {
            return back()->withErrors(['supplier_email_draft' => 'Demo-safe Mark Email as Sent is unavailable while real email delivery is enabled. Use Send via Gmail.']);
        }

        DB::transaction(function () use ($supplierEmailDraft): void {
            $supplierEmailDraft->update($this->existingDraftColumns([
                'status' => SupplierEmailDraft::STATUS_SENT,
                'sent_at' => now(),
                'delivery_status' => SupplierEmailDraft::DELIVERY_DEMO_MARKED_SENT,
                'delivery_provider' => 'demo_safe',
                'delivery_metadata' => [
                    'result' => 'marked_sent_without_delivery',
                    'real_email_sent' => false,
                ],
                'last_delivery_attempt_at' => now(),
            ]));

            $supplierEmailDraft->purchaseOrder()->update([
                'status' => PurchaseOrder::STATUS_SENT,
                'sent_at' => now(),
                'email_to' => $supplierEmailDraft->supplier?->email,
            ]);
        });

        $draftService->logToolCallForDraft($supplierEmailDraft->fresh(['purchaseOrder']), 'mark_supplier_email_sent', [
            'draft_id' => $supplierEmailDraft->id,
            'demo_safe' => true,
        ], [
            'status' => SupplierEmailDraft::STATUS_SENT,
            'purchase_order_status' => PurchaseOrder::STATUS_SENT,
            'sent_at' => $supplierEmailDraft->fresh()->sent_at?->toDateTimeString(),
        ]);
        $draftService->logToolCallForDraft($supplierEmailDraft->fresh(['purchaseOrder']), 'mark_email_sent', [
            'draft_id' => $supplierEmailDraft->id,
            'demo_safe' => true,
        ], [
            'status' => SupplierEmailDraft::STATUS_SENT,
            'purchase_order_status' => PurchaseOrder::STATUS_SENT,
            'sent_at' => $supplierEmailDraft->fresh()->sent_at?->toDateTimeString(),
        ]);
        if ($supplierEmailDraft->agentRun) {
            $reasoningActivity->humanCheckpoint($supplierEmailDraft->agentRun, 'Supplier email marked sent by admin', 'Admin marked the supplier email sent for demo. No real email was sent and the agent did not perform this action autonomously.', [
                'supplier_email_draft_id' => $supplierEmailDraft->id,
                'purchase_order_id' => $supplierEmailDraft->purchase_order_id,
                'demo_safe' => true,
            ]);
        }

        return back()->with('status', 'Supplier email marked as sent for demo. No real email was sent.');
    }

    public function sendViaGmail(Request $request, SupplierEmailDraft $supplierEmailDraft, SupplierEmailDeliveryService $deliveryService, HumanApprovalGuardService $guard): RedirectResponse
    {
        $guard->assertAdminCanApprove($request->user(), HumanApprovalGuardService::ACTION_MARK_EMAIL_SENT);

        try {
            $deliveryService->send($supplierEmailDraft, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['supplier_email_draft' => $exception->getMessage()]);
        }

        return back()->with('status', 'Supplier email sent through configured Gmail SMTP. Delivery evidence was added to the audit trail.');
    }

    /**
     * Keep legacy deployments usable while the nullable delivery-audit migration is pending.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function existingDraftColumns(array $attributes): array
    {
        return array_filter(
            $attributes,
            fn (mixed $value, string $column): bool => Schema::hasColumn('supplier_email_drafts', $column),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
