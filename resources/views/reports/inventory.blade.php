@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.reports') }}</p>
                <h1>{{ __('messages.inventory_report') }}</h1>
            </div>
            <div class="page-actions">
                <a href="{{ route('reports.index') }}" class="btn btn-muted">{{ __('messages.reports') }}</a>
            </div>
        </div>

        <form class="filter-bar" method="get" action="{{ route('reports.inventory') }}">
            <select name="category">
                <option value="0">{{ __('messages.all_categories') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($selectedCategory === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary">{{ __('messages.filter') }}</button>
            <a href="{{ route('reports.inventory') }}" class="btn btn-muted">{{ __('messages.reset') }}</a>
        </form>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.ingredient') }}</th>
                        <th>{{ __('messages.category') }}</th>
                        <th>{{ __('messages.supplier') }}</th>
                        <th>{{ __('messages.quantity') }}</th>
                        <th>{{ __('messages.minimum') }}</th>
                        <th>{{ __('messages.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ingredients as $ingredient)
                        <tr>
                            <td><strong>{{ $ingredient->name }}</strong></td>
                            <td>{{ $ingredient->category?->name ?? __('messages.uncategorized') }}</td>
                            <td>{{ $ingredient->supplier?->name ?? __('messages.no_supplier') }}</td>
                            <td>{{ $ingredient->quantity }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->minimum_stock }} {{ $ingredient->unit }}</td>
                            <td>
                                <span class="status-pill {{ $ingredient->isLowStock() ? 'danger' : 'ok' }}">
                                {{ $ingredient->isLowStock() ? __('messages.low_stock') : __('messages.available') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">{{ __('messages.no_inventory_data') }}</td></tr>
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
