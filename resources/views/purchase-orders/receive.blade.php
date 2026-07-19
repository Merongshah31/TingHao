@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.goods_receiving') }}</p>
                <h1>{{ $purchaseOrder->po_number }}</h1>
                <p>{{ $purchaseOrder->supplier?->name ?? __('messages.not_set') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn btn-muted">{{ __('messages.back') }}</a>
            </div>
        </div>

        @if ($errors->any())
            <div class="error-alert">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('purchase-orders.receive', $purchaseOrder) }}" class="form-card receiving-form">
            @csrf
            @php
                $defaultAllocationLocationId = $stockLocations->firstWhere('type', \App\Models\StockLocation::TYPE_STORAGE)?->id
                    ?? $stockLocations->first(fn ($location) => ! $location->isQuarantine())?->id;
            @endphp

            <div class="receiving-form-header">
                <div>
                    <p class="eyebrow">{{ __('messages.stock_allocation') }}</p>
                    <h2>{{ __('messages.goods_receiving') }}</h2>
                    <span>{{ $purchaseOrder->supplier?->name ?? __('messages.not_set') }} &middot; {{ $purchaseOrder->po_number }}</span>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('messages.record') }}</button>
            </div>

            @foreach ($purchaseOrder->items as $item)
                @php
                    $remaining = max(0, (float) $item->quantity - (float) $item->received_quantity);
                    $receivedValue = (float) old("items.{$item->id}.received_quantity", $remaining);
                    $acceptedValue = (float) old("items.{$item->id}.accepted_quantity", $remaining);
                    $damagedValue = (float) old("items.{$item->id}.damaged_quantity", 0);
                    $returnedValue = (float) old("items.{$item->id}.returned_quantity", 0);
                    $shortageValue = (float) old("items.{$item->id}.shortage_quantity", 0);
                    $qualityStatus = old("items.{$item->id}.quality_status");

                    if (! in_array($qualityStatus, \App\Models\PurchaseOrderItem::QUALITY_STATUSES, true) && $receivedValue > 0) {
                        $qualityStatus = match (true) {
                            $returnedValue > 0 => \App\Models\PurchaseOrderItem::QUALITY_RETURNED,
                            $damagedValue > 0 && $acceptedValue <= 0 => \App\Models\PurchaseOrderItem::QUALITY_DAMAGED,
                            $shortageValue > 0 && $acceptedValue <= 0 => \App\Models\PurchaseOrderItem::QUALITY_SHORTAGE,
                            $damagedValue > 0 || $shortageValue > 0 => \App\Models\PurchaseOrderItem::QUALITY_PARTIALLY_ACCEPTED,
                            round($receivedValue, 2) === round($acceptedValue, 2) => \App\Models\PurchaseOrderItem::QUALITY_ACCEPTED,
                            default => null,
                        };
                    }
                @endphp
                <section class="receiving-item-panel" data-receiving-row>
                    <div class="receiving-item-head">
                        <div class="receiving-item-title">
                            <p class="eyebrow">{{ __('messages.ingredient') }}</p>
                            <h3>{{ $item->ingredient?->name ?? $item->description }}</h3>
                            <span>{{ $item->description ?: __('messages.goods_receiving') }}</span>
                        </div>
                        <div class="receiving-metrics">
                            <div>
                                <span>{{ __('messages.ordered') }}</span>
                                <strong>{{ number_format((float) $item->quantity, 2) }}</strong>
                                <em>{{ $item->unit }}</em>
                            </div>
                            <div>
                                <span>{{ __('messages.received_quantity') }}</span>
                                <strong>{{ number_format((float) $item->received_quantity, 2) }}</strong>
                                <em>{{ $item->unit }}</em>
                            </div>
                            <div>
                                <span>{{ __('messages.remaining') }}</span>
                                <strong>{{ number_format((float) $remaining, 2) }}</strong>
                                <em>{{ $item->unit }}</em>
                            </div>
                        </div>
                    </div>

                    <div class="receiving-section-title">
                        <div>
                            <p class="eyebrow">{{ __('messages.goods_receiving') }}</p>
                            <h4>{{ __('messages.receiving_discrepancy') }}</h4>
                        </div>
                        <span>{{ __('messages.receiving_formula_help') }}</span>
                    </div>

                    <div class="receiving-input-grid">
                        <label class="receiving-field">
                            <span>{{ __('messages.received_quantity') }}</span>
                            <input type="number" step="0.01" min="0" name="items[{{ $item->id }}][received_quantity]" value="{{ $receivedValue }}" data-receiving-value="received">
                        </label>
                        <label class="receiving-field">
                            <span>{{ __('messages.accepted_quantity') }}</span>
                            <input type="number" step="0.01" min="0" name="items[{{ $item->id }}][accepted_quantity]" value="{{ $acceptedValue }}" data-receiving-value="accepted">
                        </label>
                        <label class="receiving-field">
                            <span>{{ __('messages.damaged_quantity') }}</span>
                            <input type="number" step="0.01" min="0" name="items[{{ $item->id }}][damaged_quantity]" value="{{ $damagedValue }}" data-receiving-value="damaged">
                        </label>
                        <label class="receiving-field">
                            <span>{{ __('messages.returned_quantity') }}</span>
                            <input type="number" step="0.01" min="0" name="items[{{ $item->id }}][returned_quantity]" value="{{ $returnedValue }}" data-receiving-value="returned">
                        </label>
                        <label class="receiving-field">
                            <span>{{ __('messages.shortage_quantity') }}</span>
                            <input type="number" step="0.01" min="0" name="items[{{ $item->id }}][shortage_quantity]" value="{{ $shortageValue }}" data-receiving-value="shortage">
                        </label>
                        <label class="receiving-field">
                            <span>{{ __('messages.quality_status') }}</span>
                            <select name="items[{{ $item->id }}][quality_status]" data-quality-status>
                                <option value="">{{ __('messages.not_set') }}</option>
                                @foreach (\App\Models\PurchaseOrderItem::QUALITY_STATUSES as $status)
                                    <option value="{{ $status }}" @selected($qualityStatus === $status)>{{ $status === \App\Models\PurchaseOrderItem::QUALITY_ACCEPTED ? __('messages.accepted_good') : __('messages.'.$status) }}</option>
                                @endforeach
                            </select>
                            <small class="receiving-field-help">{{ __('messages.quality_status_help') }}</small>
                        </label>
                        <label class="receiving-field receiving-field-wide">
                            <span>{{ __('messages.notes') }}</span>
                            <input type="text" name="items[{{ $item->id }}][receiving_notes]" value="{{ old("items.{$item->id}.receiving_notes") }}" placeholder="{{ __('messages.optional') }}">
                        </label>
                    </div>

                    <div class="receiving-allocation-panel">
                        <div class="receiving-section-title">
                            <div>
                                <p class="eyebrow">{{ __('messages.stock_allocation') }}</p>
                                <h4>{{ __('messages.stock_allocation') }}</h4>
                            </div>
                            <span>{{ __('messages.accepted_quantity') }} = {{ __('messages.store_room') }} + {{ __('messages.production_area') }} + {{ __('messages.front_counter') }}</span>
                        </div>
                        <div class="receiving-allocation-grid">
                            @foreach ($stockLocations as $location)
                                <label @class([
                                    'allocation-card',
                                    'is-warning' => $location->isQuarantine() && ($damagedValue > 0 || $returnedValue > 0),
                                ]) @if ($location->isQuarantine()) data-quarantine-card @endif>
                                    <span>{{ __('messages.'.str_replace([' / ', ' '], ['_', '_'], strtolower($location->name))) }}</span>
                                    <small>{{ $location->isQuarantine() ? __('messages.damaged_stock') : __('messages.accepted_quantity') }}</small>
                                    <input type="number" step="0.01" min="0" name="items[{{ $item->id }}][allocations][{{ $location->id }}]" value="{{ old("items.{$item->id}.allocations.{$location->id}", ! $location->isQuarantine() && $location->id === $defaultAllocationLocationId ? $remaining : 0) }}">
                                </label>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endforeach

            <div class="receiving-form-actions">
                <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn btn-muted">{{ __('messages.back') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('messages.record_receiving') }}</button>
            </div>
        </form>
    </section>
</main>

<script>
    document.querySelectorAll('[data-receiving-row]').forEach((row) => {
        const value = (name) => Number.parseFloat(row.querySelector(`[data-receiving-value="${name}"]`)?.value || '0') || 0;
        const qualityStatus = row.querySelector('[data-quality-status]');
        const quarantineCard = row.querySelector('[data-quarantine-card]');

        const updateReceivingState = () => {
            const received = value('received');
            const accepted = value('accepted');
            const damaged = value('damaged');
            const returned = value('returned');
            const shortage = value('shortage');

            quarantineCard?.classList.toggle('is-warning', damaged > 0 || returned > 0);

            if (!qualityStatus || received <= 0) {
                if (qualityStatus) qualityStatus.value = '';
                return;
            }

            if (returned > 0) {
                qualityStatus.value = 'returned';
            } else if (damaged > 0 && accepted <= 0) {
                qualityStatus.value = 'damaged';
            } else if (shortage > 0 && accepted <= 0) {
                qualityStatus.value = 'shortage';
            } else if (damaged > 0 || shortage > 0) {
                qualityStatus.value = 'partially_accepted';
            } else if (Math.abs(received - accepted) < 0.005) {
                qualityStatus.value = 'accepted';
            } else {
                qualityStatus.value = '';
            }
        };

        row.querySelectorAll('[data-receiving-value]').forEach((input) => {
            input.addEventListener('input', updateReceivingState);
        });

        updateReceivingState();
    });

    document.querySelector('.receiving-form')?.addEventListener('submit', (event) => {
        if (!event.currentTarget.checkValidity()) return;

        event.currentTarget.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
    });
</script>
@endsection
