@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.low_stock_alert') }}</p>
                <h1>{{ __('messages.low_stock_notification') }}</h1>
                <p>{{ __('messages.low_stock_intro') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">{{ __('messages.dashboard') }}</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('purchase-orders.create-from-low-stock') }}" class="btn btn-muted">{{ __('messages.create_po_from_low_stock') }}</a>
                @endif
                <a href="{{ route('inventory.index') }}" class="btn btn-primary">{{ __('messages.inventory') }}</a>
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <section class="info-panel">
            <p class="eyebrow">{{ __('messages.tinghao_agent') }}</p>
            <h2>{{ __('messages.plan_restock_with_agent') }}</h2>
            <p class="agent-summary">{{ __('messages.low_stock_agent_intro') }}</p>
        </section>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.ingredient') }}</th>
                        <th>{{ __('messages.quantity') }}</th>
                        <th>{{ __('messages.low_stock') }}</th>
                        <th>{{ __('messages.restock') }}</th>
                        <th>{{ __('messages.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ingredients as $ingredient)
                        @php
                            $latestRestock = $ingredient->currentRestockRequest;
                            $activeRestock = $ingredient->activeRestockRequest;
                            $displayRestock = $activeRestock ?? $latestRestock;
                            $restockStatusClass = match ($displayRestock?->status) {
                                'completed' => 'ok',
                                'ordered', 'requested', 'pending' => 'warning',
                                'rejected' => 'danger',
                                default => 'danger',
                            };
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $ingredient->name }}</strong>
                                <span>{{ $ingredient->category?->name ?? __('messages.uncategorized') }}</span>
                            </td>
                            <td>{{ $ingredient->quantity }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->minimum_stock }} {{ $ingredient->unit }}</td>
                            <td>
                                @if ($displayRestock)
                                    <span class="status-pill {{ $restockStatusClass }}">
                                        {{ __('messages.'.$displayRestock->status) }}
                                    </span>
                                @else
                                    <span class="status-pill danger">{{ __('messages.needs_request') }}</span>
                                @endif
                            </td>
                            <td class="table-actions stacked-actions">
                                <a class="action-chip" href="{{ route('inventory.show', $ingredient) }}">{{ __('messages.view') }}</a>

                                @if ($activeRestock)
                                    <span class="action-chip disabled">{{ __('messages.already_requested') }}</span>
                                @else
                                    <form action="{{ route('alerts.restock.request', $ingredient) }}" method="post">
                                        @csrf
                                        <input name="notes" type="hidden" value="{{ __('messages.default_restock_request_note', ['ingredient' => $ingredient->name]) }}">
                                        <button type="submit" class="action-chip button-chip">{{ __('messages.request_stock') }}</button>
                                    </form>
                                @endif

                                <form action="{{ route('alerts.restock.agent-plan', $ingredient) }}" method="post">
                                    @csrf
                                    <button type="submit" class="action-chip button-chip">{{ __('messages.plan_restock_with_agent') }}</button>
                                </form>

                                <a class="action-chip" href="{{ route('stock.create', [$ingredient, 'in']) }}">{{ __('messages.stock_in') }}</a>
                                <a class="action-chip" href="{{ route('stock.create', [$ingredient, 'out']) }}">{{ __('messages.stock_out') }}</a>

                                @if (auth()->user()->isAdmin() && $activeRestock)
                                    <form class="restock-status-form" action="{{ route('alerts.restock.update', $activeRestock) }}" method="post">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status">
                                            <option value="requested" @selected($activeRestock->status === 'requested')>{{ __('messages.requested') }}</option>
                                            <option value="ordered" @selected($activeRestock->status === 'ordered')>{{ __('messages.ordered') }}</option>
                                            <option value="completed" @selected($activeRestock->status === 'completed')>{{ __('messages.completed') }}</option>
                                            <option value="rejected" @selected($activeRestock->status === 'rejected')>{{ __('messages.rejected') }}</option>
                                            </select>
                                            <button type="submit">{{ __('messages.update') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">{{ __('messages.no_low_stock_ingredients') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $ingredients->links() }}
        </div>
    </section>
</main>
@endsection
