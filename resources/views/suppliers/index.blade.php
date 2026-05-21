@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">SUPPLIER MANAGEMENT</p>
                <h1>Suppliers</h1>
                <p>Manage supplier contact details and view which ingredients each supplier provides.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">Dashboard</a>
                <a href="{{ route('inventory.index') }}" class="btn btn-muted">Inventory</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('suppliers.create') }}" class="btn btn-primary">Add Supplier</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <form class="filter-bar" method="get" action="{{ route('suppliers.index') }}">
            <input name="search" value="{{ $search }}" type="search" placeholder="Search supplier, contact, phone, or email">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="{{ route('suppliers.index') }}" class="btn btn-muted">Reset</a>
        </form>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Contact</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Ingredients</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td><strong>{{ $supplier->name }}</strong></td>
                            <td>{{ $supplier->contact_person ?: 'Not set' }}</td>
                            <td>{{ $supplier->phone ?: 'Not set' }}</td>
                            <td>{{ $supplier->email ?: 'Not set' }}</td>
                            <td>{{ $supplier->ingredients_count }}</td>
                            <td class="table-actions">
                                <a href="{{ route('suppliers.show', $supplier) }}">View</a>
                                @if (auth()->user()->isAdmin())
                                    <a href="{{ route('suppliers.edit', $supplier) }}">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">No suppliers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $suppliers->links() }}
        </div>
    </section>
</main>
@endsection
