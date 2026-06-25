@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.inventory') }}</p>
                <h1>{{ __('messages.ingredient_inventory') }}</h1>
                <p>{{ __('messages.inventory_intro') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">{{ __('messages.dashboard') }}</a>
                <a href="{{ route('alerts.low-stock') }}" class="btn btn-muted">{{ __('messages.low_stock') }}</a>
                <a href="{{ route('expiry.index') }}" class="btn btn-muted">{{ __('messages.expiry') }}</a>
                <a href="{{ route('suppliers.index') }}" class="btn btn-muted">{{ __('messages.suppliers') }}</a>
                @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                    <a href="{{ route('inventory.create') }}" class="btn btn-primary">{{ __('messages.add_ingredient') }}</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <form class="filter-bar" method="get" action="{{ route('inventory.index') }}">
            <input name="search" value="{{ $search }}" type="search" placeholder="Search name or SKU">
            <select name="category">
                <option value="0">{{ __('messages.all_categories') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($selectedCategory === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary">{{ __('messages.filter') }}</button>
            <a href="{{ route('inventory.index') }}" class="btn btn-muted">{{ __('messages.reset') }}</a>
        </form>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.ingredient') }}</th>
                        <th>{{ __('messages.category') }}</th>
                        <th>{{ __('messages.supplier') }}</th>
                        <th>{{ __('messages.quantity') }}</th>
                        <th>{{ __('messages.low_stock') }}</th>
                        <th>{{ __('messages.expiry') }}</th>
                        <th>{{ __('messages.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ingredients as $ingredient)
                        <tr>
                            <td>
                                <strong>{{ $ingredient->name }}</strong>
                                <span>{{ $ingredient->sku ?: __('messages.no_sku') }}</span>
                            </td>
                            <td>{{ $ingredient->category?->name ?? __('messages.uncategorized') }}</td>
                            <td>{{ $ingredient->supplier?->name ?? __('messages.no_supplier') }}</td>
                            <td>{{ $ingredient->quantity }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->minimum_stock }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->expiry_date?->format('d M Y') ?? 'Not set' }}</td>
                            <td>
                                <span class="status-pill {{ $ingredient->isLowStock() ? 'danger' : 'ok' }}">
                                    {{ $ingredient->isLowStock() ? __('messages.low_stock') : __('messages.available') }}
                                </span>
                            </td>
                            <td class="table-actions">
                                <a href="{{ route('inventory.show', $ingredient) }}">{{ __('messages.view') }}</a>
                                <a href="{{ route('stock.create', [$ingredient, 'in']) }}">{{ __('messages.stock_in') }}</a>
                                <a href="{{ route('stock.create', [$ingredient, 'out']) }}">{{ __('messages.stock_out') }}</a>
                                @if (auth()->user()->isAdmin())
                                    <a href="{{ route('inventory.edit', $ingredient) }}">{{ __('messages.edit') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">{{ __('messages.no_ingredients_found') }}</td>
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
