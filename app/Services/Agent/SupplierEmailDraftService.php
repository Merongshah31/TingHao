<?php

namespace App\Services\Agent;

use App\Models\AgentToolCall;
use App\Models\PurchaseOrder;
use App\Models\SupplierEmailDraft;
use App\Services\Qwen\QwenClient;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SupplierEmailDraftService
{
    public function __construct(
        private readonly QwenClient $qwenClient,
        private readonly ReasoningActivityService $reasoningActivity,
    ) {
    }

    public function generate(PurchaseOrder $purchaseOrder): SupplierEmailDraft
    {
        $existingDraft = $purchaseOrder->latestSupplierEmailDraft()->first();

        if ($existingDraft) {
            return $existingDraft->load(['purchaseOrder', 'supplier', 'agentRun']);
        }

        return $this->createOrUpdateDraft($purchaseOrder);
    }

    public function regenerate(SupplierEmailDraft $draft): SupplierEmailDraft
    {
        return $this->createOrUpdateDraft($draft->purchaseOrder()->firstOrFail(), $draft);
    }

    private function createOrUpdateDraft(PurchaseOrder $purchaseOrder, ?SupplierEmailDraft $existingDraft = null): SupplierEmailDraft
    {
        $purchaseOrder->load(['supplier', 'items.ingredient', 'agentRun']);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_APPROVED) {
            throw new DomainException('Purchase order must be approved before generating supplier email draft.');
        }

        if (! $purchaseOrder->supplier) {
            throw new DomainException('Supplier information is missing. Please assign a supplier before generating email draft.');
        }

        if (! $purchaseOrder->supplier->email && ! $purchaseOrder->supplier->phone && ! $purchaseOrder->supplier->contact_person) {
            throw new DomainException('Supplier contact information is missing. Please add supplier email or contact details before generating email draft.');
        }

        if ($purchaseOrder->items->isEmpty()) {
            throw new DomainException('Purchase order has no items. Email draft cannot be generated.');
        }

        $payload = $this->payload($purchaseOrder);
        $this->logToolCall($purchaseOrder, 'approved_purchase_order_detected', [
            'purchase_order_id' => $purchaseOrder->id,
        ], [
            'status' => $purchaseOrder->status,
            'po_number' => $purchaseOrder->po_number,
        ]);
        $contextToolCall = $this->logToolCall($purchaseOrder, 'build_supplier_email_context', [
            'purchase_order_id' => $purchaseOrder->id,
        ], [
            'context' => $payload,
        ]);
        if ($purchaseOrder->agentRun && $contextToolCall) {
            $this->reasoningActivity->toolAction($purchaseOrder->agentRun, 'Supplier email context prepared', 'Compact purchase order, supplier, item, and business context was prepared for Qwen.', $contextToolCall);
        }

        $response = $this->qwenClient->generateJson($this->systemPrompt(), json_encode($payload), [
            'max_tokens' => (int) config('qwen.max_tokens.email_draft', config('qwen.max_tokens.email', 500)),
            'temperature' => (float) config('qwen.temperature', 0.2),
        ]);
        $qwenToolCall = $this->logToolCall($purchaseOrder, 'call_qwen_email_draft', [
            'purchase_order_id' => $purchaseOrder->id,
            'payload_summary' => [
                'po_number' => $payload['purchase_order']['po_number'],
                'supplier_name' => $payload['supplier']['name'],
                'item_count' => count($payload['items']),
            ],
        ], [
            'mocked' => $response['mocked'],
            'error' => $response['error'],
            'metadata' => $response['metadata'] ?? [],
        ], $response['error'] ? 'failed' : 'completed');

        if ($purchaseOrder->agentRun && $qwenToolCall) {
            $this->reasoningActivity->toolResult($purchaseOrder->agentRun, 'Qwen email draft response', $response['error']
                ? 'Qwen email drafting was unavailable, so no draft was saved.'
                : 'Qwen returned a supplier email draft response for admin review.', $qwenToolCall);
        }

        if (! $response['mocked'] && ($response['error'] || $response['json'] === [])) {
            throw new DomainException('Qwen email drafting is temporarily unavailable. You can try again later.');
        }

        $draft = $this->normalize($response['json'], $purchaseOrder);

        if ($response['mocked'] || $response['json'] === []) {
            $draft = $this->fallbackDraft($purchaseOrder);
        }

        $supplierEmailDraft = DB::transaction(function () use ($purchaseOrder, $existingDraft, $draft, $response): SupplierEmailDraft {
            $attributes = [
                'supplier_id' => $purchaseOrder->supplier_id,
                'agent_run_id' => $purchaseOrder->agent_run_id,
                'subject' => $draft['subject'],
                'body' => $draft['body'],
                'status' => SupplierEmailDraft::STATUS_DRAFT,
                'approved_by' => null,
                'approved_at' => null,
                'sent_at' => null,
                'qwen_model' => data_get($response, 'metadata.model'),
                'qwen_metadata' => $response['metadata'] ?? [],
            ];
            $attributes = $this->onlyExistingDraftColumns($attributes);

            if ($existingDraft) {
                $existingDraft->update($attributes);

                return $existingDraft->fresh();
            }

            return $purchaseOrder->supplierEmailDrafts()->create($attributes);
        });

        $toolCall = $this->logToolCall($purchaseOrder, 'generate_supplier_email_draft', $payload, [
            'draft_id' => $supplierEmailDraft->id,
            'subject' => $supplierEmailDraft->subject,
            'qwen_mocked' => $response['mocked'],
            'qwen_error' => $response['error'],
            'qwen_metadata' => $response['metadata'] ?? [],
        ]);
        $saveToolCall = $this->logToolCall($purchaseOrder, 'save_supplier_email_draft', [
            'purchase_order_id' => $purchaseOrder->id,
            'draft_id' => $supplierEmailDraft->id,
            'regenerated' => $existingDraft !== null,
        ], [
            'status' => $supplierEmailDraft->status,
            'qwen_model' => $supplierEmailDraft->qwen_model,
        ]);
        $waitToolCall = $this->logToolCall($purchaseOrder, 'wait_for_admin_email_approval', [
            'draft_id' => $supplierEmailDraft->id,
        ], [
            'status' => $supplierEmailDraft->status,
            'requires_admin' => true,
        ]);
        if ($purchaseOrder->agentRun) {
            if ($toolCall) {
                $this->reasoningActivity->toolAction($purchaseOrder->agentRun, 'Supplier email draft generated', 'Qwen or mock mode generated a supplier email draft from approved PO data.', $toolCall);
            }
            if ($saveToolCall) {
                $this->reasoningActivity->toolResult($purchaseOrder->agentRun, 'Supplier email draft saved', 'The supplier email draft was saved for admin review. No real email was sent.', $saveToolCall);
            }
            if ($waitToolCall) {
                $this->reasoningActivity->toolResult($purchaseOrder->agentRun, 'Waiting for admin email approval', 'Admin must approve the supplier email draft before it can be marked sent.', $waitToolCall);
            }
            $this->reasoningActivity->humanCheckpoint($purchaseOrder->agentRun, 'Email approval required', 'Admin must approve the supplier email draft before it can be marked sent.', [
                'supplier_email_draft_id' => $supplierEmailDraft->id,
                'draft_status' => $supplierEmailDraft->status,
            ]);
        }

        return $supplierEmailDraft->load(['purchaseOrder', 'supplier', 'agentRun']);
    }

    public function logToolCallForDraft(SupplierEmailDraft $draft, string $toolName, array $input, array $output, string $status = 'completed'): void
    {
        $draft->loadMissing('purchaseOrder');
        $toolCall = $this->logToolCall($draft->purchaseOrder, $toolName, $input, $output, $status);

        if (! $draft->purchaseOrder?->agentRun) {
            return;
        }

        $summary = match ($toolName) {
            'approve_supplier_email_draft' => 'Admin approved the supplier email draft for the next human-controlled step.',
            'mark_supplier_email_sent' => 'Admin marked the supplier email sent for demo. No real email was sent.',
            'mark_email_sent' => 'Admin marked the supplier email sent for demo. No real email was sent.',
            default => 'Supplier email workflow action was recorded.',
        };

        if ($toolCall) {
            $this->reasoningActivity->toolResult($draft->purchaseOrder->agentRun, str_replace('_', ' ', $toolName), $summary, $toolCall);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PurchaseOrder $purchaseOrder): array
    {
        return [
            'purchase_order' => [
                'po_number' => $purchaseOrder->po_number,
                'status' => $purchaseOrder->status,
                'source' => $purchaseOrder->agentRun?->input_type === 'stock_prediction_restock' ? 'stock_prediction' : 'agent_or_manual',
                'notes' => $purchaseOrder->notes,
            ],
            'supplier' => [
                'name' => $purchaseOrder->supplier?->name,
                'email' => $purchaseOrder->supplier?->email,
                'phone' => $purchaseOrder->supplier?->phone,
                'contact_person' => $purchaseOrder->supplier?->contact_person,
            ],
            'items' => $purchaseOrder->items
                ->map(fn ($item): array => [
                    'name' => $item->ingredient?->name ?? $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit' => $item->unit,
                ])
                ->values()
                ->all(),
            'business_context' => $this->businessContext($purchaseOrder),
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You write professional supplier email drafts for Ting Hao Bakery.
Use only the purchase order and supplier data provided.
Return JSON only.
No markdown.
No chain-of-thought.
Do not invent prices, delivery dates, discounts, or supplier promises.
Do not promise payment terms unless provided.
Ask the supplier to confirm availability and earliest delivery date.
Keep the tone professional, simple, concise, and Malaysian SME-friendly.
Return only JSON: {"subject":"...","body":"..."}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{subject: string, body: string}
     */
    private function normalize(array $json, PurchaseOrder $purchaseOrder): array
    {
        $fallback = $this->fallbackDraft($purchaseOrder);

        $subject = filled($json['subject'] ?? null) ? trim((string) $json['subject']) : $fallback['subject'];
        $body = filled($json['body'] ?? null) ? trim((string) $json['body']) : $fallback['body'];

        return [
            'subject' => Str::limit($subject, 255, ''),
            'body' => $body,
        ];
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function fallbackDraft(PurchaseOrder $purchaseOrder): array
    {
        $supplierName = $purchaseOrder->supplier?->name ?: 'Supplier';
        $itemLines = $purchaseOrder->items
            ->map(fn ($item): string => '- '.($item->ingredient?->name ?? $item->description).': '.number_format((float) $item->quantity, 2).' '.$item->unit)
            ->implode("\n");

        return [
            'subject' => "Purchase Order {$purchaseOrder->po_number} - Ting Hao Bakery Restock Request",
            'body' => "Dear {$supplierName},\n\nWe would like to place the following order for Ting Hao Bakery:\n\n{$itemLines}\n\nPlease confirm item availability and your earliest delivery date.\n\nThank you,\nTing Hao Team",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function businessContext(PurchaseOrder $purchaseOrder): array
    {
        $prediction = $purchaseOrder->agentRun?->input_type === 'stock_prediction_restock'
            ? (array) data_get($purchaseOrder->agentRun?->parsed_intent, 'stock_prediction', [])
            : [];

        return [
            'reason' => $prediction
                ? 'Stock prediction indicates this item may run low soon.'
                : ($purchaseOrder->agent_reasoning ? Str::limit($purchaseOrder->agent_reasoning, 220) : 'Approved purchase order requires supplier confirmation.'),
            'risk_level' => $prediction['risk_level'] ?? $prediction['risk_label'] ?? null,
            'estimated_days_until_stockout' => $prediction['estimated_days_until_stockout'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    private function logToolCall(PurchaseOrder $purchaseOrder, string $toolName, array $input, array $output, string $status = 'completed'): ?AgentToolCall
    {
        if (! $purchaseOrder->agent_run_id) {
            return null;
        }

        return AgentToolCall::create([
            'agent_run_id' => $purchaseOrder->agent_run_id,
            'tool_name' => $toolName,
            'input_payload' => $input,
            'output_payload' => $output,
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function onlyExistingDraftColumns(array $attributes): array
    {
        return collect($attributes)
            ->filter(fn (mixed $value, string $column): bool => Schema::hasColumn('supplier_email_drafts', $column))
            ->all();
    }
}
