@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.stock_movement') }}</p>
                <h1>{{ __('messages.stock_movement_history') }}</h1>
                <p>{{ __('messages.stock_intro') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">{{ __('messages.dashboard') }}</a>
                <a href="{{ route('inventory.index') }}" class="btn btn-primary">{{ __('messages.inventory') }}</a>
            </div>
        </div>

        <form class="filter-bar" method="get" action="{{ route('stock.index') }}">
            <select name="ingredient">
                <option value="0">{{ __('messages.all_ingredients') }}</option>
                @foreach ($ingredients as $ingredient)
                    <option value="{{ $ingredient->id }}" @selected($selectedIngredient === $ingredient->id)>{{ $ingredient->name }}</option>
                @endforeach
            </select>
            <select name="type">
                <option value="">{{ __('messages.all_movements') }}</option>
                <option value="in" @selected($selectedType === 'in')>{{ __('messages.stock_in') }}</option>
                <option value="out" @selected($selectedType === 'out')>{{ __('messages.stock_out') }}</option>
            </select>
            <button type="submit" class="btn btn-primary">{{ __('messages.filter') }}</button>
            <a href="{{ route('stock.index') }}" class="btn btn-muted">{{ __('messages.reset') }}</a>
        </form>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.date') }}</th>
                        <th>{{ __('messages.ingredient') }}</th>
                        <th>{{ __('messages.type') }}</th>
                        <th>{{ __('messages.quantity') }}</th>
                        <th>Before</th>
                        <th>After</th>
                        <th>{{ __('messages.recorded_by') }}</th>
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
                                <span>{{ $movement->reason ?: __('messages.no_reason') }}</span>
                            </td>
                            <td>
                                <span class="status-pill {{ $movement->type === 'in' ? 'ok' : 'danger' }}">
                                    {{ $movement->typeLabel() }}
                                </span>
                            </td>
                            <td>{{ $movement->quantity }} {{ $movement->ingredient->unit }}</td>
                            <td>{{ $movement->quantity_before }} {{ $movement->ingredient->unit }}</td>
                            <td>{{ $movement->quantity_after }} {{ $movement->ingredient->unit }}</td>
                            <td>{{ $movement->creator?->name ?? __('messages.unknown') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">{{ __('messages.no_stock_movements') }}</td>
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
