@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.reports') }}</p>
                <h1>{{ __('messages.stock_movement_report') }}</h1>
            </div>
            <div class="page-actions">
                <a href="{{ route('reports.index') }}" class="btn btn-muted">{{ __('messages.reports') }}</a>
            </div>
        </div>

        <form class="filter-bar report-filter-bar" method="get" action="{{ route('reports.stock') }}">
            <select name="type">
                <option value="">{{ __('messages.all_movements') }}</option>
                <option value="in" @selected($selectedType === 'in')>{{ __('messages.stock_in') }}</option>
                <option value="out" @selected($selectedType === 'out')>{{ __('messages.stock_out') }}</option>
            </select>
            <input name="from" value="{{ $from }}" type="date">
            <input name="to" value="{{ $to }}" type="date">
            <button type="submit" class="btn btn-primary">{{ __('messages.filter') }}</button>
            <a href="{{ route('reports.stock') }}" class="btn btn-muted">{{ __('messages.reset') }}</a>
        </form>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.date') }}</th>
                        <th>{{ __('messages.ingredient') }}</th>
                        <th>{{ __('messages.type') }}</th>
                        <th>{{ __('messages.quantity') }}</th>
                        <th>{{ __('messages.before') }}</th>
                        <th>{{ __('messages.after') }}</th>
                        <th>{{ __('messages.recorded_by') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td>{{ $movement->created_at->format('d M Y H:i') }}</td>
                            <td>{{ $movement->ingredient->name }}</td>
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
                        <tr><td colspan="7" class="empty-state">{{ __('messages.no_stock_movement_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
