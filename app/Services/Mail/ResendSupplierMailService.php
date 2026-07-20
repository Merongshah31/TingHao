<?php

namespace App\Services\Mail;

use App\Models\PurchaseOrder;
use App\Models\SupplierEmailDraft;
use App\Models\User;
use App\Services\Agent\AgentWorkflowAuditService;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Resend\Laravel\Facades\Resend;
use Throwable;

class ResendSupplierMailService
{
    public function __construct(private readonly AgentWorkflowAuditService $audit) {}

    /**
     * @return array{enabled: bool, configured: bool, test_mode: bool, provider: string, message: string, recipient: ?string, masked_recipient: ?string}
     */
    public function configuration(?SupplierEmailDraft $draft = null): array
    {
        $enabled = (bool) config('autopilot.real_email_enabled', false);
        $testMode = (bool) config('autopilot.resend_test_mode', true);
        $recipient = $testMode
            ? (string) config('autopilot.resend_test_recipient', '')
            : (string) ($draft?->supplier?->email ?? '');
        $fromAddress = $this->fromAddress($testMode);
        $configured = $enabled
            && filled((string) config('resend.api_key', config('services.resend.key')))
            && filter_var($fromAddress, FILTER_VALIDATE_EMAIL)
            && filter_var($recipient, FILTER_VALIDATE_EMAIL);

        $message = match (true) {
            ! $enabled => 'Real email delivery is disabled.',
            $testMode && $configured => 'Resend test mode is enabled. Only the configured test recipient can receive this email.',
            $configured => 'Resend is configured for explicit admin delivery.',
            default => 'Real email is enabled, but Resend is not fully configured.',
        };

        return [
            'enabled' => $enabled,
            'configured' => $configured,
            'test_mode' => $testMode,
            'provider' => 'resend',
            'message' => $message,
            'recipient' => $recipient ?: null,
            'masked_recipient' => $recipient ? $this->maskEmail($recipient) : null,
        ];
    }

    public function send(SupplierEmailDraft $draft, User $admin): string
    {
        $lock = Cache::lock("supplier-email-draft:{$draft->id}:send-resend", 30);

        if (! $lock->get()) {
            throw new DomainException('This supplier email draft is already being sent. Please wait and refresh.');
        }

        try {
            return $this->sendWhileLocked($draft, $admin);
        } finally {
            optional($lock)->release();
        }
    }

    private function sendWhileLocked(SupplierEmailDraft $draft, User $admin): string
    {
        $draft->refresh()->loadMissing(['purchaseOrder.agentRun', 'supplier']);
        $purchaseOrder = $draft->purchaseOrder;
        $testMode = (bool) config('autopilot.resend_test_mode', true);

        try {
            $this->assertSendable($draft, $admin, $testMode);
            $recipient = $this->recipientFor($draft, $testMode);
            $intendedRecipient = (string) $draft->supplier->email;
            $sentAt = now();
            $response = Resend::emails()->send($this->payloadFor($draft, $recipient, $testMode), [
                'idempotency_key' => "supplier-email-draft-{$draft->id}",
            ]);
            $messageId = (string) ($response->id ?? '');

            if ($messageId === '') {
                throw new DomainException('Resend accepted the request without returning an email ID.');
            }

            DB::transaction(function () use ($draft, $purchaseOrder, $admin, $sentAt, $messageId, $recipient, $intendedRecipient, $testMode): void {
                $this->updateExistingDraftColumns($draft, [
                    'status' => SupplierEmailDraft::STATUS_SENT,
                    'provider' => 'resend',
                    'provider_message_id' => $messageId,
                    'delivery_status' => SupplierEmailDraft::DELIVERY_ACCEPTED,
                    'delivery_provider' => 'resend',
                    'delivery_metadata' => [
                        'attempted_at' => $sentAt->toIso8601String(),
                        'result' => 'accepted_by_resend',
                        'test_mode' => $testMode,
                        'recipient' => $this->maskEmail($recipient),
                        'intended_recipient' => $this->maskEmail($intendedRecipient),
                    ],
                    'sent_at' => $sentAt,
                    'sent_by' => $admin->id,
                    'send_error_category' => null,
                    'last_delivery_attempt_at' => $sentAt,
                ]);

                $purchaseOrder?->update([
                    'status' => PurchaseOrder::STATUS_SENT,
                    'sent_at' => $sentAt,
                    'email_to' => $intendedRecipient,
                ]);
            });

            if ($purchaseOrder) {
                $this->audit->record(
                    $purchaseOrder,
                    'send_supplier_email_resend',
                    [
                        'supplier_email_draft_id' => $draft->id,
                        'approved_by' => $admin->id,
                        'intended_recipient' => $this->maskEmail($intendedRecipient),
                        'actual_recipient' => $this->maskEmail($recipient),
                    ],
                    [
                        'delivery_status' => SupplierEmailDraft::DELIVERY_ACCEPTED,
                        'provider' => 'resend',
                        'provider_message_id' => $messageId,
                        'test_mode' => $testMode,
                        'intended_recipient' => $this->maskEmail($intendedRecipient),
                        'actual_recipient' => $this->maskEmail($recipient),
                    ],
                    'Supplier email sent through Resend',
                    'Approved supplier email was sent through Resend after admin confirmation.'
                );
            }

            return $messageId;
        } catch (DomainException $exception) {
            $this->recordFailure($draft, $purchaseOrder, $this->safeErrorCategory($exception), $exception->getMessage());

            throw $exception;
        } catch (Throwable $exception) {
            $this->recordFailure($draft, $purchaseOrder, class_basename($exception), 'Resend did not accept the supplier email request.');

            throw new DomainException('Resend could not accept the supplier email. Please check configuration and retry.');
        }
    }

    private function assertSendable(SupplierEmailDraft $draft, User $admin, bool $testMode): void
    {
        if (! $admin->isAdmin()) {
            throw new DomainException('Only an admin can send an approved supplier email.');
        }

        if (! (bool) config('autopilot.real_email_enabled', false)) {
            throw new DomainException('Real email delivery is disabled.');
        }

        if ($draft->status === SupplierEmailDraft::STATUS_SENT || $draft->sent_at) {
            throw new DomainException('This supplier email draft has already been sent.');
        }

        if ($draft->status !== SupplierEmailDraft::STATUS_APPROVED) {
            throw new DomainException('The supplier email draft must be approved before Resend delivery.');
        }

        if ($draft->purchaseOrder?->status !== PurchaseOrder::STATUS_APPROVED) {
            throw new DomainException('The linked purchase order must be approved before Resend delivery.');
        }

        if (! $draft->supplier) {
            throw new DomainException('The supplier email draft must be linked to a supplier.');
        }

        if (! filter_var($draft->supplier->email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('The supplier does not have a valid email address.');
        }

        $actualRecipient = $this->recipientFor($draft, $testMode);

        if (! filter_var($actualRecipient, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Resend recipient is not configured correctly.');
        }

        if (! filter_var($this->fromAddress($testMode), FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Resend sender address is not configured.');
        }

        if (! filled((string) config('resend.api_key', config('services.resend.key')))) {
            throw new DomainException('Resend API key is not configured.');
        }

    }

    private function payloadFor(SupplierEmailDraft $draft, string $recipient, bool $testMode): array
    {
        return [
            'from' => $this->fromHeader($testMode),
            'to' => [$recipient],
            'subject' => $draft->subject,
            'html' => $this->renderHtml($draft),
            'text' => $draft->body,
            'tags' => [
                ['name' => 'po_number', 'value' => (string) $draft->purchaseOrder?->po_number],
                ['name' => 'supplier_email_draft_id', 'value' => (string) $draft->id],
                ['name' => 'environment', 'value' => (string) app()->environment()],
            ],
        ];
    }

    private function renderHtml(SupplierEmailDraft $draft): string
    {
        $subject = e($draft->subject);
        $body = nl2br(e($draft->body), false);

        return <<<HTML
<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
<h1 style="font-size: 18px;">{$subject}</h1>
<div>{$body}</div>
</body>
</html>
HTML;
    }

    private function recipientFor(SupplierEmailDraft $draft, bool $testMode): string
    {
        return $testMode
            ? (string) config('autopilot.resend_test_recipient')
            : (string) $draft->supplier?->email;
    }

    private function fromHeader(bool $testMode): string
    {
        return sprintf('%s <%s>', $this->fromName(), $this->fromAddress($testMode));
    }

    private function fromAddress(bool $testMode): string
    {
        return $testMode
            ? 'onboarding@resend.dev'
            : (string) config('autopilot.resend_from_address', '');
    }

    private function fromName(): string
    {
        return (string) config('autopilot.resend_from_name', 'Bakery TingHao Procurement');
    }

    private function recordFailure(SupplierEmailDraft $draft, ?PurchaseOrder $purchaseOrder, string $category, string $message): void
    {
        if ($draft->status === SupplierEmailDraft::STATUS_SENT) {
            return;
        }

        $attemptedAt = now();

        $this->updateExistingDraftColumns($draft, [
            'provider' => 'resend',
            'delivery_status' => SupplierEmailDraft::DELIVERY_FAILED,
            'delivery_provider' => 'resend',
            'delivery_metadata' => [
                'attempted_at' => $attemptedAt->toIso8601String(),
                'error_category' => $category,
                'result' => 'not_accepted_by_resend',
            ],
            'send_error_category' => $category,
            'last_delivery_attempt_at' => $attemptedAt,
        ]);

        if ($purchaseOrder) {
            $this->audit->record(
                $purchaseOrder,
                'send_supplier_email_resend',
                ['supplier_email_draft_id' => $draft->id],
                ['delivery_status' => SupplierEmailDraft::DELIVERY_FAILED, 'provider' => 'resend', 'error_category' => $category],
                'Supplier email Resend delivery failed',
                $message,
                'failed'
            );
        }
    }

    private function safeErrorCategory(Throwable $exception): string
    {
        return str($exception->getMessage())->lower()->slug('_')->limit(80, '')->toString() ?: class_basename($exception);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($local === '' || $domain === '') {
            return 'invalid email';
        }

        $visible = str($local)->substr(0, min(2, strlen($local)))->toString();

        return $visible.'***@'.$domain;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateExistingDraftColumns(SupplierEmailDraft $draft, array $attributes): void
    {
        $draft->update(array_filter(
            $attributes,
            fn (mixed $value, string $column): bool => Schema::hasColumn('supplier_email_drafts', $column),
            ARRAY_FILTER_USE_BOTH
        ));
    }
}
