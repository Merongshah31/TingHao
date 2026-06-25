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
                <a href="{{ route('inventory.index') }}" class="btn btn-primary">{{ __('messages.inventory') }}</a>
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

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
                            $latestRestock = $ingredient->restockRequests->first();
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $ingredient->name }}</strong>
                                <span>{{ $ingredient->category?->name ?? __('messages.uncategorized') }}</span>
                            </td>
                            <td>{{ $ingredient->quantity }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->minimum_stock }} {{ $ingredient->unit }}</td>
                            <td>
                                @if ($latestRestock)
                                    <span class="status-pill {{ $latestRestock->status === 'completed' ? 'ok' : 'warning' }}">
                                        {{ $latestRestock->statusLabel() }}
                                    </span>
                                @else
                                    <span class="status-pill danger">{{ __('messages.needs_request') }}</span>
                                @endif
                            </td>
                            <td class="table-actions stacked-actions">
                                <a href="{{ route('inventory.show', $ingredient) }}">{{ __('messages.view') }}</a>
                                @if (auth()->user()->isAdmin())
                                    @if ($latestRestock)
                                        <form action="{{ route('alerts.restock.update', $latestRestock) }}" method="post">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status">
                                                <option value="requested" @selected($latestRestock->status === 'requested')>{{ __('messages.requested') }}</option>
                                                <option value="ordered" @selected($latestRestock->status === 'ordered')>{{ __('messages.ordered') }}</option>
                                                <option value="completed" @selected($latestRestock->status === 'completed')>{{ __('messages.completed') }}</option>
                                            </select>
                                            <button type="submit">{{ __('messages.update') }}</button>
                                        </form>
                                    @else
                                        <form action="{{ route('alerts.restock.request', $ingredient) }}" method="post">
                                            @csrf
                                            <input name="notes" type="hidden" value="Created from low-stock alert.">
                                            <button type="submit">{{ __('messages.request_restock') }}</button>
                                        </form>
                                    @endif
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
    </section>
</main>
@endsection
