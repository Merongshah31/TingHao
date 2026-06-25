@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.expiry_date_tracking') }}</p>
                <h1>{{ $filter === 'expired' ? __('messages.expired_items') : __('messages.expiring_soon') }}</h1>
                <p>{{ __('messages.expiry_intro') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">{{ __('messages.dashboard') }}</a>
                <a href="{{ route('inventory.index') }}" class="btn btn-primary">{{ __('messages.inventory') }}</a>
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <div class="segmented-actions">
            <a href="{{ route('expiry.index', ['filter' => 'expiring']) }}" class="{{ $filter !== 'expired' ? 'active' : '' }}">{{ __('messages.expiring_soon') }}</a>
            <a href="{{ route('expiry.index', ['filter' => 'expired']) }}" class="{{ $filter === 'expired' ? 'active' : '' }}">{{ __('messages.expired') }}</a>
        </div>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.ingredient') }}</th>
                        <th>{{ __('messages.category') }}</th>
                        <th>{{ __('messages.quantity') }}</th>
                        <th>{{ __('messages.expiry_date') }}</th>
                        <th>{{ __('messages.status') }}</th>
                        <th>{{ __('messages.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ingredients as $ingredient)
                        @php
                            $isExpired = $ingredient->expiry_date?->isPast();
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $ingredient->name }}</strong>
                                <span>{{ $ingredient->sku ?: __('messages.no_sku') }}</span>
                            </td>
                            <td>{{ $ingredient->category?->name ?? __('messages.uncategorized') }}</td>
                            <td>{{ $ingredient->quantity }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->expiry_date?->format('d M Y') }}</td>
                            <td>
                                <span class="status-pill {{ $isExpired ? 'danger' : 'warning' }}">
                                    {{ $isExpired ? __('messages.expired') : __('messages.expiring_soon') }}
                                </span>
                            </td>
                            <td class="table-actions stacked-actions">
                                <a href="{{ route('inventory.show', $ingredient) }}">{{ __('messages.view') }}</a>
                                @if (auth()->user()->isAdmin() && $isExpired && (float) $ingredient->quantity > 0)
                                    <form action="{{ route('expiry.remove', $ingredient) }}" method="post">
                                        @csrf
                                        <button type="submit">{{ __('messages.stock_out') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">{{ __('messages.no_ingredients_found') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
