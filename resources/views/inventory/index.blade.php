@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">INVENTORY</p>
                <h1>Ingredient Inventory</h1>
                <p>Track bakery ingredients, quantities, prices, stock limits, and expiry dates.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">Dashboard</a>
                <a href="{{ route('alerts.low-stock') }}" class="btn btn-muted">Low Stock</a>
                <a href="{{ route('expiry.index') }}" class="btn btn-muted">Expiry</a>
                <a href="{{ route('suppliers.index') }}" class="btn btn-muted">Suppliers</a>
                @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                    <a href="{{ route('inventory.create') }}" class="btn btn-primary">Add Ingredient</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <form class="filter-bar" method="get" action="{{ route('inventory.index') }}">
            <input name="search" value="{{ $search }}" type="search" placeholder="Search name or SKU">
            <select name="category">
                <option value="0">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($selectedCategory === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('inventory.index') }}" class="btn btn-muted">Reset</a>
        </form>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th>Quantity</th>
                        <th>Min Stock</th>
                        <th>Expiry</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ingredients as $ingredient)
                        <tr>
                            <td>
                                <strong>{{ $ingredient->name }}</strong>
                                <span>{{ $ingredient->sku ?: 'No SKU' }}</span>
                            </td>
                            <td>{{ $ingredient->category?->name ?? 'Uncategorized' }}</td>
                            <td>{{ $ingredient->supplier?->name ?? 'No supplier' }}</td>
                            <td>{{ $ingredient->quantity }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->minimum_stock }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->expiry_date?->format('d M Y') ?? 'Not set' }}</td>
                            <td>
                                <span class="status-pill {{ $ingredient->isLowStock() ? 'danger' : 'ok' }}">
                                    {{ $ingredient->isLowStock() ? 'Low Stock' : 'Available' }}
                                </span>
                            </td>
                            <td class="table-actions">
                                <a href="{{ route('inventory.show', $ingredient) }}">View</a>
                                <a href="{{ route('stock.create', [$ingredient, 'in']) }}">Stock In</a>
                                <a href="{{ route('stock.create', [$ingredient, 'out']) }}">Stock Out</a>
                                @if (auth()->user()->isAdmin())
                                    <a href="{{ route('inventory.edit', $ingredient) }}">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">No ingredients found.</td>
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
