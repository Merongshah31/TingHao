@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.reports') }}</p>
                <h1>{{ __('messages.expiry_report') }}</h1>
            </div>
            <div class="page-actions">
                <a href="{{ route('reports.index') }}" class="btn btn-muted">{{ __('messages.reports') }}</a>
                <a href="{{ route('expiry.index') }}" class="btn btn-primary">{{ __('messages.manage_expiry') }}</a>
            </div>
        </div>

        <div class="segmented-actions">
            <a href="{{ route('reports.expiry', ['filter' => 'expiring']) }}" class="{{ $filter !== 'expired' ? 'active' : '' }}">{{ __('messages.expiring_soon') }}</a>
            <a href="{{ route('reports.expiry', ['filter' => 'expired']) }}" class="{{ $filter === 'expired' ? 'active' : '' }}">{{ __('messages.expired') }}</a>
        </div>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.ingredient') }}</th>
                        <th>{{ __('messages.category') }}</th>
                        <th>{{ __('messages.supplier') }}</th>
                        <th>{{ __('messages.quantity') }}</th>
                        <th>{{ __('messages.expiry_date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ingredients as $ingredient)
                        <tr>
                            <td><strong>{{ $ingredient->name }}</strong></td>
                            <td>{{ $ingredient->category?->name ?? __('messages.uncategorized') }}</td>
                            <td>{{ $ingredient->supplier?->name ?? __('messages.no_supplier') }}</td>
                            <td>{{ $ingredient->quantity }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->expiry_date?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">{{ __('messages.no_expiry_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
