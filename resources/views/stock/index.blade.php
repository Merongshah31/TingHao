@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">STOCK CONTROL</p>
                <h1>Stock Movement History</h1>
                <p>Review every stock-in and stock-out record across the inventory.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">Dashboard</a>
                <a href="{{ route('inventory.index') }}" class="btn btn-primary">Inventory</a>
            </div>
        </div>

        <form class="filter-bar" method="get" action="{{ route('stock.index') }}">
            <select name="ingredient">
                <option value="0">All ingredients</option>
                @foreach ($ingredients as $ingredient)
                    <option value="{{ $ingredient->id }}" @selected($selectedIngredient === $ingredient->id)>{{ $ingredient->name }}</option>
                @endforeach
            </select>
            <select name="type">
                <option value="">All movements</option>
                <option value="in" @selected($selectedType === 'in')>Stock In</option>
                <option value="out" @selected($selectedType === 'out')>Stock Out</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('stock.index') }}" class="btn btn-muted">Reset</a>
        </form>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Ingredient</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Before</th>
                        <th>After</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td>
                                <strong>{{ $movement->created_at->format('d M Y') }}</strong>
                                <span>{{ $movement->created_at->format('H:i') }}</span>
                            </td>
                            <td>
                                <strong>{{ $movement->ingredient->name }}</strong>
                                <span>{{ $movement->reason ?: 'No reason' }}</span>
                            </td>
                            <td>
                                <span class="status-pill {{ $movement->type === 'in' ? 'ok' : 'danger' }}">
                                    {{ $movement->typeLabel() }}
                                </span>
                            </td>
                            <td>{{ $movement->quantity }} {{ $movement->ingredient->unit }}</td>
                            <td>{{ $movement->quantity_before }} {{ $movement->ingredient->unit }}</td>
                            <td>{{ $movement->quantity_after }} {{ $movement->ingredient->unit }}</td>
                            <td>{{ $movement->creator?->name ?? 'Unknown' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">No stock movements recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $movements->links() }}
        </div>
    </section>
</main>
@endsection
