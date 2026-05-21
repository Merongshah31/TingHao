@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">INVENTORY DETAIL</p>
                <h1>{{ $ingredient->name }}</h1>
                <p>{{ $ingredient->category?->name ?? 'Uncategorized' }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('inventory.index') }}" class="btn btn-muted">Back</a>
                <a href="{{ route('stock.create', [$ingredient, 'in']) }}" class="btn btn-muted">Stock In</a>
                <a href="{{ route('stock.create', [$ingredient, 'out']) }}" class="btn btn-muted">Stock Out</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('inventory.edit', $ingredient) }}" class="btn btn-primary">Edit</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <div class="detail-grid">
            <article class="detail-card">
                <span>Current Quantity</span>
                <strong>{{ $ingredient->quantity }} {{ $ingredient->unit }}</strong>
            </article>
            <article class="detail-card">
                <span>Minimum Stock</span>
                <strong>{{ $ingredient->minimum_stock }} {{ $ingredient->unit }}</strong>
            </article>
            <article class="detail-card">
                <span>Stock Status</span>
                <strong>{{ $ingredient->isLowStock() ? 'Low Stock' : 'Available' }}</strong>
            </article>
            <article class="detail-card">
                <span>Expiry Date</span>
                <strong>{{ $ingredient->expiry_date?->format('d M Y') ?? 'Not set' }}</strong>
            </article>
        </div>

        <div class="info-panel">
            <dl>
                <div>
                    <dt>SKU</dt>
                    <dd>{{ $ingredient->sku ?: 'Not set' }}</dd>
                </div>
                <div>
                    <dt>Supplier</dt>
                    <dd>
                        @if ($ingredient->supplier)
                            <a href="{{ route('suppliers.show', $ingredient->supplier) }}">{{ $ingredient->supplier->name }}</a>
                        @else
                            Not set
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>Cost Price</dt>
                    <dd>{{ $ingredient->cost_price ? 'RM '.$ingredient->cost_price : 'Not set' }}</dd>
                </div>
                <div>
                    <dt>Selling Price</dt>
                    <dd>{{ $ingredient->selling_price ? 'RM '.$ingredient->selling_price : 'Not set' }}</dd>
                </div>
                <div>
                    <dt>Created By</dt>
                    <dd>{{ $ingredient->creator?->name ?? 'Unknown' }}</dd>
                </div>
                <div>
                    <dt>Last Updated By</dt>
                    <dd>{{ $ingredient->updater?->name ?? 'Unknown' }}</dd>
                </div>
                <div>
                    <dt>Notes</dt>
                    <dd>{{ $ingredient->notes ?: 'No notes added.' }}</dd>
                </div>
            </dl>
        </div>

        <div class="table-card movement-preview">
            <div class="section-heading-row">
                <h2>Recent Stock Movement</h2>
                <a href="{{ route('stock.index', ['ingredient' => $ingredient->id]) }}">View all</a>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Before</th>
                        <th>After</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentMovements as $movement)
                        <tr>
                            <td>{{ $movement->created_at->format('d M Y H:i') }}</td>
                            <td>
                                <span class="status-pill {{ $movement->type === 'in' ? 'ok' : 'danger' }}">
                                    {{ $movement->typeLabel() }}
                                </span>
                            </td>
                            <td>{{ $movement->quantity }} {{ $ingredient->unit }}</td>
                            <td>{{ $movement->quantity_before }} {{ $ingredient->unit }}</td>
                            <td>{{ $movement->quantity_after }} {{ $ingredient->unit }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">No stock movement recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (auth()->user()->isAdmin())
            <form class="danger-panel" action="{{ route('inventory.destroy', $ingredient) }}" method="post">
                @csrf
                @method('DELETE')
                <div>
                    <strong>Delete ingredient record</strong>
                    <p>This removes the ingredient from inventory. Stock movement history will be added in Phase 3.</p>
                </div>
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        @endif
    </section>
</main>
@endsection
