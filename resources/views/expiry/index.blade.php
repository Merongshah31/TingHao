@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">EXPIRY DATE TRACKING</p>
                <h1>{{ $filter === 'expired' ? 'Expired Items' : 'Expiring Soon' }}</h1>
                <p>Track ingredients with expiry dates within 30 days or already past their expiry date.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">Dashboard</a>
                <a href="{{ route('inventory.index') }}" class="btn btn-primary">Inventory</a>
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <div class="segmented-actions">
            <a href="{{ route('expiry.index', ['filter' => 'expiring']) }}" class="{{ $filter !== 'expired' ? 'active' : '' }}">Expiring Soon</a>
            <a href="{{ route('expiry.index', ['filter' => 'expired']) }}" class="{{ $filter === 'expired' ? 'active' : '' }}">Expired</a>
        </div>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th>Action</th>
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
                                <span>{{ $ingredient->sku ?: 'No SKU' }}</span>
                            </td>
                            <td>{{ $ingredient->category?->name ?? 'Uncategorized' }}</td>
                            <td>{{ $ingredient->quantity }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->expiry_date?->format('d M Y') }}</td>
                            <td>
                                <span class="status-pill {{ $isExpired ? 'danger' : 'warning' }}">
                                    {{ $isExpired ? 'Expired' : 'Expiring Soon' }}
                                </span>
                            </td>
                            <td class="table-actions stacked-actions">
                                <a href="{{ route('inventory.show', $ingredient) }}">View</a>
                                @if (auth()->user()->isAdmin() && $isExpired && (float) $ingredient->quantity > 0)
                                    <form action="{{ route('expiry.remove', $ingredient) }}" method="post">
                                        @csrf
                                        <button type="submit">Remove Stock</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">No ingredients found for this expiry filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
