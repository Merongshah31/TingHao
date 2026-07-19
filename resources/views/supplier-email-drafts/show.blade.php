@extends('layouts.app')

@section('content')
@php
    $isAdmin = auth()->user()->isAdmin();
    $canEdit = $isAdmin && in_array($supplierEmailDraft->status, [
        \App\Models\SupplierEmailDraft::STATUS_DRAFT,
        \App\Models\SupplierEmailDraft::STATUS_APPROVED,
    ], true);
    $realEmailEnabled = (bool) ($emailDelivery['enabled'] ?? false);
    $realEmailConfigured = (bool) ($emailDelivery['configured'] ?? false);
@endphp
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">Supplier Email Draft</p>
                <h1>{{ $supplierEmailDraft->subject }}</h1>
                <p>{{ $supplierEmailDraft->supplier?->name ?? __('messages.not_set') }} &middot; {{ $purchaseOrder->po_number }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn btn-muted">Back to PO</a>
                @if ($isAdmin && $supplierEmailDraft->status === \App\Models\SupplierEmailDraft::STATUS_DRAFT)
                    <form method="post" action="{{ route('supplier-email-drafts.approve', $supplierEmailDraft) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">Approve Email Draft</button>
                    </form>
                    <form method="post" action="{{ route('supplier-email-drafts.regenerate', $supplierEmailDraft) }}">
                        @csrf
                        <button class="btn btn-muted" type="submit">Regenerate Draft</button>
                    </form>
                @endif
                @if ($isAdmin && $supplierEmailDraft->status === \App\Models\SupplierEmailDraft::STATUS_APPROVED)
                    @if ($realEmailEnabled && $realEmailConfigured)
                        <form method="post" action="{{ route('supplier-email-drafts.send-via-gmail', $supplierEmailDraft) }}">
                            @csrf
                            <button class="btn btn-primary" type="submit">Send via Gmail</button>
                        </form>
                    @elseif (! $realEmailEnabled)
                        <form method="post" action="{{ route('supplier-email-drafts.mark-sent', $supplierEmailDraft) }}">
                            @csrf
                            <button class="btn btn-primary" type="submit">Mark Email as Sent</button>
                        </form>
                    @endif
                @endif
                @if (! $isAdmin && $supplierEmailDraft->status !== \App\Models\SupplierEmailDraft::STATUS_SENT)
                    <span class="status-pill warning">Waiting for admin approval</span>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="error-alert">{{ $errors->first() }}</div>
        @endif

        <section class="mission-grid">
            <article class="autopilot-card simple-decision-card">
                <p class="eyebrow">AI-generated supplier draft</p>
                <h2>Admin review and explicit delivery</h2>
                <p class="agent-summary">TingHao Agent generated this supplier email draft from the approved purchase order. Admin reviews and approves the content, then explicitly sends through configured Gmail or records a demo-safe Mark Sent action. Nothing is sent automatically.</p>
                <div class="agent-detail-list">
                    <div><span>Linked PO</span><strong>{{ $purchaseOrder->po_number }}</strong></div>
                    <div><span>Supplier</span><strong>{{ $supplierEmailDraft->supplier?->name ?? __('messages.not_set') }}</strong></div>
                    <div><span>Email status</span><strong>{{ ucfirst($supplierEmailDraft->status) }}</strong></div>
                    <div><span>Source</span><strong>Qwen Cloud</strong></div>
                </div>
                @if ($supplierEmailDraft->agentRun)
                    <p><a href="{{ route('agent.runs.show', $supplierEmailDraft->agentRun) }}">Back to Agent Mission #{{ $supplierEmailDraft->agentRun->id }}</a></p>
                @endif
            </article>

            <article class="safety-card simple-decision-card">
                <p class="eyebrow">Email Safety</p>
                <h2>{{ $realEmailEnabled ? 'Real Gmail delivery control' : 'Demo-safe delivery mode' }}</h2>
                <ul>
                    <li>Admin approval is required before any delivery action.</li>
                    <li>{{ $emailDelivery['message'] }}</li>
                    <li>Supplier contact details are shown for review.</li>
                    <li>Delivery success or failure is stored without credentials.</li>
                </ul>
            </article>
        </section>

        <div class="info-panel po-summary-panel">
            <dl>
                <div><dt>Status</dt><dd><span class="status-pill email-status-{{ $supplierEmailDraft->status }}">{{ ucfirst($supplierEmailDraft->status) }}</span></dd></div>
                <div><dt>Supplier</dt><dd>{{ $supplierEmailDraft->supplier?->name ?? __('messages.not_set') }}</dd></div>
                <div><dt>Email</dt><dd>{{ $supplierEmailDraft->supplier?->email ?? __('messages.not_set') }}</dd></div>
                <div><dt>Contact</dt><dd>{{ $supplierEmailDraft->supplier?->contact_person ?? $supplierEmailDraft->supplier?->phone ?? __('messages.not_set') }}</dd></div>
                <div><dt>Linked PO</dt><dd><a href="{{ route('purchase-orders.show', $purchaseOrder) }}">{{ $purchaseOrder->po_number }}</a></dd></div>
                <div><dt>Source</dt><dd>Qwen Cloud</dd></div>
                <div><dt>Qwen model</dt><dd>{{ $supplierEmailDraft->qwen_model ?? __('messages.not_set') }}</dd></div>
                <div><dt>Approved by</dt><dd>{{ $supplierEmailDraft->approvedBy?->name ?? __('messages.not_set') }}</dd></div>
                <div><dt>Approved at</dt><dd>{{ $supplierEmailDraft->approved_at?->format('d M Y H:i') ?? __('messages.not_set') }}</dd></div>
                <div><dt>Sent at</dt><dd>{{ $supplierEmailDraft->sent_at?->format('d M Y H:i') ?? 'Not marked sent' }}</dd></div>
                <div><dt>Delivery mode</dt><dd>{{ $realEmailEnabled ? 'Explicit Gmail SMTP' : 'Demo-safe Mark Sent' }}</dd></div>
                <div><dt>Delivery status</dt><dd>{{ $supplierEmailDraft->delivery_status ? str($supplierEmailDraft->delivery_status)->replace('_', ' ')->title() : 'Not attempted' }}</dd></div>
                <div><dt>Delivery provider</dt><dd>{{ $supplierEmailDraft->delivery_provider ? str($supplierEmailDraft->delivery_provider)->replace('_', ' ')->title() : 'Not set' }}</dd></div>
                <div><dt>Last attempt</dt><dd>{{ $supplierEmailDraft->last_delivery_attempt_at?->format('d M Y H:i') ?? 'Not attempted' }}</dd></div>
            </dl>
        </div>

        <section class="info-panel supplier-email-card">
            <div class="section-heading-row">
                <div><p class="eyebrow">Supplier message</p><h2>Email Content</h2></div>
                @if ($canEdit)<span class="status-pill warning">Editing requires approval</span>@endif
            </div>
            @if ($canEdit)
                <form method="post" action="{{ route('supplier-email-drafts.update', $supplierEmailDraft) }}" class="email-draft-edit-form">
                    @csrf
                    @method('PUT')
                    <label for="email-subject">Subject</label>
                    <input id="email-subject" name="subject" type="text" value="{{ old('subject', $supplierEmailDraft->subject) }}" maxlength="255" required>
                    <label for="email-body">Email body</label>
                    <textarea id="email-body" name="body" rows="14" maxlength="10000" required>{{ old('body', $supplierEmailDraft->body) }}</textarea>
                    <p class="field-help">Suggestions are advisory. Saving changes resets an approved draft to Draft so an admin must approve it again.</p>
                    <button class="btn btn-primary" type="submit">Save Draft Changes</button>
                </form>
            @else
                <p class="eyebrow">Subject</p>
                <h2>{{ $supplierEmailDraft->subject }}</h2>
                <pre>{{ $supplierEmailDraft->body }}</pre>
            @endif
        </section>

        @if ($supplierEmailDraft->agentRun)
            <details class="info-panel advanced-details-panel">
                <summary>{{ __('messages.advanced_details') }}</summary>
                <div class="advanced-details-body">
                    <x-agent.reasoning-activity :steps="$supplierEmailDraft->agentRun->reasoningSteps" />
                    <p><a href="{{ route('agent.runs.show', $supplierEmailDraft->agentRun) }}">Open Agent Mission #{{ $supplierEmailDraft->agentRun->id }}</a></p>
                </div>
            </details>
        @endif

        <div class="table-card movement-preview">
            <div class="section-heading-row">
                <h2>Purchase Order Items</h2>
                <strong>{{ $purchaseOrder->po_number }}</strong>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.ingredient') }}</th>
                        <th>{{ __('messages.quantity') }}</th>
                        <th>{{ __('messages.unit_price') }}</th>
                        <th>{{ __('messages.line_total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchaseOrder->items as $item)
                        <tr>
                            <td><strong>{{ $item->ingredient?->name ?? $item->description }}</strong><span>{{ $item->description }}</span></td>
                            <td>{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</td>
                            <td>RM {{ number_format((float) $item->unit_price, 2) }}</td>
                            <td>RM {{ number_format((float) $item->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
