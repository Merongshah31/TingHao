@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">REPORTS & ANALYTICS</p>
                <h1>Stock Movement Report</h1>
            </div>
            <div class="page-actions">
                <a href="{{ route('reports.index') }}" class="btn btn-muted">Reports</a>
            </div>
        </div>

        <form class="filter-bar report-filter-bar" method="get" action="{{ route('reports.stock') }}">
            <select name="type">
                <option value="">All movements</option>
                <option value="in" @selected($selectedType === 'in')>Stock In</option>
                <option value="out" @selected($selectedType === 'out')>Stock Out</option>
            </select>
            <input name="from" value="{{ $from }}" type="date">
            <input name="to" value="{{ $to }}" type="date">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('reports.stock') }}" class="btn btn-muted">Reset</a>
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
                            <td>{{ $movement->creator?->name ?? 'Unknown' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">No stock movement data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
