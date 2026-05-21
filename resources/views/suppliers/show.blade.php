@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">SUPPLIER DETAIL</p>
                <h1>{{ $supplier->name }}</h1>
                <p>{{ $supplier->contact_person ?: 'No contact person set' }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('suppliers.index') }}" class="btn btn-muted">Back</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-primary">Edit</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <div class="info-panel">
            <dl>
                <div>
                    <dt>Phone</dt>
                    <dd>{{ $supplier->phone ?: 'Not set' }}</dd>
                </div>
                <div>
                    <dt>Email</dt>
                    <dd>{{ $supplier->email ?: 'Not set' }}</dd>
                </div>
                <div>
                    <dt>Address</dt>
                    <dd>{{ $supplier->address ?: 'Not set' }}</dd>
                </div>
                <div>
                    <dt>Notes</dt>
                    <dd>{{ $supplier->notes ?: 'No notes added.' }}</dd>
                </div>
            </dl>
        </div>

        <div class="table-card movement-preview">
            <div class="section-heading-row">
                <h2>Supplied Ingredients</h2>
                <a href="{{ route('inventory.index') }}">Open inventory</a>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Expiry</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($supplier->ingredients as $ingredient)
                        <tr>
                            <td><strong>{{ $ingredient->name }}</strong></td>
                            <td>{{ $ingredient->category?->name ?? 'Uncategorized' }}</td>
                            <td>{{ $ingredient->quantity }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->expiry_date?->format('d M Y') ?? 'Not set' }}</td>
                            <td class="table-actions">
                                <a href="{{ route('inventory.show', $ingredient) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">No ingredients linked to this supplier yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
