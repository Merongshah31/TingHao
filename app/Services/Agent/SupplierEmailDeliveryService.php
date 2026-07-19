<?php

namespace App\Services\Agent;

use App\Mail\SupplierEmailDraftMail;
use App\Models\PurchaseOrder;
use App\Models\SupplierEmailDraft;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SupplierEmailDeliveryService
{
    public function __construct(private readonly AgentWorkflowAuditService $audit) {}

    /**
     * @return array{enabled: bool, configured: bool, provider: string, message: string}
     */
    public function configuration(): array
    {
        $enabled = (bool) config('autopilot.real_email_enabled', false);
        $mailer = (string) config('mail.default', 'log');
        $host = (string) config('mail.mailers.smtp.host', '');
        $username = (string) config('mail.mailers.smtp.username', '');
        $password = (string) config('mail.mailers.smtp.password', '');
        $from = (string) config('mail.from.address', '');
        $gmailHost = str_contains(strtolower($host), 'gmail');
        $configured = $enabled
            && $mailer === 'smtp'
            && $gmailHost
            && filled($username)
            && filled($password)
            && filter_var($from, FILTER_VALIDATE_EMAIL);

        $message = match (true) {
            ! $enabled => 'Real Gmail delivery is disabled. Use the demo-safe Mark Email as Sent action.',
            $configured => 'Gmail SMTP is configured for explicit admin delivery.',
            default => 'Real email is enabled, but Gmail SMTP is not fully configured. Check the server mail environment.',
        };

        return [
            'enabled' => $enabled,
            'configured' => $configured,
            'provider' => 'gmail_smtp',
            'message' => $message,
        ];
    }

    public function send(SupplierEmailDraft $draft, User $admin): SupplierEmailDraft
    {
        $draft->loadMissing(['purchaseOrder.agentRun', 'supplier']);
        $configuration = $this->configuration();

        if (! $admin->isAdmin()) {
            throw new DomainException('Only an admin can send an approved supplier email.');
        }

        if ($draft->status !== SupplierEmailDraft::STATUS_APPROVED || $draft->purchaseOrder?->status !== PurchaseOrder::STATUS_APPROVED) {
            throw new DomainException('The purchase order and supplier email draft must both be approved before Gmail delivery.');
        }

        if (! $configuration['configured']) {
            $this->recordFailure($draft, 'gmail_not_configured', $configuration['message']);

            throw new DomainException($configuration['message']);
        }

        if (! filter_var($draft->supplier?->email, FILTER_VALIDATE_EMAIL)) {
            $this->recordFailure($draft, 'supplier_email_missing', 'The supplier does not have a valid email address.');

            throw new DomainException('The supplier does not have a valid email address.');
        }

        try {
            $sentMessage = Mail::to($draft->supplier->email)->send(new SupplierEmailDraftMail($draft));
            $sentAt = now();
            $messageId = $sentMessage && method_exists($sentMessage, 'getMessageId')
                ? $sentMessage->getMessageId()
                : null;

            DB::transaction(function () use ($draft, $sentAt, $messageId): void {
                $this->updateExistingDraftColumns($draft, [
                    'status' => SupplierEmailDraft::STATUS_SENT,
                    'sent_at' => $sentAt,
                    'delivery_status' => SupplierEmailDraft::DELIVERY_DELIVERED,
                    'delivery_provider' => 'gmail_smtp',
                    'delivery_metadata' => array_filter([
                        'attempted_at' => $sentAt->toIso8601String(),
                        'provider_message_id' => $messageId,
                        'result' => 'accepted_by_mail_transport',
                    ]),
                    'last_delivery_attempt_at' => $sentAt,
                ]);

                $draft->purchaseOrder()->update([
                    'status' => PurchaseOrder::STATUS_SENT,
                    'sent_at' => $sentAt,
                    'email_to' => $draft->supplier?->email,
                ]);
            });

            $this->audit->record(
                $draft->purchaseOrder,
                'send_supplier_email_gmail',
                ['supplier_email_draft_id' => $draft->id, 'approved_by' => $admin->id],
                ['delivery_status' => SupplierEmailDraft::DELIVERY_DELIVERED, 'provider' => 'gmail_smtp', 'sent_at' => $sentAt->toIso8601String()],
                'Supplier email delivered through Gmail',
                'Admin explicitly sent the approved supplier email through configured Gmail SMTP.'
            );

            return $draft->fresh(['purchaseOrder', 'supplier', 'agentRun']);
        } catch (DomainException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->recordFailure($draft, class_basename($exception), 'Gmail delivery failed before the mail transport confirmed acceptance.');

            throw new DomainException('Gmail delivery failed. Review the server mail configuration and retry.');
        }
    }

    private function recordFailure(SupplierEmailDraft $draft, string $code, string $message): void
    {
        $attemptedAt = now();

        $this->updateExistingDraftColumns($draft, [
            'delivery_status' => SupplierEmailDraft::DELIVERY_FAILED,
            'delivery_provider' => 'gmail_smtp',
            'delivery_metadata' => [
                'attempted_at' => $attemptedAt->toIso8601String(),
                'error_code' => $code,
                'result' => 'not_delivered',
            ],
            'last_delivery_attempt_at' => $attemptedAt,
        ]);

        $this->audit->record(
            $draft->purchaseOrder,
            'send_supplier_email_gmail',
            ['supplier_email_draft_id' => $draft->id],
            ['delivery_status' => SupplierEmailDraft::DELIVERY_FAILED, 'provider' => 'gmail_smtp', 'error_code' => $code],
            'Supplier email delivery failed',
            $message,
            'failed'
        );
    }

    /**
     * Delivery audit fields were introduced after the original supplier email draft table.
     * Do not block the core email workflow while a deployment is waiting for that migration.
     *
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
