@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.supplier_management') }}</p>
                <h1>{{ __('messages.suppliers') }}</h1>
                <p>{{ __('messages.supplier_intro') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">{{ __('messages.dashboard') }}</a>
                <a href="{{ route('inventory.index') }}" class="btn btn-muted">{{ __('messages.inventory') }}</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('suppliers.create') }}" class="btn btn-primary">{{ __('messages.add_supplier') }}</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <form class="filter-bar" method="get" action="{{ route('suppliers.index') }}">
            <input name="search" value="{{ $search }}" type="search" placeholder="Search supplier, contact, phone, or email">
            <button type="submit" class="btn btn-primary">{{ __('messages.search') }}</button>
            <a href="{{ route('suppliers.index') }}" class="btn btn-muted">{{ __('messages.reset') }}</a>
        </form>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.supplier') }}</th>
                        <th>{{ __('messages.contact') }}</th>
                        <th>{{ __('messages.phone') }}</th>
                        <th>{{ __('messages.email') }}</th>
                        <th>{{ __('messages.ingredients') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td><strong>{{ $supplier->name }}</strong></td>
                            <td>{{ $supplier->contact_person ?: __('messages.not_set') }}</td>
                            <td>{{ $supplier->phone ?: __('messages.not_set') }}</td>
                            <td>{{ $supplier->email ?: __('messages.not_set') }}</td>
                            <td>{{ $supplier->ingredients_count }}</td>
                            <td class="table-actions">
                                <a href="{{ route('suppliers.show', $supplier) }}">{{ __('messages.view') }}</a>
                                @if (auth()->user()->isAdmin())
                                    <a href="{{ route('suppliers.edit', $supplier) }}">{{ __('messages.edit') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">{{ __('messages.no_suppliers_found') }}</td>
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
