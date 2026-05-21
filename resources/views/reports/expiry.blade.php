@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">REPORTS & ANALYTICS</p>
                <h1>Expiry Report</h1>
            </div>
            <div class="page-actions">
                <a href="{{ route('reports.index') }}" class="btn btn-muted">Reports</a>
                <a href="{{ route('expiry.index') }}" class="btn btn-primary">Manage Expiry</a>
            </div>
        </div>

        <div class="segmented-actions">
            <a href="{{ route('reports.expiry', ['filter' => 'expiring']) }}" class="{{ $filter !== 'expired' ? 'active' : '' }}">Expiring Soon</a>
            <a href="{{ route('reports.expiry', ['filter' => 'expired']) }}" class="{{ $filter === 'expired' ? 'active' : '' }}">Expired</a>
        </div>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th>Quantity</th>
                        <th>Expiry Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ingredients as $ingredient)
                        <tr>
                            <td><strong>{{ $ingredient->name }}</strong></td>
                            <td>{{ $ingredient->category?->name ?? 'Uncategorized' }}</td>
                            <td>{{ $ingredient->supplier?->name ?? 'No supplier' }}</td>
                            <td>{{ $ingredient->quantity }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->expiry_date?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">No expiry data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
