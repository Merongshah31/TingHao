@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">LOW STOCK ALERT</p>
                <h1>Low Stock Notification</h1>
                <p>Ingredients shown here are at or below their minimum stock level.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">Dashboard</a>
                <a href="{{ route('inventory.index') }}" class="btn btn-primary">Inventory</a>
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th>Quantity</th>
                        <th>Minimum</th>
                        <th>Restock Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ingredients as $ingredient)
                        @php
                            $latestRestock = $ingredient->restockRequests->first();
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $ingredient->name }}</strong>
                                <span>{{ $ingredient->category?->name ?? 'Uncategorized' }}</span>
                            </td>
                            <td>{{ $ingredient->quantity }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->minimum_stock }} {{ $ingredient->unit }}</td>
                            <td>
                                @if ($latestRestock)
                                    <span class="status-pill {{ $latestRestock->status === 'completed' ? 'ok' : 'warning' }}">
                                        {{ $latestRestock->statusLabel() }}
                                    </span>
                                @else
                                    <span class="status-pill danger">Needs Request</span>
                                @endif
                            </td>
                            <td class="table-actions stacked-actions">
                                <a href="{{ route('inventory.show', $ingredient) }}">View</a>
                                @if (auth()->user()->isAdmin())
                                    @if ($latestRestock)
                                        <form action="{{ route('alerts.restock.update', $latestRestock) }}" method="post">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status">
                                                <option value="requested" @selected($latestRestock->status === 'requested')>Requested</option>
                                                <option value="ordered" @selected($latestRestock->status === 'ordered')>Ordered</option>
                                                <option value="completed" @selected($latestRestock->status === 'completed')>Completed</option>
                                            </select>
                                            <button type="submit">Update</button>
                                        </form>
                                    @else
                                        <form action="{{ route('alerts.restock.request', $ingredient) }}" method="post">
                                            @csrf
                                            <input name="notes" type="hidden" value="Created from low-stock alert.">
                                            <button type="submit">Request Restock</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">No low-stock ingredients right now.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
