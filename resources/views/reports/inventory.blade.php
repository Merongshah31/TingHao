@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">REPORTS & ANALYTICS</p>
                <h1>Inventory Report</h1>
            </div>
            <div class="page-actions">
                <a href="{{ route('reports.index') }}" class="btn btn-muted">Reports</a>
            </div>
        </div>

        <form class="filter-bar" method="get" action="{{ route('reports.inventory') }}">
            <select name="category">
                <option value="0">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($selectedCategory === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('reports.inventory') }}" class="btn btn-muted">Reset</a>
        </form>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th>Quantity</th>
                        <th>Minimum</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ingredients as $ingredient)
                        <tr>
                            <td><strong>{{ $ingredient->name }}</strong></td>
                            <td>{{ $ingredient->category?->name ?? 'Uncategorized' }}</td>
                            <td>{{ $ingredient->supplier?->name ?? 'No supplier' }}</td>
                            <td>{{ $ingredient->quantity }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->minimum_stock }} {{ $ingredient->unit }}</td>
                            <td>
                                <span class="status-pill {{ $ingredient->isLowStock() ? 'danger' : 'ok' }}">
                                    {{ $ingredient->isLowStock() ? 'Low Stock' : 'Available' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">No inventory data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
